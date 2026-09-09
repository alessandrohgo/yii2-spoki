<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Jobs;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiInvoicingInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use Yii;
use yii\base\BaseObject;
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
 * Non è `final`: un'app ospite può estenderlo per aggiungere un passo extra tramite
 * {@see self::afterActivated()}, o sovrascrivere singoli passi (tutti i metodi interni sono
 * `protected`) senza dover riscrivere l'intero job.
 */
class SpokiActivationJob extends BaseObject implements JobInterface
{
    /**
     * Spoki esprime gli importi in millesimi (valore ×1000); il value object del modulo li
     * riceve in centesimi (valore ×100) — fattore di conversione puramente aritmetico, non
     * dipende dall'host.
     */
    private const CENTS_TO_SPOKI_MILLESIMI_FACTOR = 10;
    private const RECHARGE_AMOUNT_DIVISOR = 2;

    public string $purchaseReference;

    public function execute($queue): bool
    {
        Yii::info("Avvio SpokiActivationJob per purchaseReference=$this->purchaseReference", __METHOD__);

        $context = $this->purchaseGateway()->findPendingPurchase($this->purchaseReference);
        if ($context === null) {
            Yii::warning("Nessun acquisto in attesa trovato per purchaseReference=$this->purchaseReference", __METHOD__);

            return false;
        }

        $ownerReference = $context->ownerReference();
        $email = $context->metadata[SpokiPurchaseContext::METADATA_EMAIL] ?? null;
        $firstName = $context->metadata[SpokiPurchaseContext::METADATA_FIRST_NAME] ?? null;
        $accountName = $context->metadata[SpokiPurchaseContext::METADATA_ACCOUNT_NAME] ?? null;

        if (empty($ownerReference) || empty($email) || empty($firstName) || empty($accountName)) {
            Yii::error("Metadata obbligatori mancanti nel contesto acquisto per purchaseReference=$this->purchaseReference", __METHOD__);

            return false;
        }

        $state = $this->repository()->findByOwnerReference($ownerReference)
            ?? new SpokiAccountState(ownerReference: $ownerReference, email: $email, status: SpokiAccountState::STATUS_PENDING_REQUEST);

        $state = $this->ensureSpokiAccountId($state, $context);
        if ($state->spokiAccountId === null) {
            return false;
        }

        $state = $this->ensureApiKey($state);
        if ($state->apiKey === null) {
            return false;
        }

        if (!$this->applyProfits($state)) {
            return false;
        }

        $state = $this->ensureOnboardingUrl($state);
        if ($state->onboardingUrl === null) {
            return false;
        }

        $this->rechargeInitialCredit($state, $context->amountInCents);

        if (!$this->purchaseGateway()->markFulfilled($context)) {
            Yii::warning("markFulfilled fallito per purchaseReference=$this->purchaseReference", __METHOD__);

            return false;
        }
        Yii::info("Acquisto marcato come completato per purchaseReference=$this->purchaseReference", __METHOD__);

        $this->afterActivated($context, $state);
        $this->enqueueInvoice($context);
        $this->notifyPaymentConfirmed($ownerReference, $email);

        Yii::info("SpokiActivationJob completato con successo per purchaseReference=$this->purchaseReference, spoki_account_id={$state->spokiAccountId}", __METHOD__);

        return true;
    }

    /**
     * Hook per un'app ospite che vuole eseguire un passo extra subito dopo che l'acquisto è
     * stato marcato completato, ma prima di fatturazione e notifica. Non fa nulla di default.
     */
    protected function afterActivated(SpokiPurchaseContext $context, SpokiAccountState $state): void
    {
    }

    /**
     * Restituisce i margini di profitto da applicare con setProfits(). Un'app ospite li
     * sovrascrive per applicare i propri margini, invece di ereditare quelli di un altro progetto.
     *
     * @return array<string, int>
     */
    protected function profitMargins(): array
    {
        return [
            'sms_profit_margin' => 0,
            'utility_profit_margin' => 0,
            'authentication_profit_margin' => 0,
            'marketing_profit_margin' => 0,
            'service_profit_margin' => 0,
            'conversation_profit_margin' => 0,
        ];
    }

