<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Controllers;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

/**
 * Controller di base per la dashboard/iframe Spoki.
 *
 * Scheletro pensato per essere usato così com'è (basta impostare `viewPath` sulla propria
 * cartella viste se si vuole un tema diverso) oppure esteso per aggiungere sezioni proprie
 * (es. un report con grafici personalizzati) — tutti i metodi che producono dati sono
 * `protected` e sovrascrivibili singolarmente.
 *
 * Non gestisce autenticazione/autorizzazione: un'app ospite che estende questo controller
 * deve aggiungere i propri `behaviors()` (`AccessControl` o equivalente).
 */
class DashboardController extends Controller
{
    /**
     * Sezioni Spoki valide per l'iframe. Un host può sovrascrivere per aggiungerne/toglierne.
     *
     * @return array<string, string> slug => etichetta
     */
    protected function sections(): array
    {
        return [
            'dashboard' => Yii::t('app', 'Dashboard'),
            'chats' => Yii::t('app', 'Chat'),
            'templates' => Yii::t('app', 'Template'),
            'automations' => Yii::t('app', 'Automazioni'),
            'contacts' => Yii::t('app', 'Contatti'),
            'lists' => Yii::t('app', 'Liste'),
            'tags' => Yii::t('app', 'Tag'),
        ];
    }

    /**
     * Riferimento owner dell'utente corrente. Default: id dell'utente autenticato Yii2.
     * Un host con un concetto diverso di "chi è il proprietario dell'account" sovrascrive questo metodo.
     */
    protected function resolveOwnerReference(): string
    {
        return (string) Yii::$app->user->id;
    }

    /**
     * Recupera l'account Spoki attivo dell'utente corrente, o lancia 404 se non esiste/non è attivo.
     */
    protected function ensureActiveAccount(): SpokiAccountState
    {
        $repository = Yii::$container->get(SpokiAccountRepositoryInterface::class);
        $state = $repository->findByOwnerReference($this->resolveOwnerReference());

        if ($state === null || $state->status !== SpokiAccountState::STATUS_ACTIVE) {
            throw new NotFoundHttpException(Yii::t('app', 'Account Spoki non trovato o non attivo.'));
        }

        if (empty($state->apiKey) || empty($state->privateKey)) {
            throw new NotFoundHttpException(Yii::t('app', 'Account Spoki non completamente configurato.'));
        }

        return $state;
    }

    /**
     * Dashboard con iframe Spoki.
     */
    public function actionIndex(): string
    {
        $state = $this->ensureActiveAccount();

        return $this->render('index', [
            'spokiAccount' => $state,
            'sections' => $this->sections(),
        ]);
    }

    /**
     * Endpoint AJAX: genera un token di autenticazione fresco per l'iframe (il token non è
     * persistente, va rigenerato ad ogni caricamento).
     *
     * @return array<string, mixed>
     */
    public function actionAuthToken(): array
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $state = $this->ensureActiveAccount();

        /** @var SpokiService $spokiService */
        $spokiService = Yii::$container->get(SpokiService::class);
        $result = $spokiService->getAuthenticationToken((string) $state->emailIframe, (string) $state->privateKey, $state->apiKey);

        if (!$result->success) {
            Yii::$app->response->statusCode = $result->status > 0 ? (int) $result->status : 502;

            return ['success' => false, 'message' => $result->message];
        }

        return ['success' => true, 'token' => $result->data->token, 'uid' => $result->data->uid];
    }
}
