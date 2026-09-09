# alessandrohgo/yii2-spoki

Estensione Yii2 per integrare [Spoki](https://spoki.app) (WhatsApp Business): un client PHP per
tutte le chiamate API, con gestione errori uniforme. **L'unica cosa che serve davvero è
`SpokiService`** — tutto il resto (modello per salvare gli account, job pronti per
l'attivazione, controller per l'iframe) è **completamente facoltativo**: componenti pronti per
chi vuole automatizzare, mai un vincolo per chi preferisce chiamare l'SDK a mano.

## Requisiti

- PHP 8.1+
- Yii2 (`yiisoft/yii2` ^2.0)

## Installazione

Finché il pacchetto non è pubblicato su Packagist, installalo puntando direttamente al
repository. Nel `composer.json` della tua app:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/alessandrohgo/yii2-spoki" }
    ],
    "require": {
        "alessandrohgo/yii2-spoki": "^1.0"
    }
}
```

(In sviluppo locale, prima di pubblicare un tag, puoi usare un
[path repository](#sviluppo-locale-path-repository) — vedi in fondo.)

## Configurazione

Nel bootstrap della tua app, registra `SpokiService` nel container con le tue credenziali:

```php
Yii::$container->set(\AlessandroHgo\Yii2Spoki\SpokiService::class, [
    'baseUrl' => 'https://api.spoki.com/api/1',
    'apiKey' => getenv('SPOKI_API_KEY'), // la tua API key Partner
]);
```

Questo è **tutto ciò che serve** per iniziare a usare l'SDK. Il resto di questo README è
organizzato così: prima un esempio minimo, poi il riferimento dei metodi, poi (in fondo) i
componenti opzionali più avanzati (job pronti, storage, iframe).

## Guida rapida

Ecco un esempio minimo e completo: crea un account cliente su Spoki e genera il link di
onboarding da mandargli.

```php
use AlessandroHgo\Yii2Spoki\SpokiService;

/** @var SpokiService $spoki */
$spoki = Yii::$container->get(SpokiService::class);

$result = $spoki->addSvClients([[
    'email' => 'cliente@esempio.com',
    'first_name' => 'Mario',
    'account_name' => 'Azienda SRL',
    'country' => 'it',
    'country_code' => 'IT',
    'vat_amount' => 2200,
]]);

if (!$result->success) {
    throw new \RuntimeException("Errore Spoki: {$result->message}");
}

$spokiAccountId = $result->data[0]->id;

$result = $spoki->onboarding(['account' => $spokiAccountId]);
$onboardingUrl = $result->data->redirect_url; // mandalo al cliente
```

Questo è tutto quello che c'è da sapere per iniziare: `SpokiService` è un client HTTP, ogni
metodo chiama un endpoint e ritorna sempre lo stesso tipo di oggetto (vedi
[Gestione errori](#gestione-errori)). Per il flusso di attivazione completo (fino all'iframe)
vedi [Guida rapida estesa](#guida-rapida-estesa-flusso-di-attivazione-completo) più sotto, o
usa i [job già pronti](#uso-avanzato-job-servizi-e-iframe-pronti-alluso).

## Metodi disponibili in `SpokiService`

| Metodo | Endpoint | Uso |
|---|---|---|
| `addSvClients` | `POST /partners/add_sv_clients/` | Partner: crea un nuovo account cliente |
| `createApiKeyForAccount` | `POST /partners/create_api_key_for_account/` | Partner: genera l'API key per un account |
| `createSubrecharge` | `POST /partners/create_subrecharge/` | Partner: ricarica credito su un account |
| `onboarding` | `POST /partners/onboarding/` | Partner: genera il link di onboarding |
| `setProfits` | `POST /partners/set_profits/` | Partner: imposta i margini di profitto |
| `getAccountReport` | `GET /partners/get_account_report/` | Partner: report per intervallo di date |
| `getRoles` | `GET /roles/` | Cerca ruoli, opzionalmente per email |
| `addServiceUser` | `POST /roles/add_service_user/` | Crea un utente di servizio |
| `generatePrivateKey` | `POST /roles/{id}/generate_private_key/` | Genera la private key per l'iframe |
| `getAccountSummary` | `GET /accounts/{id}/` | Riepilogo account (credito, stato) |
| `getAuthenticationToken` | `POST /auth/get_authentication_token/` | Genera il token per l'iframe |
| `updatePartnerRole` | `POST /partner-roles/{id}/update_role/` | Aggiorna un partner role — **corpo della richiesta non documentato con certezza**, verificare prima dell'uso in produzione |

Spoki ha due modalità d'uso, entrambe coperte da questi stessi metodi:

| Modalità | Metodi tipici | API key da passare |
|---|---|---|
| **Partner** (apri/gestisci account per conto di altri clienti) | `addSvClients`, `createApiKeyForAccount`, `setProfits`, `onboarding`, `createSubrecharge` | La propria API key Partner (quella già configurata di default, nessun parametro extra) |
| **Cliente diretto** (un account Spoki già esistente) | `getRoles`, `addServiceUser`, `generatePrivateKey`, `getAccountSummary` | L'API key dell'account stesso, passata come **ultimo parametro opzionale** di ogni metodo |

```php
// Usa l'API key di default (Partner) — nessun parametro extra
$spoki->addSvClients([...]);