    /**
     * Crea l'account cliente su Spoki (se non già presente) e ne restituisce lo stato aggiornato.
     */
    protected function ensureSpokiAccountId(SpokiAccountState $state, SpokiPurchaseContext $context): SpokiAccountState
    {
        if ($state->spokiAccountId !== null) {
            Yii::info("spoki_account_id già presente: {$state->spokiAccountId}, salto chiamata addSvClients", __METHOD__);

            return $state;
        }

        Yii::info("Chiamata addSvClients per ownerReference={$state->ownerReference}", __METHOD__);
        $result = $this->spokiService()->addSvClients([[
            'email' => $state->email,
            'first_name' => $context->metadata[SpokiPurchaseContext::METADATA_FIRST_NAME] ?? '',
            'account_name' => $context->metadata[SpokiPurchaseContext::METADATA_ACCOUNT_NAME] ?? '',
            'country' => $context->metadata[SpokiPurchaseContext::METADATA_COUNTRY] ?? 'it',
            'country_code' => $context->metadata[SpokiPurchaseContext::METADATA_COUNTRY_CODE] ?? 'IT',
            'vat_amount' => 2200,
        ]]);

        if (!$result->success) {
            Yii::error("Errore addSvClients per ownerReference={$state->ownerReference}. mex:" . $result->message, __METHOD__);

            return $state;
        }

        if (is_array($result->data) && count($result->data) > 0) {
            $clientData = is_object($result->data[0]) ? $result->data[0] : (object) $result->data[0];
            $spokiAccountId = $clientData->id ?? null;
        } else {
            $spokiAccountId = is_object($result->data) ? ($result->data->id ?? null) : ($result->data['id'] ?? null);
        }

        if (empty($spokiAccountId)) {
            Yii::error("spoki_account_id non trovato nella risposta addSvClients per ownerReference={$state->ownerReference}", __METHOD__);

            return $state;
        }

        Yii::info("spoki_account_id salvato: $spokiAccountId", __METHOD__);

        return $this->repository()->save($state->with(['spokiAccountId' => (int) $spokiAccountId]));
    }

    /**
     * Genera l'API key dell'account, se non già presente.
     */
    protected function ensureApiKey(SpokiAccountState $state): SpokiAccountState
    {
        if ($state->apiKey !== null) {
            Yii::info('api_key già presente, salto chiamata createApiKeyForAccount', __METHOD__);

            return $state;
        }

        Yii::info("Chiamata createApiKeyForAccount per spoki_account_id={$state->spokiAccountId}", __METHOD__);
        $result = $this->spokiService()->createApiKeyForAccount(['account' => $state->spokiAccountId]);

        if (!$result->success) {
            Yii::error("Errore creazione apiKey per spoki_account_id={$state->spokiAccountId}. mex:" . $result->message, __METHOD__);

            return $state;
        }

        return $this->repository()->save($state->with(['apiKey' => $result->data->api_key]));
    }

    /**
     * Imposta i margini partner. Va sempre eseguito, anche se un valore precedente esiste già,
     * perché potrebbe essere necessario riapplicare i margini.
     */
    protected function applyProfits(SpokiAccountState $state): bool
    {
        Yii::info("Chiamata setProfits per spoki_account_id={$state->spokiAccountId}", __METHOD__);
        $result = $this->spokiService()->setProfits(array_merge(
            ['account_id' => $state->spokiAccountId],
            $this->profitMargins()
        ));

        if (!$result->success) {
            Yii::error("Errore set profits per spoki_account_id={$state->spokiAccountId}. mex:" . $result->message, __METHOD__);

            return false;
        }

        Yii::info('setProfits completato con successo', __METHOD__);

        return true;
    }

