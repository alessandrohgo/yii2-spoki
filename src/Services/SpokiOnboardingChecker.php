<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Services;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\Jobs\SpokiFinalActivationJob;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use Yii;

/**
 * Verifica se un account Spoki in stato ONBOARDING_PENDING ha completato l'onboarding, e in
 * caso positivo accoda {@see SpokiFinalActivationJob}.
 *
 * Generalizza una logica che non ha nulla di specifico per una singola app: l'host decide
 * solo *quando* chiamare {@see self::check()} — al caricamento di una pagina, da un comando
 * cron, da un endpoint AJAX di polling, ecc. Non è `final`: un host può estenderla per
 * personalizzare il criterio di "onboarding completato" (es. un canale diverso da WhatsApp).
 */
class SpokiOnboardingChecker
{
    public function __construct(
        private readonly SpokiService $spokiService,
        private readonly SpokiAccountRepositoryInterface $repository,
    ) {
    }

    /**
     * Controlla lo stato di onboarding per l'owner indicato e, se completato, avvia
     * l'attivazione finale. Non fa nulla se l'account non esiste, non è in attesa di
     * onboarding, o se la chiamata a Spoki fallisce (verrà ritentato alla prossima chiamata).
     */
    public function check(string $ownerReference): void
    {
        $state = $this->repository->findByOwnerReference($ownerReference);
        if ($state === null || $state->status !== SpokiAccountState::STATUS_ONBOARDING_PENDING) {
            return;
        }

        if (empty($state->spokiAccountId) || empty($state->apiKey)) {
            return;
        }

        $result = $this->spokiService->getAccountSummary($state->spokiAccountId, $state->apiKey);
        if (!$result->success) {
            Yii::warning("Errore getAccountSummary durante il controllo onboarding per ownerReference=$ownerReference. mex:" . $result->message, __METHOD__);

            return;
        }

        if (!$this->isOnboardingCompleted($result->data)) {
            return;
        }

        Yii::info("Onboarding confermato per ownerReference=$ownerReference, accodo SpokiFinalActivationJob", __METHOD__);
        $this->pushFinalActivationJob($this->createFinalActivationJob($ownerReference));

        // Lo stato resta ONBOARDING_PENDING finché SpokiFinalActivationJob non completa (passa
        // ad ACTIVE) o fallisce (ERROR) — non serve uno stato intermedio dedicato alla coda:
        // la protezione da doppio accodamento è nel job stesso (vedi il suo controllo iniziale).
    }

    /**
     * Determina se l'onboarding è completato dalla risposta di getAccountSummary(). Un host
     * può sovrascrivere questo metodo se il criterio (es. canale diverso da WhatsApp, altro
     * campo di stato) è diverso dal comportamento di default.
     */
    protected function isOnboardingCompleted(mixed $accountSummary): bool
    {
        $status = null;
        if (isset($accountSummary->channels) && is_array($accountSummary->channels)) {
            foreach ($accountSummary->channels as $channel) {
                if (($channel->type ?? null) === 'whatsapp') {
                    $status = $channel->status ?? null;
                    break;
                }
            }
        }
        $status ??= $accountSummary->status ?? null;

        return $status !== null && strcasecmp((string) $status, 'Active') === 0;
    }

    /**
     * Crea il job di attivazione finale per l'owner indicato. Un host può sovrascrivere questo
     * metodo per accodare una propria sottoclasse di {@see SpokiFinalActivationJob}.
     */
    protected function createFinalActivationJob(string $ownerReference): SpokiFinalActivationJob
    {
        return new SpokiFinalActivationJob(['ownerReference' => $ownerReference]);
    }

    /**
     * Accoda il job sulla coda dell'applicazione. Un host la cui coda non è registrata come
     * componente `queue` di Yii::$app (es. un nome diverso, o un meccanismo diverso da
     * yii2-queue) sovrascrive questo metodo.
     */
    protected function pushFinalActivationJob(SpokiFinalActivationJob $job): void
    {
        Yii::$app->queue->push($job);
    }
}
