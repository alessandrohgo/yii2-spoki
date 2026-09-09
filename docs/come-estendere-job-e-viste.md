# Come estendere job, servizi e viste

Guida pratica con esempi reali per le modifiche più comuni. Tutti gli esempi presuppongono che tu
abbia già registrato i 4 adapter nel container (vedi README, sezione "Come si adatta ad app
diverse").

## 1. Aggiungere un passo extra a un job

**Caso**: dopo l'attivazione, vuoi mandare una notifica interna su Slack al team commerciale
(cosa che il pacchetto non fa e non deve fare, è specifica della tua azienda).

Usa l'hook `afterActivated()`, pensato apposta per questo — non serve toccare nient'altro:

```php
// common/modules/spoki/components/job/SpokiActivationJob.php (nel TUO progetto)
namespace common\modules\spoki\components\job;

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;

class SpokiActivationJob extends \AlessandroHgo\Yii2Spoki\Jobs\SpokiActivationJob
{
    protected function afterActivated(SpokiPurchaseContext $context, SpokiAccountState $state): void
    {
        Yii::$app->slack->send(
            channel: '#vendite',
            message: "Nuovo account Spoki attivato: owner={$context->ownerReference()}, spoki_account_id={$state->spokiAccountId}"
        );
    }
}
```

Poi, dove nel tuo codice oggi accodi il job (es. nel webhook di pagamento), usa la TUA classe
invece di quella del pacchetto:

```php
$job = new \common\modules\spoki\components\job\SpokiActivationJob(['purchaseReference' => (string) $order->id]);
Yii::$app->queue->push($job);
```

**Stessa tecnica per `SpokiFinalActivationJob`**, che ha lo stesso hook `afterActivated(SpokiAccountState $state)`.

## 2. Rimuovere/saltare un passo da un job

**Caso**: il tuo progetto non fa mai una ricarica iniziale di credito insieme all'attivazione
(magari il credito si acquista sempre separatamente) — vuoi che `SpokiActivationJob` salti del
tutto la chiamata `createSubrecharge`.

Ogni passo interno è un metodo `protected` a sé stante: per disattivarne uno, lo sovrascrivi con
un'implementazione vuota, senza toccare `execute()` né gli altri passi:

```php
class SpokiActivationJob extends \AlessandroHgo\Yii2Spoki\Jobs\SpokiActivationJob
{
    protected function rechargeInitialCredit(\AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState $state, int $amountInCents): void
    {
        // Non facciamo mai la ricarica automatica in questo progetto — non chiamare parent::.
    }
}
```

Stesso principio per **saltare i margini di profitto** (se il tuo account Spoki non è un
account Partner con margini da impostare):

```php
protected function applyProfits(\AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState $state): bool
{
    return true; // nessuna chiamata setProfits in questo progetto
}
```

**Nota**: se devi saltare un passo che nell'originale del pacchetto FA parte del valore di
ritorno da cui dipendono i passi successivi (es. `ensureSpokiAccountId()`, da cui dipende tutto
il resto), valuta se ha senso scrivere un job completamente tuo invece di forzare un
override — vedi punto 5.

## 3. Personalizzare il criterio di "onboarding completato"

**Caso**: il tuo progetto considera l'onboarding completo con un criterio diverso da "canale
whatsapp con status Active" (es. un campo custom nella risposta Spoki).

```php
class SpokiOnboardingChecker extends \AlessandroHgo\Yii2Spoki\Services\SpokiOnboardingChecker
{
    protected function isOnboardingCompleted(mixed $accountSummary): bool
    {
        return ($accountSummary->custom_field ?? null) === 'ready';
    }
}
```

Se la tua coda non è il componente `queue` di default di `Yii::$app`, sovrascrivi anche:

```php
protected function pushFinalActivationJob(\AlessandroHgo\Yii2Spoki\Jobs\SpokiFinalActivationJob $job): void
{
    Yii::$app->get('mySpecialQueue')->push($job);
}
```

## 4. Estendere/sovrascrivere la vista dell'iframe

**Caso A — stesso controller, solo il tuo tema/CSS**: nessuna sottoclasse, imposti solo
`viewPath` quando registri il controller:

```php
// backend/config/main.php o dove registri i controller
'controllerMap' => [
    'spoki-dashboard' => [
        'class' => \AlessandroHgo\Yii2Spoki\Controllers\DashboardController::class,
        'viewPath' => '@frontend/views/spoki-dashboard', // le tue viste, stesso controller
    ],
],
```

Poi crei `frontend/views/spoki-dashboard/index.php` copiando la struttura di
`vendor/alessandrohgo/yii2-spoki/src/views/dashboard/index.php` come punto di partenza, con il
tuo HTML/CSS. **Importante**: mantieni la chiamata `DashboardAsset::register($this);` in cima
e il div `#spoki-embedding` con i suoi `data-auth-token-url`/`data-language` — è quello che il
JS del pacchetto usa per far funzionare davvero l'iframe. Puoi cambiare tutto il resto (markup
del menu, CSS, struttura) liberamente.

