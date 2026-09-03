<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Jobs;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiInvoicingInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface;
use AlessandroHgo\Yii2Spoki\Models\SpokiAccount;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use Yii;
use yii\queue\JobInterface;

/**
 * Job per attivazione account Spoki dopo conferma pagamento.
 *
 * Eseguito dopo che l'acquisto è stato confermato. Esegue le chiamate API Spoki iniziali:
 * 1. Crea account cliente su Spoki (addSvClients)
 * 2. Genera API Key per dashboard (createApiKeyForAccount)
 * 3. Imposta margini partner (setProfits)
 * 4. Genera link onboarding (onboarding)
 * 5. Accredita la ricarica iniziale (createSubrecharge)
 *
 * NON genera la private key (verrà fatto da {@see SpokiFinalActivationJob} dopo l'onboarding).
 *
 * Tutto ciò che riguarda l'app ospite (trovare/chiudere l'acquisto, fatturazione, notifiche)
 * passa dalle 3 interfacce del modulo — questo job non conosce Order, User, o altre classi
 * specifiche di un host.
 */
class SpokiActivationJob implements JobInterface
{
    /**
     * Spoki esprime gli importi in millesimi (valore ×1000); il value object del modulo li
     * riceve in centesimi (valore ×100) — fattore di conversione puramente aritmetico, non
     * dipende dall'host.
     */
    private const int CENTS_TO_SPOKI_MILLESIMI_FACTOR = 10;
    private const int RECHARGE_AMOUNT_DIVISOR = 2;

    public string $purchaseReference;

    public function execute($queue): bool
    {
        Yii::info("Avvio SpokiActivationJob per purchaseReference=$this->purchaseReference", __METHOD__);

        $purchaseGateway = Yii::$container->get(SpokiPurchaseGatewayInterface::class);
        $context = $purchaseGateway->findPendingPurchase($this->purchaseReference);
        if ($context === null) {
            Yii::warning("Nessun acquisto in attesa trovato per purchaseReference=$this->purchaseReference", __METHOD__);

            return false;
        }

        $ownerReference = $context->metadata[SpokiPurchaseContext::METADATA_OWNER_REFERENCE] ?? null;
        $email = $context->metadata[SpokiPurchaseContext::METADATA_EMAIL] ?? null;
        $firstName = $context->metadata[SpokiPurchaseContext::METADATA_FIRST_NAME] ?? null;
        $accountName = $context->metadata[SpokiPurchaseContext::METADATA_ACCOUNT_NAME] ?? null;
        $country = $context->metadata[SpokiPurchaseContext::METADATA_COUNTRY] ?? 'it';
        $countryCode = $context->metadata[SpokiPurchaseContext::METADATA_COUNTRY_CODE] ?? 'IT';

        if (empty($ownerReference) || empty($email) || empty($firstName) || empty($accountName)) {
            Yii::error("Metadata obbligatori mancanti nel contesto acquisto per purchaseReference=$this->purchaseReference", __METHOD__);

            return false;
        }

        $spokiAccount = SpokiAccount::find()->byOwnerReference($ownerReference)->one();
        if (empty($spokiAccount)) {
            Yii::info("Prima esecuzione: creo nuovo SpokiAccount per ownerReference=$ownerReference", __METHOD__);
            $spokiAccount = SpokiAccount::import([
                'owner_reference' => $ownerReference,
                'email' => $email,
                'status' => SpokiAccount::STATUS_PENDING_REQUEST,
            ]);
            if (empty($spokiAccount)) {
                Yii::error('SpokiAccount non salvato', __METHOD__);

                return false;
            }
            Yii::info("SpokiAccount creato con successo, id={$spokiAccount->id}", __METHOD__);
        } else {
            Yii::info("Retry: uso SpokiAccount esistente id={$spokiAccount->id} per ownerReference=$ownerReference", __METHOD__);
            if (empty($spokiAccount->email) || $spokiAccount->email !== $email) {
                $spokiAccount->updateAttributes(['email' => $email]);
            }
        }

        /** @var SpokiService $spokiService */
        $spokiService = Yii::$container->get(SpokiService::class);

        $spokiAccountId = $this->ensureSpokiAccountId($spokiService, $spokiAccount, $email, $firstName, $accountName, $country, $countryCode);
        if ($spokiAccountId === null) {
            return false;
        }

        if (!$this->ensureApiKey($spokiService, $spokiAccount, $spokiAccountId)) {
            return false;
        }

        if (!$this->applyProfits($spokiService, $spokiAccountId)) {
            return false;
        }

        if (!$this->ensureOnboardingUrl($spokiService, $spokiAccount, $spokiAccountId)) {
            return false;
        }

        $this->rechargeInitialCredit($spokiService, $spokiAccountId, $context->amountInCents);

        if (!$purchaseGateway->markFulfilled($context)) {
            Yii::warning("markFulfilled fallito per purchaseReference=$this->purchaseReference", __METHOD__);

            return false;
        }
        Yii::info("Acquisto marcato come completato per purchaseReference=$this->purchaseReference", __METHOD__);

        $this->enqueueInvoice($context);
        $this->notifyPaymentConfirmed($ownerReference, $email);

        Yii::info("SpokiActivationJob completato con successo per purchaseReference=$this->purchaseReference, spoki_account_id=$spokiAccountId", __METHOD__);

        return true;
    }