// Usa l'API key DELL'ACCOUNT invece di quella Partner — ultimo parametro
$spoki->getRoles('cliente@esempio.com', $accountApiKey);
```

## Gestione errori

`SpokiService` non lancia mai eccezioni per errori HTTP o di validazione dell'API Spoki — ogni
metodo ritorna sempre lo stesso tipo di oggetto:

```php
(object) [
    'success' => bool,   // true solo per risposte HTTP 2xx
    'status'  => int,    // status HTTP
    'message' => ?string, // messaggio d'errore leggibile, null se success
    'data'    => mixed,  // stdClass o array di stdClass con il payload
]
```

```php
$result = $spokiService->onboarding(['account' => $spokiAccountId]);
if (!$result->success) {
    Yii::error("Errore onboarding: {$result->message}", __METHOD__);
    return false;
}
$onboardingUrl = $result->data->redirect_url;
```

Chi consuma l'SDK decide cosa fare in caso di errore (log, retry, eccezione propria).

## Guida rapida estesa: flusso di attivazione completo

Continuando l'esempio di prima, dopo aver creato l'account e generato l'onboarding, mancano 3
passaggi: margini di profitto, e — **dopo che il cliente ha completato l'onboarding** su Spoki —
creare l'utente di servizio e generare la private key per l'iframe.

```php
// Subito dopo la creazione dell'account (opzionale)
$spoki->setProfits([
    'account_id' => $spokiAccountId,
    'conversation_profit_margin' => 40,
    // ...gli altri margini a 0 se non servono
]);

// (Opzionale) accredita subito un po' di credito — importo in "millesimi", vedi doc API Spoki
$spoki->createSubrecharge(['destination_account' => $spokiAccountId, 'amount' => 5000]);
```

```php
// --- Dopo che il cliente ha completato l'onboarding (webhook/notifica Spoki, gestita da te) ---

// Recupera l'API key generata in precedenza
$result = $spoki->createApiKeyForAccount(['account' => $spokiAccountId]);
$accountApiKey = $result->data->api_key;

// Trova il ruolo dell'account per email, per recuperare il nome completo dell'utente
$result = $spoki->getRoles('cliente@esempio.com', $accountApiKey);
$user = $result->data->results[0]->user;
$fullName = $user->firstname . ' ' . $user->surname;

// Crea l'utente di servizio (necessario per l'iframe)
$result = $spoki->addServiceUser([
    'role' => 'Administrator',
    'email' => 'cliente@esempio.com',
    'name' => $fullName,
], $accountApiKey);
$serviceUserId = $result->data->id;
$emailIframe = $result->data->user->email; // salvala, serve per l'iframe