**Caso B — aggiungere una sezione che il pacchetto non ha** (es. un pulsante "Ricarica credito"
nella dashboard, specifico del tuo progetto): estendi il controller, aggiungi un'azione, e nella
TUA vista (sempre puntata via `viewPath`) aggiungi il link:

```php
class DashboardController extends \AlessandroHgo\Yii2Spoki\Controllers\DashboardController
{
    public function actionRecharge(): \yii\web\Response
    {
        // logica tua: crea ordine di ricarica, redirect al checkout, ecc.
        return $this->redirect(['/spoki-recharge/create']);
    }
}
```

```php
<!-- frontend/views/spoki-dashboard/index.php, tua vista -->
<a href="<?= \yii\helpers\Url::to(['recharge']) ?>">Ricarica credito</a>
<?php // ... resto della vista, come l'originale del pacchetto o come preferisci ?>
```

**Caso C — "chi è l'utente corrente" è diverso da `Yii::$app->user->id`** (raro, ma possibile
se hai un concetto di account condiviso da più utenti): sovrascrivi solo questo metodo,
nient'altro cambia:

```php
protected function resolveOwnerReference(): string
{
    return (string) Yii::$app->user->identity->company_id; // invece dell'id utente
}
```

**Caso D — sostituire completamente il JS** (es. vuoi integrare l'iframe dentro un componente
Vue/React invece che con JS puro): non registrare `DashboardAsset`, usa direttamente l'endpoint
`DashboardController::actionAuthToken()` (restituisce `{success, token, uid}` in JSON) dal tuo
frontend, con la libreria che preferisci — il pacchetto non impone il proprio JS, lo fornisce
solo come opzione pronta.

## 5. Creare un job completamente nuovo, che il pacchetto non fornisce

**Caso**: vuoi un job "revoca account Spoki" (disattivazione), che il pacchetto non ha. Non
estendi nulla — scrivi un job nuovo nel tuo progetto, usando `SpokiService` e le interfacce
esattamente come fa il pacchetto internamente:

```php
namespace common\modules\spoki\components\job;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\SpokiService;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use Yii;
use yii\base\BaseObject;
use yii\queue\JobInterface;

class SpokiRevokeAccountJob extends BaseObject implements JobInterface
{
    public string $ownerReference;

    public function execute($queue): bool
    {
        $repository = Yii::$container->get(SpokiAccountRepositoryInterface::class);
        $state = $repository->findByOwnerReference($this->ownerReference);
        if ($state === null || empty($state->spokiAccountId) || empty($state->apiKey)) {
            return false;
        }

        /** @var SpokiService $spokiService */
        $spokiService = Yii::$container->get(SpokiService::class);
        // usa qui l'endpoint Spoki più adatto (es. revoca api key, se/quando implementato)

        $repository->save($state->with(['status' => SpokiAccountState::STATUS_ERROR]));

        return true;
    }
}
```

Questo è lo stesso pattern usato da tutti i job del pacchetto: niente di magico, solo
`SpokiService` + le interfacce già disponibili.

## Riepilogo: quando fare cosa

| Vuoi... | Come |
|---|---|
| Aggiungere un passo extra | Estendi il job, sovrascrivi `afterActivated()` |
| Saltare un passo esistente | Estendi il job, sovrascrivi quel singolo metodo `protected` con un'implementazione vuota |
| Cambiare un criterio/comportamento di un metodo | Estendi la classe, sovrascrivi solo quel metodo |
| Cambiare solo l'aspetto grafico | Nessuna sottoclasse, imposta `viewPath` |
| Aggiungere una sezione/azione nuova | Estendi il controller, aggiungi l'azione, aggiorna la tua vista |
| Fare qualcosa che il pacchetto non prevede affatto | Scrivi un job/servizio nuovo nel tuo progetto usando `SpokiService` + le interfacce |
