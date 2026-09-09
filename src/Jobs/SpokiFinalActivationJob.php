<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Jobs;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

/**
 * Job finale per completare l'attivazione account Spoki dopo onboarding.
 *
 * Eseguito dopo che l'utente ha completato l'onboarding. Esegue le chiamate API Spoki finali:
 * 1. GET /roles/?search={email} - Cerca ruolo per email
 * 2. POST /roles/add_service_user/ - Crea utente di servizio
 * 3. POST /roles/{id}/generate_private_key/ - Genera private key per l'iframe
 *
 * Dopo il completamento, aggiorna lo stato ad ACTIVE e notifica l'owner tramite
 * {@see SpokiNotifierInterface}.
 *
 * Non è `final`: un'app ospite può estenderlo per aggiungere un passo extra o sovrascrivere
 * singoli passi (tutti i metodi interni sono `protected`).
 */
class SpokiFinalActivationJob extends BaseObject implements JobInterface
{
    public string $ownerReference;

    public function execute($queue): bool
    {
        Yii::info("Avvio SpokiFinalActivationJob per ownerReference=$this->ownerReference", __METHOD__);

        $state = $this->repository()->findByOwnerReference($this->ownerReference);
        if ($state === null) {
            Yii::error("Account Spoki non trovato per ownerReference=$this->ownerReference", __METHOD__);

            return false;
        }

        if (empty($state->spokiAccountId) || empty($state->apiKey)) {
            Yii::error("Account Spoki senza spoki_account_id o api_key per ownerReference=$this->ownerReference", __METHOD__);

            return false;
        }

        if ($state->status === SpokiAccountState::STATUS_ACTIVE) {
            Yii::info("Account già ACTIVE per ownerReference=$this->ownerReference, nessuna azione (esecuzione duplicata del job)", __METHOD__);

            return true;
        }

        $fullName = $this->findUserRole($state);
        if ($fullName === null) {
            $this->repository()->save($state->with(['status' => SpokiAccountState::STATUS_ERROR]));

            return false;
        }

        $serviceUser = $this->createServiceUser($state, $fullName);
        if ($serviceUser === null) {
            $this->repository()->save($state->with(['status' => SpokiAccountState::STATUS_ERROR]));

            return false;
        }

        [$serviceId, $serviceEmail] = $serviceUser;

        $privateKey = $this->generatePrivateKey($state, $serviceId);
        if ($privateKey === null) {
            $this->repository()->save($state->with(['status' => SpokiAccountState::STATUS_ERROR]));

            return false;
        }

        $state = $this->repository()->save($state->with([
            'emailIframe' => $serviceEmail,
            'privateKey' => $privateKey,
            'status' => SpokiAccountState::STATUS_ACTIVE,
        ]));
        Yii::info("private_key salvata e status aggiornato a ACTIVE per ownerReference=$this->ownerReference", __METHOD__);

        $this->afterActivated($state);
        $this->notifyActivated($this->ownerReference);

        Yii::info("SpokiFinalActivationJob completato con successo per ownerReference=$this->ownerReference", __METHOD__);

        return true;
    }

    /**
     * Hook per un'app ospite che vuole eseguire un passo extra subito dopo che l'account è
     * stato attivato, prima della notifica. Non fa nulla di default.
     */
    protected function afterActivated(SpokiAccountState $state): void
    {
    }

    /**
     * Cerca il ruolo Spoki per email e ne estrae il nome completo. Restituisce null in caso
     * di errore o se il ruolo non è stato trovato.
     */
    protected function findUserRole(SpokiAccountState $state): ?string
    {
        Yii::info("Chiamata getRoles per email={$state->email}", __METHOD__);
        $result = $this->spokiService()->getRoles($state->email, $state->apiKey);

        if (!$result->success) {
            Yii::error("Errore getRoles per email={$state->email}. mex:" . $result->message, __METHOD__);

            return null;
        }

        $user = null;
        if (isset($result->data->results) && is_array($result->data->results) && count($result->data->results) > 0) {
            $user = $result->data->results[0]->user ?? null;
        }

        if (empty($user)) {
            Yii::error("user non trovato nella risposta getRoles per email={$state->email}", __METHOD__);

            return null;
        }

        Yii::info("user trovato per email={$state->email}", __METHOD__);

        return $user->firstname . ' ' . $user->surname;
    }

    /**
     * Crea l'utente di servizio Spoki. Restituisce [id, email] o null in caso di errore.
     *
     * @return array{0: int, 1: string}|null
     */
    protected function createServiceUser(SpokiAccountState $state, string $fullName): ?array
    {
        $result = $this->spokiService()->addServiceUser([
            'role' => 'Administrator',
            'email' => $state->email,
            'name' => $fullName,
        ], $state->apiKey);

        if (!$result->success) {
            Yii::error("Errore creazione utente di servizio per email={$state->email}. mex:" . $result->message, __METHOD__);

            return null;
        }

        return [(int) $result->data->id, (string) $result->data->user->email];
    }

    /**
     * Genera la private key finale per l'iframe. Restituisce null in caso di errore o se la
     * risposta non contiene la chiave.
     */
    protected function generatePrivateKey(SpokiAccountState $state, int $serviceId): ?string
    {
        Yii::info("Chiamata generatePrivateKey per role_id=$serviceId", __METHOD__);
        $result = $this->spokiService()->generatePrivateKey($serviceId, $state->apiKey);

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
    protected function notifyActivated(string $ownerReference): void
    {
        try {
            $this->notifier()->notify(SpokiNotifierInterface::EVENT_ACTIVATED, $ownerReference, []);
            Yii::info("Notifica activated inviata per ownerReference=$ownerReference", __METHOD__);
        } catch (\Throwable $e) {
            Yii::error("Errore invio notifica activated per ownerReference=$ownerReference: " . $e->getMessage(), __METHOD__);
        }
    }

    protected function spokiService(): SpokiService
    {
        return Yii::$container->get(SpokiService::class);
    }

    protected function repository(): SpokiAccountRepositoryInterface
    {
        return Yii::$container->get(SpokiAccountRepositoryInterface::class);
    }

    protected function notifier(): SpokiNotifierInterface
    {
        return Yii::$container->get(SpokiNotifierInterface::class);
    }
}