// Genera la private key finale — salvala, serve per l'iframe
$result = $spoki->generatePrivateKey($serviceUserId, $accountApiKey);
$privateKey = $result->data->value;
```

**Per mostrare l'iframe**, ripeti questa chiamata a ogni caricamento (il token non è persistente):

```php
$result = $spoki->getAuthenticationToken($emailIframe, $privateKey, $accountApiKey);
$iframeUrl = "https://spoki.app/dashboard?auth_token={$result->data->token}&auth_uid={$result->data->uid}&language=it";
// stampa $iframeUrl in un tag <iframe src="...">
```

Sezioni valide al posto di `dashboard`: `chats`, `templates`, `automations`, `contacts`, `lists`, `tags`.

**Se il tuo progetto gestisce un account già esistente** (non aperto tramite un account
Partner), salti i primi passaggi: parti direttamente da "Recupera l'API key" in su, con
`$spokiAccountId`/`$accountApiKey` che ti ha già dato Spoki.

Scrivere ed eseguire manualmente questa sequenza va benissimo per un caso d'uso semplice. Se
vuoi automatizzarla del tutto (coda, retry, storage dello stato, iframe pronto), continua a
leggere.

## Endpoint documentati da Spoki ma NON implementati in questo SDK

Trovati nella documentazione ufficiale Spoki, ma il path/parametri esatti non sono verificati
(non c'è un ambiente di test Spoki: ogni chiamata reale crea account o modifica ruoli veri, quindi
non si possono verificare "per tentativi"). Non implementati finché non saranno confermati da un
uso reale o da un riferimento certo alla documentazione.

**Partners**: List/search partners (segnato deprecato da Spoki), Get associated accounts,
Revoke API Key For Account, Move Credit From Account (Software Vendor Only), Get account
forecasts.

**Roles**: Retrieve role (singolo, by id), Has Private Key, Update Role (`/roles/{id}/...`,
diverso da `updatePartnerRole` che usa `/partner-roles/...`), Delete role.

**Partner Roles**: List/search, Retrieve, Has Private Key, Delete role (Generate Private Key e
Update Role di questa categoria sono già coperti da `generatePrivateKey`/`updatePartnerRole`).

**Accounts**: List/search/filter accounts, Retrieve account by phone, Current report, Create
Onboarding Link (variante lato account, diversa da `onboarding()` che è quella Partner).

---

## Uso avanzato: job, servizi e iframe pronti all'uso

> ⚠️ **Tutto da qui in poi è facoltativo, e non introduce nessun vincolo su come usare l'SDK.**
> Se ti bastano le chiamate dirette della guida rapida sopra, non devi leggere altro: nessun job,
> nessuna interfaccia da implementare, nessuna sottoclasse da scrivere. Quello che segue serve
> **solo** a chi vuole automatizzare la sequenza (coda, retry se una chiamata fallisce a metà,
> storage dello stato, iframe con JS già pronto) invece di scriverla e mantenerla a mano.

### Perché servono 4 interfacce

Questi job non sanno nulla del tuo progetto (niente `Order`, `User`, o una tabella specifica):
comunicano tramite 4 interfacce, che tu implementi con un piccolo adapter per collegarli al tuo
dominio (ordini, fatturazione, notifiche, storage). Se una di queste cose non esiste nel tuo
progetto, il tuo adapter per quell'interfaccia semplicemente non fa nulla.

| Interfaccia | Cosa astrae | Se il tuo progetto non ne ha bisogno |
|---|---|---|
| `SpokiPurchaseGatewayInterface` | Trovare/chiudere l'acquisto collegato all'attivazione | Adapter no-op (nessun concetto di "acquisto") |
| `SpokiInvoicingInterface` | Emettere un documento di fatturazione | Adapter che ritorna sempre `true` senza fare nulla |
| `SpokiNotifierInterface` | Inviare una notifica (email o altro canale) | Adapter che non fa nulla |
| `SpokiAccountRepositoryInterface` | Salvare/leggere lo stato dell'account Spoki | Usa `SpokiAccount` del pacchetto (vedi [Storage pronto](#storage-pronto-spokiaccount)) invece di scriverne uno tuo |

### Esempio: adattare i job al tuo dominio

```php
// Nel tuo progetto — adapter verso il TUO modello Order/User esistente
class MySpokiPurchaseGateway implements \AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface
{
    public function findPendingPurchase(string $purchaseReference): ?SpokiPurchaseContext
    {
        $order = Order::findOne((int) $purchaseReference);
        if (!$order) return null;

        return new SpokiPurchaseContext(
            purchaseReference: $purchaseReference,
            amountInCents: (int) round($order->total * 100),
            currency: 'EUR',
            metadata: [
                SpokiPurchaseContext::METADATA_OWNER_REFERENCE => (string) $order->user_id,
                SpokiPurchaseContext::METADATA_EMAIL => $order->email,
                SpokiPurchaseContext::METADATA_FIRST_NAME => $order->first_name,
                SpokiPurchaseContext::METADATA_ACCOUNT_NAME => $order->account_name,
            ],
        );
    }

    public function markFulfilled(SpokiPurchaseContext $context): bool
    {
        return Order::findOne((int) $context->purchaseReference)->close();
    }
}
```

Se il tuo progetto non ha un sistema di acquisti (es. attivazione gratuita), l'adapter è ancora
più semplice — costruisci il contesto direttamente dai tuoi dati, senza cercare nessun "ordine":

```php
class NullSpokiPurchaseGateway implements \AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface
{
    public function findPendingPurchase(string $purchaseReference): ?SpokiPurchaseContext
    {
        $user = User::findOne((int) $purchaseReference);
        if (!$user) return null;

        return new SpokiPurchaseContext(
            purchaseReference: $purchaseReference,
            amountInCents: 0,
            currency: 'EUR',
            metadata: [
                SpokiPurchaseContext::METADATA_OWNER_REFERENCE => (string) $user->id,
                SpokiPurchaseContext::METADATA_EMAIL => $user->email,
                SpokiPurchaseContext::METADATA_FIRST_NAME => $user->firstName,
                SpokiPurchaseContext::METADATA_ACCOUNT_NAME => $user->companyName,
            ],
        );
    }