    /**
     * Crea l'account cliente su Spoki (se non già presente) e ne restituisce l'id, o null in caso di errore.
     */
    private function ensureSpokiAccountId(
        SpokiService $spokiService,
        SpokiAccount $spokiAccount,
        string $email,
        string $firstName,
        string $accountName,
        string $country,
        string $countryCode,
    ): ?int {
        if (!empty($spokiAccount->spoki_account_id)) {
            Yii::info("spoki_account_id già presente: {$spokiAccount->spoki_account_id}, salto chiamata addSvClients", __METHOD__);

            return (int) $spokiAccount->spoki_account_id;
        }

        Yii::info('Chiamata addSvClients per ownerReference=' . $spokiAccount->owner_reference, __METHOD__);
        $result = $spokiService->addSvClients([
            [
                'email' => $email,
                'first_name' => $firstName,
                'account_name' => $accountName,
                'country' => $country,
                'country_code' => $countryCode,
                'vat_amount' => 2200,
            ],
        ]);

        if (!$result->success) {
            Yii::error('Errore addSvClients per ownerReference=' . $spokiAccount->owner_reference . '. mex:' . $result->message, __METHOD__);

            return null;
        }

        if (is_array($result->data) && count($result->data) > 0) {
            $clientData = is_object($result->data[0]) ? $result->data[0] : (object) $result->data[0];
            $spokiAccountId = $clientData->id ?? null;
        } else {
            $spokiAccountId = is_object($result->data) ? ($result->data->id ?? null) : ($result->data['id'] ?? null);
        }

        if (empty($spokiAccountId)) {
            Yii::error('spoki_account_id non trovato nella risposta addSvClients per ownerReference=' . $spokiAccount->owner_reference, __METHOD__);

            return null;
        }

        $spokiAccount->updateAttributes(['spoki_account_id' => $spokiAccountId]);
        Yii::info("spoki_account_id salvato: $spokiAccountId", __METHOD__);

        return (int) $spokiAccountId;
    }

    /**
     * Genera l'API Key per il dashboard Spoki, se non già presente.
     */
    private function ensureApiKey(SpokiService $spokiService, SpokiAccount $spokiAccount, int $spokiAccountId): bool
    {
        if (!empty($spokiAccount->api_key)) {
            Yii::info('api_key già presente, salto chiamata createApiKeyForAccount', __METHOD__);

            return true;
        }

        Yii::info("Chiamata createApiKeyForAccount per spoki_account_id=$spokiAccountId", __METHOD__);
        $result = $spokiService->createApiKeyForAccount(['account' => $spokiAccountId]);

        if (!$result->success) {
            Yii::error("Errore creazione apiKey per spoki_account_id=$spokiAccountId. mex:" . $result->message, __METHOD__);

            return false;
        }

        $spokiAccount->updateAttributes(['api_key' => $result->data->api_key]);
        Yii::info('api_key salvata', __METHOD__);

        return true;
    }

    /**
     * Imposta i margini partner. Va sempre eseguito, anche se un valore precedente esiste già,
     * perché potrebbe essere necessario riapplicare i margini.
     */
    private function applyProfits(SpokiService $spokiService, int $spokiAccountId): bool
    {
        Yii::info("Chiamata setProfits per spoki_account_id=$spokiAccountId", __METHOD__);
        $result = $spokiService->setProfits([
            'account_id' => $spokiAccountId,
            'sms_profit_margin' => 0,
            'utility_profit_margin' => 0,
            'authentication_profit_margin' => 0,
            'marketing_profit_margin' => 0,
            'service_profit_margin' => 0,
            'conversation_profit_margin' => 40,
        ]);

        if (!$result->success) {
            Yii::error("Errore set profits per spoki_account_id=$spokiAccountId. mex:" . $result->message, __METHOD__);

            return false;
        }

        Yii::info('setProfits completato con successo', __METHOD__);

        return true;
    }