    /**
     * Genera il link di onboarding, se non già presente, e aggiorna lo stato dell'account.
     */
    protected function ensureOnboardingUrl(SpokiAccountState $state): SpokiAccountState
    {
        if ($state->onboardingUrl !== null) {
            Yii::info('onboarding_url già presente, salto chiamata onboarding', __METHOD__);
            if ($state->status !== SpokiAccountState::STATUS_ONBOARDING_PENDING) {
                return $this->repository()->save($state->with(['status' => SpokiAccountState::STATUS_ONBOARDING_PENDING]));
            }

            return $state;
        }

        Yii::info("Chiamata onboarding per spoki_account_id={$state->spokiAccountId}", __METHOD__);
        $result = $this->spokiService()->onboarding(['account' => $state->spokiAccountId]);

        if (!$result->success) {
            Yii::error("Errore creazione link onboarding per spoki_account_id={$state->spokiAccountId}. mex:" . $result->message, __METHOD__);

            return $state;
        }

        return $this->repository()->save($state->with([
            'onboardingUrl' => $result->data->redirect_url,
            'status' => SpokiAccountState::STATUS_ONBOARDING_PENDING,
        ]));
    }

    /**
     * Accredita la ricarica iniziale, se l'importo dell'acquisto è positivo. Un errore qui
     * non blocca il job: la ricarica può essere ritentata manualmente senza rifare l'intera
     * attivazione.
     */
    protected function rechargeInitialCredit(SpokiAccountState $state, int $amountInCents): void
    {
        $amountInMillesimi = $amountInCents * self::CENTS_TO_SPOKI_MILLESIMI_FACTOR;
        if ($amountInMillesimi <= 0) {
            Yii::warning("Importo acquisto zero o non valido per spoki_account_id={$state->spokiAccountId}, salto createSubrecharge", __METHOD__);

            return;
        }

        $rechargeAmount = (int) floor($amountInMillesimi / self::RECHARGE_AMOUNT_DIVISOR);
        Yii::info("Chiamata createSubrecharge per spoki_account_id={$state->spokiAccountId}, amount=$rechargeAmount millesimi", __METHOD__);
        $result = $this->spokiService()->createSubrecharge([
            'destination_account' => $state->spokiAccountId,
            'amount' => $rechargeAmount,
        ]);

        if (!$result->success) {
            Yii::error("Errore createSubrecharge per spoki_account_id={$state->spokiAccountId}. mex:" . $result->message, __METHOD__);

            return;
        }

        Yii::info("createSubrecharge completato con successo, amount=$rechargeAmount millesimi", __METHOD__);
    }

    /**
     * Mette in coda la fatturazione dell'acquisto. Un errore qui non blocca il job: la
     * fattura può essere rigenerata manualmente senza rifare l'attivazione.
     */
    protected function enqueueInvoice(SpokiPurchaseContext $context): void
    {
        try {
            if (!$this->invoicing()->enqueueInvoice($context)) {
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
    protected function notifyPaymentConfirmed(string $ownerReference, string $email): void
    {
        try {
            $this->notifier()->notify(SpokiNotifierInterface::EVENT_PAYMENT_CONFIRMED, $ownerReference, ['email' => $email]);
            Yii::info("Notifica payment_confirmed inviata per ownerReference=$ownerReference", __METHOD__);
        } catch (\Throwable $e) {
            Yii::error("Errore invio notifica payment_confirmed per ownerReference=$ownerReference: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function spokiService(): SpokiService
    {
        return Yii::$container->get(SpokiService::class);
    }

    protected function purchaseGateway(): SpokiPurchaseGatewayInterface
    {
        return Yii::$container->get(SpokiPurchaseGatewayInterface::class);
    }

    protected function repository(): SpokiAccountRepositoryInterface
    {
        return Yii::$container->get(SpokiAccountRepositoryInterface::class);
    }

    protected function invoicing(): SpokiInvoicingInterface
    {
        return Yii::$container->get(SpokiInvoicingInterface::class);
    }

    protected function notifier(): SpokiNotifierInterface
    {
        return Yii::$container->get(SpokiNotifierInterface::class);
    }
}