    public function markFulfilled(SpokiPurchaseContext $context): bool
    {
        return true; // nessun ordine da chiudere
    }
}
```

Registra i 4 adapter nel bootstrap:

```php
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface::class, MyPurchaseGateway::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiInvoicingInterface::class, MyInvoicing::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface::class, MyNotifier::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface::class, MyAccountRepository::class);
```

### Come si collegano i pezzi

| Quando | Cosa fai | Cosa succede |
|---|---|---|
| Il pagamento/attivazione è confermato (nel tuo webhook, o dove lo gestisci tu) | `Yii::$app->queue->push(new SpokiActivationJob(['purchaseReference' => (string) $order->id]))` | Crea l'account su Spoki, genera l'onboarding, salva lo stato tramite il tuo `SpokiAccountRepositoryInterface` |
| Vuoi controllare se il cliente ha completato l'onboarding (al caricamento di una pagina, da un cron, da un endpoint AJAX — decidi tu) | `Yii::$container->get(SpokiOnboardingChecker::class)->check($ownerReference)` | Se completato, accoda automaticamente `SpokiFinalActivationJob` |
| Il job finale è stato eseguito | (niente da fare) | L'account è `STATUS_ACTIVE`, pronto per l'iframe |
| Il cliente vuole vedere la dashboard | Registra/estendi `DashboardController` (vedi sotto) | Mostra l'iframe, già funzionante |

```php
// Dove nel tuo progetto oggi controlli lo stato (es. il caricamento di una pagina)
use AlessandroHgo\Yii2Spoki\Services\SpokiOnboardingChecker;

public function actionIndex()
{
    Yii::$container->get(SpokiOnboardingChecker::class)->check((string) Yii::$app->user->id);
    // ... resto della tua action
}
```

`SpokiOnboardingChecker` non richiede un `Yii::$container->set()` a parte: Yii2 lo istanzia
risolvendo automaticamente `SpokiService`/`SpokiAccountRepositoryInterface` dal costruttore.

### Personalizzare i job pronti (facoltativo — solo se hai scelto di usarli)

**`SpokiActivationJob` funziona già così com'è, senza scrivere nessuna sottoclasse** — i margini
di profitto di default sono tutti zero e sono validi, non è richiesto sovrascrivere nulla per
usarlo. Estenderlo serve **solo** se vuoi cambiare un comportamento specifico (es. margini
diversi da zero, o un passo extra) — e riguarda solo chi ha scelto la sezione precedente
("Come si collegano i pezzi"). Se chiami l'SDK direttamente (Guida rapida), non esiste alcun
job: passi i margini che vuoi direttamente a `setProfits([...])`, senza nessuna sottoclasse.

Se invece usi `SpokiActivationJob` e vuoi margini diversi da zero, nessuno dei job è `final` e
ogni passo interno è un metodo `protected`, pensato per essere sovrascritto singolarmente — non
serve mai copiare l'intero job per cambiare un dettaglio:

```php
class MySpokiActivationJob extends \AlessandroHgo\Yii2Spoki\Jobs\SpokiActivationJob
{
    // Sovrascrivi SOLO se vuoi margini diversi dal default (tutti zero, già validi così)
    protected function profitMargins(): array
    {
        return array_merge(parent::profitMargins(), ['conversation_profit_margin' => 40]);
    }