    /**
     * Genera il link di onboarding, se non già presente, e aggiorna lo status dell'account.
     */
    private function ensureOnboardingUrl(SpokiService $spokiService, SpokiAccount $spokiAccount, int $spokiAccountId): bool
    {
        if (!empty($spokiAccount->onboarding_url)) {
            Yii::info('onboarding_url già presente, salto chiamata onboarding', __METHOD__);
            if ($spokiAccount->status !== SpokiAccount::STATUS_ONBOARDING_PENDING) {
                $spokiAccount->updateAttributes(['status' => SpokiAccount::STATUS_ONBOARDING_PENDING]);
                Yii::info('Status aggiornato a ONBOARDING_PENDING', __METHOD__);
            }

            return true;
        }

        Yii::info("Chiamata onboarding per spoki_account_id=$spokiAccountId", __METHOD__);
        $result = $spokiService->onboarding(['account' => $spokiAccountId]);

        if (!$result->success) {
            Yii::error("Errore creazione link onboarding per spoki_account_id=$spokiAccountId. mex:" . $result->message, __METHOD__);

            return false;
        }

        $spokiAccount->updateAttributes([
            'onboarding_url' => $result->data->redirect_url,
            'status' => SpokiAccount::STATUS_ONBOARDING_PENDING,
        ]);
        Yii::info('onboarding_url salvato e status aggiornato a ONBOARDING_PENDING', __METHOD__);

        return true;
    }

    /**
     * Accredita la ricarica iniziale, se l'importo dell'acquisto è positivo. Un errore qui
     * non blocca il job: la ricarica può essere ritentata manualmente senza rifare l'intera
     * attivazione.
     */
    private function rechargeInitialCredit(SpokiService $spokiService, int $spokiAccountId, int $amountInCents): void
    {
        $amountInMillesimi = $amountInCents * self::CENTS_TO_SPOKI_MILLESIMI_FACTOR;
        if ($amountInMillesimi <= 0) {
            Yii::warning("Importo acquisto zero o non valido per spoki_account_id=$spokiAccountId, salto createSubrecharge", __METHOD__);

            return;
        }

        $rechargeAmount = (int) floor($amountInMillesimi / self::RECHARGE_AMOUNT_DIVISOR);
        Yii::info("Chiamata createSubrecharge per spoki_account_id=$spokiAccountId, amount=$rechargeAmount millesimi", __METHOD__);
        $result = $spokiService->createSubrecharge([
            'destination_account' => $spokiAccountId,
            'amount' => $rechargeAmount,
        ]);

        if (!$result->success) {
            Yii::error("Errore createSubrecharge per spoki_account_id=$spokiAccountId. mex:" . $result->message, __METHOD__);

            return;
        }

        Yii::info("createSubrecharge completato con successo, amount=$rechargeAmount millesimi", __METHOD__);
    }

    /**
     * Mette in coda la fatturazione dell'acquisto. Un errore qui non blocca il job: la
     * fattura può essere rigenerata manualmente senza rifare l'attivazione.
     */
    private function enqueueInvoice(SpokiPurchaseContext $context): void
    {
        try {
            $invoicing = Yii::$container->get(SpokiInvoicingInterface::class);
            if (!$invoicing->enqueueInvoice($context)) {
                Yii::warning("Errore durante l'accodamento della fattura per purchaseReference={$context->purchaseReference}", __METHOD__);
            }
        } catch (\Throwable $e) {
            Yii::error("Errore durante la generazione della fattura per purchaseReference={$context->purchaseReference}: " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Notifica l'owner che il pagamento è confermato e l'attivazione è in corso. Un errore
     * qui non blocca il job: l'attivazione prosegue comunque.
     */
    private function notifyPaymentConfirmed(string $ownerReference, string $email): void
    {
        try {
            $notifier = Yii::$container->get(SpokiNotifierInterface::class);
            $notifier->notify(SpokiNotifierInterface::EVENT_PAYMENT_CONFIRMED, $ownerReference, ['email' => $email]);
            Yii::info("Notifica payment_confirmed inviata per ownerReference=$ownerReference", __METHOD__);
        } catch (\Throwable $e) {
            Yii::error("Errore invio notifica payment_confirmed per ownerReference=$ownerReference: " . $e->getMessage(), __METHOD__);
        }
    }
}
