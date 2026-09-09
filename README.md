# alessandrohgo/yii2-spoki

SDK e componenti per Yii2 per l'integrazione con [Spoki](https://spoki.app) (attivazione
account, onboarding, iframe, ricariche): tutte le chiamate API con gestione errori, un
modello/tabella generico per salvare gli account, **e** job/servizi/controller già pronti per
l'attivazione e l'iframe — costruiti su 4 interfacce, così ogni progetto li adatta al proprio
dominio (ordini, pagamenti, notifiche, storage) scrivendo solo dei piccoli adapter, senza
riscrivere la logica Spoki.

## Indice

- [Requisiti](#requisiti)
- [I job sono obbligatori? Tre livelli di utilizzo](#i-job-sono-obbligatori-tre-livelli-di-utilizzo)
- [Cosa contiene](#cosa-contiene)
- [Come si adatta ad app diverse](#come-si-adatta-ad-app-diverse)
- [Metodi disponibili in SpokiService](#metodi-disponibili-in-spokiservice)
- [Esempio completo: attivare un account come Partner](#esempio-completo-attivare-un-account-come-partner-chiamando-lsdk-direttamente)
- [Embedding via iframe: il flusso completo](#embedding-via-iframe-il-flusso-completo)
- [Due modalità d'uso: account Partner e cliente diretto](#due-modalità-duso-account-partner-e-cliente-diretto)
- [Gestione errori](#gestione-errori)
- [Endpoint non implementati](#endpoint-documentati-da-spoki-ma-non-implementati-in-questo-sdk)
- [Sviluppo](#sviluppo)
- [Installazione](#installazione-in-unapp-yii2-sviluppo-locale-prima-della-pubblicazione)
- [Licenza](#licenza)

## Requisiti

- PHP 8.1+
- Yii2 (`yiisoft/yii2` ^2.0)
- `yiisoft/yii2-queue` ^2.3 (solo se usi `SpokiActivationJob`/`SpokiFinalActivationJob`/`SpokiOnboardingChecker` — vedi livelli sotto)
- Un database supportato da Yii2 (solo se usi la migrazione/modello `SpokiAccount` del pacchetto)

## I job sono obbligatori? Tre livelli di utilizzo

**No.** Niente in questo pacchetto è obbligatorio oltre a `SpokiService`. Scegli il livello che
ti serve, anche mescolando pezzi di livelli diversi nello stesso progetto:

### Livello 1 — Solo l'SDK, chiami tutto a mano

Nessuna interfaccia da implementare, nessun job, nessuna tabella. Usi `SpokiService` come
chiameresti un client HTTP qualsiasi, gestendo tu ogni cosa (quando chiamare cosa, dove salvare
i risultati). Adatto per una prova rapida, uno script una tantum, o un caso d'uso minimo con 2-3
chiamate. Vedi [Esempio completo](#esempio-completo-attivare-un-account-come-partner-chiamando-lsdk-direttamente).

```php
$spoki = Yii::$container->get(SpokiService::class);
$result = $spoki->addSvClients([...]);
// gestisci tu il risultato, salvalo dove vuoi, decidi tu i passi successivi
```

### Livello 2 — SDK + storage pronto, ancora senza job

Usi anche `SpokiAccount`/`SpokiAccountQuery`/`SpokiAccountSearch` e la migrazione del pacchetto
per salvare lo stato degli account (utile se stai partendo da zero e non hai già una tua
tabella), ma continui a orchestrare le chiamate manualmente, senza job né interfacce.

### Livello 3 — Job/servizi pronti, tramite le 4 interfacce

Scrivi 4 piccoli adapter (traduzione verso il tuo dominio) e ottieni gratis tutta la sequenza di
attivazione (`SpokiActivationJob`, `SpokiFinalActivationJob`, `SpokiOnboardingChecker`), già
gestita con retry idempotenti e gestione errori. Vedi
[Come si adatta ad app diverse](#come-si-adatta-ad-app-diverse). Puoi fermarti qui, oppure:

### Livello 4 — Anche controller/viste pronti

Usi (o estendi) anche `DashboardController` per l'iframe, invece di scriverne uno tuo da zero.

**In sintesi**: più sali di livello, meno codice scrivi tu, ma più ti alinei alle scelte del
pacchetto (nomi neutri, 4 interfacce). Se il tuo caso d'uso è minimo, il Livello 1 non è "meno
completo" — è la scelta corretta per quel caso.

## Cosa contiene

- **[`src/SpokiService.php`](src/SpokiService.php)** — client per tutte le chiamate API (un solo
  file, un metodo per endpoint). Ogni metodo ritorna sempre `{ success, status, message, data }`,
  mai un'eccezione — vedi [Gestione errori](#gestione-errori).
- **`src/Contracts/`** — 4 interfacce con cui il pacchetto si disaccoppia dal dominio di ogni
  progetto (acquisto, fatturazione, notifiche, storage account) — vedi
  [Come si adatta ad app diverse](#come-si-adatta-ad-app-diverse).
- **`src/ValueObjects/`** — `SpokiPurchaseContext` e `SpokiAccountState`, i dati scambiati tra il
  pacchetto e gli adapter di un progetto, senza esporre le classi reali dell'host.
- **`src/Jobs/`** — `SpokiActivationJob` e `SpokiFinalActivationJob`, pronti all'uso: eseguono
  tutta la sequenza di chiamate Spoki. Non sono `final`: un progetto può estenderli per
  aggiungere un passo extra o personalizzare un singolo comportamento — vedi
  [`docs/come-estendere-job-e-viste.md`](docs/come-estendere-job-e-viste.md).
- **`src/Services/SpokiOnboardingChecker.php`** — controlla se l'onboarding è stato completato e
  accoda `SpokiFinalActivationJob`. L'host decide solo *quando* chiamarlo (al caricamento di una
  pagina, da un cron, da un endpoint AJAX).
- **`src/Controllers/DashboardController.php`** + **`src/views/dashboard/index.php`** +
  **`src/Assets/DashboardAsset.php`** (JS puro, nessuna dipendenza da jQuery/Bootstrap) —
  iframe **funzionante out-of-the-box**: autenticazione, cambio sezione senza reload, deep link.
  Utilizzabile così com'è o esteso/sovrascritto — vedi [Come si adatta ad app diverse](#come-si-adatta-ad-app-diverse).
- **`src/Models/SpokiAccount.php`** (+ `SpokiAccountQuery`, `SpokiAccountSearch`) e
  **`src/migrations/`** — un'implementazione **opzionale e pronta** di storage per
  `SpokiAccountRepositoryInterface`, con tabella generica (`owner_reference`, nessuna foreign
  key). Un progetto la usa se le fa comodo, oppure scrive il proprio adapter contro una propria
  tabella già esistente (vedi sotto) — il pacchetto funziona in entrambi i casi.

## Come si adatta ad app diverse

Il pacchetto non conosce `Order`, `User`, o qualunque altra classe di un progetto specifico.
Comunica solo tramite 4 interfacce (necessarie solo se usi il [Livello 3](#i-job-sono-obbligatori-tre-livelli-di-utilizzo)),
ognuna delle quali un host implementa con un proprio adapter, traducendo verso/da il proprio
dominio:

| Interfaccia | Cosa astrae | Se il tuo progetto non ne ha bisogno |
|---|---|---|
| `SpokiPurchaseGatewayInterface` | Trovare/chiudere l'acquisto collegato all'attivazione | Adapter no-op (nessun concetto di "acquisto") |
| `SpokiInvoicingInterface` | Emettere un documento di fatturazione | Adapter che ritorna sempre `true` senza fare nulla |
| `SpokiNotifierInterface` | Inviare una notifica (email o altro canale) | Adapter che non fa nulla |
| `SpokiAccountRepositoryInterface` | Salvare/leggere lo stato dell'account Spoki | Usa `SpokiAccount` del pacchetto (vedi sopra) invece di scriverne uno tuo |

### Riferimento: campi di `SpokiPurchaseContext` e `SpokiAccountState`

`SpokiPurchaseContext` (costruito dal tuo `SpokiPurchaseGatewayInterface`):

| Campo | Tipo | Note |
|---|---|---|
| `purchaseReference` | `string` | Lo stesso valore passato a `findPendingPurchase()` |
| `amountInCents` | `int` | Importo in centesimi (conversione in "millesimi" Spoki già gestita dal job) |
| `currency` | `string` | Codice ISO 4217 (es. `EUR`) |
| `metadata[SpokiPurchaseContext::METADATA_OWNER_REFERENCE]` | `string` | **Obbligatorio** — riferimento owner, es. id utente |
| `metadata[SpokiPurchaseContext::METADATA_EMAIL]` | `string` | **Obbligatorio** |
| `metadata[SpokiPurchaseContext::METADATA_FIRST_NAME]` | `string` | **Obbligatorio** |
| `metadata[SpokiPurchaseContext::METADATA_ACCOUNT_NAME]` | `string` | **Obbligatorio** |
| `metadata[SpokiPurchaseContext::METADATA_COUNTRY]` | `string` | Opzionale, default `'it'` |
| `metadata[SpokiPurchaseContext::METADATA_COUNTRY_CODE]` | `string` | Opzionale, default `'IT'` |
| `metadata['...']` (chiavi tue, libere) | `mixed` | Per dati che ti servono solo nel tuo adapter (es. l'id del tuo ordine) — il job del pacchetto le ignora |

`SpokiAccountState` (letto/scritto dal tuo `SpokiAccountRepositoryInterface`, immutabile —
usa `->with([...])` per un aggiornamento):

| Campo | Tipo | Impostato da |
|---|---|---|
| `ownerReference` | `string` | Te, alla creazione |
| `spokiAccountId` | `?int` | `SpokiActivationJob` (da `addSvClients`) |
| `apiKey` | `?string` | `SpokiActivationJob` (da `createApiKeyForAccount`) |
| `email` | `?string` | Te, alla creazione |
| `emailIframe` | `?string` | `SpokiFinalActivationJob` (da `addServiceUser`) |
| `onboardingUrl` | `?string` | `SpokiActivationJob` (da `onboarding`) |
| `privateKey` | `?string` | `SpokiFinalActivationJob` (da `generatePrivateKey`) |
| `status` | `int` | Uno tra `SpokiAccountState::STATUS_*`, aggiornato ad ogni fase |

### Esempio: adattare `SpokiActivationJob` al tuo dominio (progetto CON un proprio sistema ordini)

```php
// Nel tuo progetto — adapter verso il TUO modello Order/User esistente
class MySpokiPurchaseGateway implements SpokiPurchaseGatewayInterface
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

### Esempio: adapter no-op (progetto SENZA sistema ordini, es. attivazione gratuita)

```php
// Il tuo progetto non "vende" l'attivazione: non c'è nulla da trovare/chiudere.
class NullSpokiPurchaseGateway implements SpokiPurchaseGatewayInterface
{
    public function findPendingPurchase(string $purchaseReference): ?SpokiPurchaseContext
    {
        // $purchaseReference qui è un riferimento qualsiasi che decidi tu (es. l'id utente),
        // non serve un vero "acquisto": costruisci il contesto direttamente dai tuoi dati.
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

```php
// Nel bootstrap della tua app
Yii::$container->set(SpokiPurchaseGatewayInterface::class, MySpokiPurchaseGateway::class);
Yii::$container->set(SpokiInvoicingInterface::class, MySpokiInvoicing::class);
Yii::$container->set(SpokiNotifierInterface::class, MySpokiNotifier::class);
Yii::$container->set(SpokiAccountRepositoryInterface::class, MySpokiAccountRepository::class);
```

Fatto questo, `SpokiActivationJob`/`SpokiFinalActivationJob`/`SpokiOnboardingChecker` funzionano
senza scrivere altro codice — a meno che tu non voglia personalizzare qualcosa: ogni passo
interno dei job è un metodo `protected`, sovrascrivibile singolarmente. Per altre ricette
pratiche (aggiungere un passo, saltarne uno, estendere una vista, creare un job nuovo da zero)
vedi [`docs/come-estendere-job-e-viste.md`](docs/come-estendere-job-e-viste.md).

### Come si collegano i pezzi: il flusso completo, punto per punto

| Quando | Cosa fai | Cosa succede |
|---|---|---|
| Il pagamento/attivazione è confermato (nel tuo webhook, o dove lo gestisci tu) | `Yii::$app->queue->push(new SpokiActivationJob(['purchaseReference' => (string) $order->id]))` | Crea l'account su Spoki, genera l'onboarding, aggiorna lo stato tramite il tuo `SpokiAccountRepositoryInterface` |
| Vuoi controllare se il cliente ha completato l'onboarding (al caricamento di una pagina, da un cron, da un endpoint AJAX — decidi tu) | `Yii::$container->get(SpokiOnboardingChecker::class)->check($ownerReference)` | Se completato, accoda automaticamente `SpokiFinalActivationJob` |
| Il job finale è stato eseguito | (niente da fare) | L'account è `STATUS_ACTIVE`, pronto per l'iframe |
| Il cliente vuole vedere la dashboard | Registra/estendi `DashboardController` (vedi sotto) | Mostra l'iframe, già funzionante |

**Esempio concreto per il secondo punto** (dove nel tuo progetto oggi controlli lo stato,
es. in un controller che carica una pagina):

```php
use AlessandroHgo\Yii2Spoki\Services\SpokiOnboardingChecker;

public function actionIndex()
{
    Yii::$container->get(SpokiOnboardingChecker::class)->check((string) Yii::$app->user->id);
    // ... resto della tua action (mostra la pagina, ecc.)
}
```

Nota: `SpokiOnboardingChecker` non è registrato di default nel container — Yii2 lo istanzia
automaticamente risolvendo `SpokiService` e `SpokiAccountRepositoryInterface` dal costruttore
(dependency injection automatica), quindi non serve un `Yii::$container->set()` esplicito per
lui, basta che tu abbia già registrato `SpokiAccountRepositoryInterface`.

### Esempio: personalizzare un job (margini di profitto, un passo extra)

```php
class MySpokiActivationJob extends \AlessandroHgo\Yii2Spoki\Jobs\SpokiActivationJob
{
    protected function profitMargins(): array
    {
        return array_merge(parent::profitMargins(), ['conversation_profit_margin' => 40]);
    }

    protected function afterActivated(SpokiPurchaseContext $context, SpokiAccountState $state): void
    {
        Yii::info("Attivazione completata per {$context->purchaseReference}", __METHOD__);
    }
}
```

### Esempio: personalizzare/sostituire l'iframe

`DashboardController` è pensato per essere usato in 3 modi, a scelta:

1. **Così com'è**, registrandolo come controller nella tua app (nessuna riga di codice, solo
   configurazione).
2. **Stesso controller, tue viste**: imposta `viewPath` sulla tua cartella per un layout/tema diverso.
3. **Esteso**: crea una sottoclasse per aggiungere sezioni proprie o cambiare `resolveOwnerReference()`
   se il concetto di "utente corrente" nella tua app è diverso da `Yii::$app->user->id`.

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

## Esempio completo: attivare un account come Partner, chiamando l'SDK direttamente

Codice illustrativo (senza gestione errori per brevità — in produzione controlla sempre
`$result->success` dopo ogni chiamata, vedi [Gestione errori](#gestione-errori)). Corrisponde al
[Livello 1](#i-job-sono-obbligatori-tre-livelli-di-utilizzo): se usi
`SpokiActivationJob`/`SpokiFinalActivationJob` (Livello 3) questa sequenza è già fatta per te —
questo esempio serve a capire cosa succede sotto, o per un uso più diretto:

```php
use AlessandroHgo\Yii2Spoki\SpokiService;
use Yii;

/** @var SpokiService $spoki */
$spoki = Yii::$container->get(SpokiService::class); // usa la propria API key Partner

// 1. Crea l'account cliente su Spoki
$result = $spoki->addSvClients([[
    'email' => 'cliente@esempio.com',
    'first_name' => 'Mario',
    'account_name' => 'Azienda SRL',
    'country' => 'it',
    'country_code' => 'IT',
    'vat_amount' => 2200,
]]);
$spokiAccountId = $result->data[0]->id;

// 2. Genera l'API key dell'account (da qui in poi usala per le chiamate "a nome" dell'account)
$result = $spoki->createApiKeyForAccount(['account' => $spokiAccountId]);
$accountApiKey = $result->data->api_key;

// 3. Imposta i margini
$spoki->setProfits([
    'account_id' => $spokiAccountId,
    'conversation_profit_margin' => 40,
    // ...gli altri margini a 0 se non servono
]);

// 4. Genera il link di onboarding che il cliente deve completare
$result = $spoki->onboarding(['account' => $spokiAccountId]);
$onboardingUrl = $result->data->redirect_url; // mostralo/mandalo al cliente

// 5. (Opzionale) accredita subito un po' di credito
$spoki->createSubrecharge([
    'destination_account' => $spokiAccountId,
    'amount' => 5000, // "millesimi" — vedi la documentazione API Spoki per l'unità esatta
]);
```

**Dopo che il cliente ha completato l'onboarding** (webhook/notifica Spoki, gestita dal tuo
modulo interno, non da questo SDK):

```php
// 6. Trova il ruolo dell'account per email
$result = $spoki->getRoles('cliente@esempio.com', $accountApiKey);
$roleId = $result->data->results[0]->id;

// 7. Crea l'utente di servizio (necessario per l'iframe)
$result = $spoki->addServiceUser([
    'role' => 'Administrator',
    'email' => 'cliente@esempio.com',
    'name' => 'Mario Rossi',
], $accountApiKey);
$serviceUserId = $result->data->id;
$emailIframe = $result->data->user->email; // salvala, serve per l'iframe

// 8. Genera la private key finale — salvala, serve per l'iframe (vedi sezione sotto)
$result = $spoki->generatePrivateKey($serviceUserId, $accountApiKey);
$privateKey = $result->data->value;
```

## Esempio: attivare un account cliente diretto (senza Partner)

Se il tuo progetto gestisce un singolo account già esistente su Spoki (non aperto tramite un
account Partner), salti del tutto i passi 1-5 sopra — non ti servono, non sono utilizzabili né
necessari:

```php
// L'account esiste già: spoki_account_id e api_key te li ha dati Spoki direttamente
// (inseriti da un admin, o forniti dal cliente stesso).
$spokiAccountId = 12345;
$accountApiKey = 'la-api-key-fornita-da-spoki';

// Parti direttamente dal passo 6 dell'esempio sopra: getRoles → addServiceUser → generatePrivateKey
```

## Embedding via iframe: il flusso completo

Una volta fatti i passi 6-8 sopra (una sola volta per account), per mostrare l'iframe:

```php
use AlessandroHgo\Yii2Spoki\SpokiService;

/** @var SpokiService $spoki */
$result = $spoki->getAuthenticationToken($emailIframe, $privateKey, $accountApiKey);
$token = $result->data->token;
$uid = $result->data->uid;

$iframeUrl = "https://spoki.app/dashboard?auth_token={$token}&auth_uid={$uid}&language=it";
// stampa $iframeUrl in un tag <iframe src="...">
```

Sezioni valide al posto di `dashboard`: `chats`, `templates`, `automations`, `contacts`, `lists`,
`tags`. La chiamata a `getAuthenticationToken` va rifatta **a ogni caricamento** dell'iframe (il
token non è persistente); `generatePrivateKey` va fatto **una sola volta** per account.
`DashboardController::actionAuthToken()` espone già questa chiamata come endpoint AJAX pronto
(Livello 4).

## Due modalità d'uso: account Partner e cliente diretto

Spoki supporta due modelli, ed entrambi sono coperti dallo stesso `SpokiService`, senza alcuna
distinzione nel client:

| Modalità | Endpoint tipici | API key da passare |
|---|---|---|
| **Partner** (apri/gestisci account per conto di altri clienti) | `addSvClients`, `createApiKeyForAccount`, `setProfits`, `onboarding`, `createSubrecharge` | La propria API key Partner |
| **Cliente diretto** (un account Spoki già esistente) | `getRoles`, `addServiceUser`, `generatePrivateKey`, `getAccountSummary` | L'API key dell'account stesso (passata come parametro opzionale a ogni metodo) |

Il modulo interno del progetto decide quali metodi chiamare e in che ordine, in base al proprio
caso d'uso.

**Come funziona in pratica**: `SpokiService` ha una `apiKey` di default, configurata una volta
sola nel bootstrap (di solito la tua API key Partner — vedi
[Installazione](#installazione-in-unapp-yii2-sviluppo-locale-prima-della-pubblicazione)). Ogni
metodo accetta anche un **ultimo parametro opzionale** per usare un'API key diversa solo per
quella chiamata (quella dell'account cliente):

```php
// Usa l'API key di default (Partner) configurata nel bootstrap — nessun parametro extra
$spoki->addSvClients([...]);

// Usa l'API key DELL'ACCOUNT invece di quella Partner di default — ultimo parametro
$spoki->getRoles('cliente@esempio.com', $accountApiKey);
$spoki->addServiceUser([...], $accountApiKey);
$spoki->generatePrivateKey($roleId, $accountApiKey);
```

Non c'è una regola universale su quale API key serva per ogni endpoint: dipende da cosa stai
facendo (vedi la tabella sopra e l'esempio completo sotto). In caso di dubbio su un endpoint
specifico, verifica nella documentazione Spoki.

## Gestione errori

`SpokiService` non lancia mai eccezioni per errori HTTP o di validazione dell'API Spoki — ogni
metodo ritorna sempre:

```php
(object) [
    'success' => bool,   // true solo per risposte HTTP 2xx
    'status'  => int,    // status HTTP
    'message' => ?string, // messaggio d'errore leggibile (estratto da vari formati di risposta Spoki), null se success
    'data'    => mixed,  // stdClass o array di stdClass con il payload
]
```

```php
$result = $spokiService->onboarding(['account' => $spokiAccountId]);
if (!$result->success) {
    // $result->message contiene già un messaggio leggibile
    Yii::error("Errore onboarding: {$result->message}", __METHOD__);
    return false;
}
$onboardingUrl = $result->data->redirect_url;
```

Chi consuma l'SDK decide cosa fare in caso di errore (log, retry, eccezione propria) — l'SDK si
limita a segnalarlo in modo uniforme, senza interrompere il flusso con un'eccezione non gestita.
I job pronti (`SpokiActivationJob`/`SpokiFinalActivationJob`) seguono la stessa regola: un errore
su una chiamata ritorna `false` da `execute()` (loggato), senza lanciare eccezioni — il job può
essere ritentato dalla coda senza duplicare le chiamate già andate a buon fine (ogni passo
controlla se il dato è già presente prima di richiamare l'API).

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

## Sviluppo

```bash
composer install
vendor/bin/phpunit
```

## Installazione in un'app Yii2 (sviluppo locale, prima della pubblicazione)

Nell'app ospite, aggiungi un path repository che punta a questa cartella:

```json
"repositories": [
    { "type": "path", "url": "../yii2-spoki" }
],
"require": {
    "alessandrohgo/yii2-spoki": "@dev"
}
```

Configura `SpokiService` (baseUrl, apiKey) tramite il DI container, nel bootstrap dell'app:

```php
Yii::$container->set(\AlessandroHgo\Yii2Spoki\SpokiService::class, [
    'class' => \AlessandroHgo\Yii2Spoki\SpokiService::class,
    'baseUrl' => 'https://api.spoki.com/api/1',
    'apiKey' => getenv('SPOKI_API_KEY'),
]);
```

Questo basta per il [Livello 1](#i-job-sono-obbligatori-tre-livelli-di-utilizzo). Per il
Livello 3, registra anche i tuoi 4 adapter (vedi
[Come si adatta ad app diverse](#come-si-adatta-ad-app-diverse)):

```php
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface::class, MyPurchaseGateway::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiInvoicingInterface::class, MyInvoicing::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface::class, MyNotifier::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface::class, MyAccountRepository::class);
```

Se usi anche `SpokiAccount`/`SpokiAccountQuery`/`SpokiAccountSearch` del pacchetto (storage
pronto, invece di scrivere il tuo adapter contro una tua tabella — Livello 2), esegui la
migrazione (dal progetto ospite, puntando alla cartella del pacchetto):

```bash
yii migrate --migrationPath=@vendor/alessandrohgo/yii2-spoki/src/migrations
```

**Regola per le colonne di `spoki_account`**: la migrazione di questo pacchetto contiene solo
colonne che corrispondono a un dato realmente restituito da uno dei metodi di `SpokiService`
(es. `spoki_account_id` da `addSvClients`, `private_key` da `generatePrivateKey`), più il minimo
bookkeeping interno (`owner_reference`, `status`, timestamp). Se il tuo progetto ha bisogno di
altre colonne specifiche del tuo dominio, crea una migrazione **nel tuo modulo interno** che
estende o affianca questa tabella — non modificare la migrazione del pacchetto.

Se usi `DashboardController` (Livello 4), registralo nella configurazione della tua app:

```php
'controllerMap' => [
    'spoki-dashboard' => \AlessandroHgo\Yii2Spoki\Controllers\DashboardController::class,
],
```

## Installazione da GitHub (dopo la pubblicazione)

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/alessandrohgo/yii2-spoki" }
],
"require": {
    "alessandrohgo/yii2-spoki": "^1.0"
}
```

## Licenza

[MIT](LICENSE).