    // Facoltativo: aggiungi un passo extra dopo l'attivazione
    protected function afterActivated(SpokiPurchaseContext $context, SpokiAccountState $state): void
    {
        Yii::info("Attivazione completata per {$context->purchaseReference}", __METHOD__);
    }
}
```

Altre ricette (saltare un passo, personalizzare il criterio di onboarding, creare un job nuovo
da zero) sono in [`docs/come-estendere-job-e-viste.md`](docs/come-estendere-job-e-viste.md).

### La dashboard con iframe, pronta all'uso

Anche questo è facoltativo e indipendente dai job: se preferisci una tua vista, ti basta
`getAuthenticationToken()` dell'SDK (vedi "Guida rapida estesa" sopra) — nessun controller da
registrare. Se invece vuoi qualcosa di già pronto, registra il controller nella configurazione
della tua app — nessun'altra riga di codice:

```php
'controllerMap' => [
    'spoki-dashboard' => \AlessandroHgo\Yii2Spoki\Controllers\DashboardController::class,
],
```

Da questo momento, `/spoki-dashboard/index` mostra una dashboard con menu e iframe **già
funzionante** (autenticazione, cambio sezione senza reload, deep link — gestiti dal JS incluso,
nessuna dipendenza da jQuery/Bootstrap). Tre modi per personalizzarlo:

1. **Solo tema/CSS diverso**: imposta `viewPath` sulla tua cartella (copia la vista del
   pacchetto come punto di partenza, mantenendo `DashboardAsset::register($this);` e il div
   `#spoki-embedding` con i suoi data-attribute — è quello che il JS usa per funzionare).
2. **Aggiungere una sezione**: estendi `DashboardController`, aggiungi un'azione.
3. **"Utente corrente" diverso da `Yii::$app->user->id`**: sovrascrivi solo `resolveOwnerReference()`.

Dettagli ed esempi completi in [`docs/come-estendere-job-e-viste.md`](docs/come-estendere-job-e-viste.md).

### Storage pronto: `SpokiAccount`

Se non hai già una tua tabella per salvare lo stato degli account, il pacchetto ne fornisce una
generica (`owner_reference`, nessuna foreign key) pronta all'uso, come implementazione di
`SpokiAccountRepositoryInterface`:

```bash
yii migrate --migrationPath=@vendor/alessandrohgo/yii2-spoki/src/migrations
```

**Regola**: questa migrazione contiene solo colonne che corrispondono a un dato realmente
restituito da `SpokiService` (es. `spoki_account_id` da `addSvClients`), più il minimo
bookkeeping interno. Se il tuo progetto ha bisogno di altre colonne, crea una migrazione **nel
tuo modulo interno** che estende/affianca questa tabella — non modificare quella del pacchetto.

### Riferimento: campi dei value object

`SpokiPurchaseContext` (costruito dal tuo `SpokiPurchaseGatewayInterface`):

| Campo | Tipo | Note |
|---|---|---|
| `purchaseReference` | `string` | Lo stesso valore passato a `findPendingPurchase()` |
| `amountInCents` | `int` | Importo in centesimi (conversione in "millesimi" Spoki già gestita dal job) |
| `currency` | `string` | Codice ISO 4217 (es. `EUR`) |
| `metadata[...::METADATA_OWNER_REFERENCE]` | `string` | **Obbligatorio** |
| `metadata[...::METADATA_EMAIL]` | `string` | **Obbligatorio** |
| `metadata[...::METADATA_FIRST_NAME]` | `string` | **Obbligatorio** |
| `metadata[...::METADATA_ACCOUNT_NAME]` | `string` | **Obbligatorio** |
| `metadata[...::METADATA_COUNTRY]` | `string` | Opzionale, default `'it'` |
| `metadata[...::METADATA_COUNTRY_CODE]` | `string` | Opzionale, default `'IT'` |
| `metadata['...']` (chiavi tue, libere) | `mixed` | Il job le ignora, utili solo al tuo adapter |

`SpokiAccountState` (letto/scritto dal tuo `SpokiAccountRepositoryInterface`, immutabile — usa
`->with([...])` per un aggiornamento):

| Campo | Tipo | Impostato da |
|---|---|---|
| `ownerReference` | `string` | Te, alla creazione |
| `spokiAccountId` | `?int` | `SpokiActivationJob` (da `addSvClients`) |
| `apiKey` | `?string` | `SpokiActivationJob` (da `createApiKeyForAccount`) |
| `email` | `?string` | Te, alla creazione |
| `emailIframe` | `?string` | `SpokiFinalActivationJob` (da `addServiceUser`) |
| `onboardingUrl` | `?string` | `SpokiActivationJob` (da `onboarding`) |
| `privateKey` | `?string` | `SpokiFinalActivationJob` (da `generatePrivateKey`) |
| `status` | `int` | Uno tra `SpokiAccountState::STATUS_*` |

---

## Sviluppo locale (path repository)

Prima di pubblicare un tag, per sviluppare/testare il pacchetto contro un'app reale:

```json
"repositories": [
    { "type": "path", "url": "../yii2-spoki" }
],
"require": {
    "alessandrohgo/yii2-spoki": "@dev"
}
```

## Test del pacchetto stesso

```bash
composer install
vendor/bin/phpunit
```

## Licenza

[MIT](LICENSE).
