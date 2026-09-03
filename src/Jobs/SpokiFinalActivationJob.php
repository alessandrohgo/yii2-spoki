<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Jobs;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use AlessandroHgo\Yii2Spoki\Models\SpokiAccount;
use AlessandroHgo\Yii2Spoki\SpokiService;
use Yii;
use yii\queue\JobInterface;

/**
 * Job finale per completare l'attivazione account Spoki dopo onboarding.
 *
 * Eseguito dopo che l'utente ha completato l'onboarding. Esegue le chiamate API Spoki finali:
 * 1. GET /roles/?search={email} - Cerca ruolo per email
 * 2. POST /roles/add_service_user/ - Crea utente di servizio
 * 3. POST /roles/{id}/generate_private_key/ - Genera private key per l'iframe
 *
 * Dopo il completamento, aggiorna lo status a STATUS_ACTIVE e notifica l'owner tramite
 * {@see SpokiNotifierInterface} — nessuna dipendenza da un sistema email specifico di un host.
 */
class SpokiFinalActivationJob implements JobInterface
{
    public int $spokiAccountId;

    public function execute($queue): bool
    {
        Yii::info("Avvio SpokiFinalActivationJob per spoki_account_id=$this->spokiAccountId", __METHOD__);

        $spokiAccount = SpokiAccount::findOne($this->spokiAccountId);
        if (empty($spokiAccount)) {
            Yii::error("SpokiAccount non trovato per id=$this->spokiAccountId", __METHOD__);

            return false;
        }

        if (empty($spokiAccount->spoki_account_id)) {
            Yii::error("SpokiAccount senza spoki_account_id per id=$this->spokiAccountId", __METHOD__);

            return false;
        }

        if (empty($spokiAccount->api_key)) {
            Yii::error("SpokiAccount senza api_key per id=$this->spokiAccountId", __METHOD__);

            return false;
        }

        /** @var SpokiService $spokiService */
        $spokiService = Yii::$container->get(SpokiService::class);

        $userRole = $this->findUserRole($spokiService, $spokiAccount);
        if ($userRole === null) {
            $spokiAccount->updateAttributes(['status' => SpokiAccount::STATUS_ERROR]);

            return false;
        }

        $serviceUser = $this->createServiceUser($spokiService, $spokiAccount, $userRole);
        if ($serviceUser === null) {
            $spokiAccount->updateAttributes(['status' => SpokiAccount::STATUS_ERROR]);

            return false;
        }

        [$serviceId, $serviceEmail] = $serviceUser;

        $privateKey = $this->generatePrivateKey($spokiService, $spokiAccount, $serviceId);
        if ($privateKey === null) {
            $spokiAccount->updateAttributes(['status' => SpokiAccount::STATUS_ERROR]);

            return false;
        }

        $spokiAccount->updateAttributes([
            'email_iframe' => $serviceEmail,
            'private_key' => $privateKey,
            'status' => SpokiAccount::STATUS_ACTIVE,
        ]);
        Yii::info("private_key salvata e status aggiornato a ACTIVE per spoki_account_id={$spokiAccount->spoki_account_id}", __METHOD__);

        $this->notifyActivated($spokiAccount->owner_reference);

        Yii::info("SpokiFinalActivationJob completato con successo per spoki_account_id={$spokiAccount->spoki_account_id}", __METHOD__);

        return true;
    }

    /**
     * Cerca il ruolo Spoki per email e ne estrae il nome completo. Restituisce null in caso
     * di errore o se il ruolo non è stato trovato.
     */
    private function findUserRole(SpokiService $spokiService, SpokiAccount $spokiAccount): ?string
    {
        Yii::info("Chiamata getRoles per email={$spokiAccount->email}", __METHOD__);
        $result = $spokiService->getRoles($spokiAccount->email, $spokiAccount->api_key);

        if (!$result->success) {
            Yii::error("Errore getRoles per email={$spokiAccount->email}. mex:" . $result->message, __METHOD__);

            return null;
        }

        $user = null;
        if (isset($result->data->results) && is_array($result->data->results) && count($result->data->results) > 0) {
            $user = $result->data->results[0]->user ?? null;
        }

        if (empty($user)) {
            Yii::error("user non trovato nella risposta getRoles per email={$spokiAccount->email}", __METHOD__);

            return null;
        }

        Yii::info("user trovato per email={$spokiAccount->email}", __METHOD__);

        return $user->firstname . ' ' . $user->surname;
    }

    /**
     * Crea l'utente di servizio Spoki. Restituisce [id, email] o null in caso di errore.
     *
     * @return array{0: int, 1: string}|null
     */
    private function createServiceUser(SpokiService $spokiService, SpokiAccount $spokiAccount, string $fullName): ?array
    {
        $result = $spokiService->addServiceUser([
            'role' => 'Administrator',
            'email' => $spokiAccount->email,
            'name' => $fullName,
        ], $spokiAccount->api_key);

        if (!$result->success) {
            Yii::error("Errore creazione utente di servizio per email={$spokiAccount->email}. mex:" . $result->message, __METHOD__);

            return null;
        }

        return [(int) $result->data->id, (string) $result->data->user->email];
    }

    /**
     * Genera la private key finale per l'iframe. Restituisce null in caso di errore o se la
     * risposta non contiene la chiave.
     */
    private function generatePrivateKey(SpokiService $spokiService, SpokiAccount $spokiAccount, int $serviceId): ?string
    {
        Yii::info("Chiamata generatePrivateKey per role_id=$serviceId", __METHOD__);
        $result = $spokiService->generatePrivateKey($serviceId, $spokiAccount->api_key);

        if (!$result->success) {
            Yii::error("Errore generatePrivateKey per role_id=$serviceId. mex:" . $result->message, __METHOD__);

            return null;
        }

        $privateKey = $result->data->value ?? null;
        if (empty($privateKey)) {
            Yii::error("private_key non presente nella risposta per role_id=$serviceId", __METHOD__);

            return null;
        }

        Yii::info("private_key generata con successo per role_id=$serviceId", __METHOD__);

        return $privateKey;
    }

    /**
     * Notifica l'owner che l'attivazione è completata. Un errore qui non blocca il job:
     * l'attivazione è comunque completata.
     */
    private function notifyActivated(string $ownerReference): void
    {
        try {
            $notifier = Yii::$container->get(SpokiNotifierInterface::class);
            $notifier->notify(SpokiNotifierInterface::EVENT_ACTIVATED, $ownerReference, []);
            Yii::info("Notifica activated inviata per ownerReference=$ownerReference", __METHOD__);
        } catch (\Throwable $e) {
            Yii::error("Errore invio notifica activated per ownerReference=$ownerReference: " . $e->getMessage(), __METHOD__);
        }
    }
}
