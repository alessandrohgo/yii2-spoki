# alessandrohgo/yii2-spoki

SDK per Yii2 per l'integrazione con [Spoki](https://spoki.app) (attivazione account Spoki,
onboarding, iframe, ricariche): tutte le chiamate API con gestione errori, più un
modello/tabella generico per salvare gli account. **Nessuna logica di business**: come e quando
chiamare gli endpoint, quali dati usare, come collegarli al proprio flusso applicativo (ordini,
pagamenti, fatturazione, email) è responsabilità del modulo interno di ogni progetto che lo
installa.

## Cosa contiene

- **[`src/SpokiService.php`](src/SpokiService.php)** — **un solo file**, con tutti i 12 metodi
  (una chiamata API ciascuno). Non è diviso in più classi: apri quel file e trovi tutto.
  Ogni metodo ritorna sempre un oggetto uniforme: `{ success, status, message, data }`. Nessuna
  eccezione lanciata per errori HTTP/API — vedi [Gestione errori](#gestione-errori).
- **`src/Models/SpokiAccount.php`** (+ `SpokiAccountQuery`, `SpokiAccountSearch`) — ActiveRecord
  generico per salvare gli account Spoki collegati alla propria app. Usa `owner_reference`, un
  riferimento opaco deciso da chi installa il pacchetto (non presuppone una tabella "user"
  specifica).
- **`src/migrations/`** — crea la tabella `spoki_account`.

## Metodi disponibili

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
| `getAuthenticationToken` | `POST /auth/get_authentication_token/` | Genera il token per l'iframe (vedi sotto) |
| `updatePartnerRole` | `POST /partner-roles/{id}/update_role/` | Aggiorna un partner role — **corpo della richiesta non documentato con certezza**, verificare prima dell'uso in produzione |

## Esempio completo: attivare un account come Partner

Codice illustrativo (senza gestione errori per brevità — in produzione controlla sempre
`$result->success` dopo ogni chiamata, vedi [Gestione errori](#gestione-errori)):

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

## Cosa NON contiene (di proposito)

Nessun job, controller, vista, o interfaccia di disaccoppiamento: quella è logica specifica di
ogni progetto (es. "attiva l'account dopo che un ordine è stato pagato", "manda un'email quando
l'attivazione è completata") e va scritta nel modulo interno del progetto che usa questo SDK,
non qui.

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

Esegui la migrazione (dal progetto ospite, puntando alla cartella del pacchetto):

```bash
yii migrate --migrationPath=@vendor/alessandrohgo/yii2-spoki/src/migrations
```

Poi, nel modulo interno del tuo progetto, usa `SpokiService` e `SpokiAccount` per costruire la
tua logica specifica (job, controller, viste).

**Regola per le colonne di `spoki_account`**: la migrazione di questo pacchetto contiene solo
colonne che corrispondono a un dato realmente restituito da uno dei metodi di `SpokiService`
(es. `spoki_account_id` da `addSvClients`, `private_key` da `generatePrivateKey`), più il minimo
bookkeeping interno (`owner_reference`, `status`, timestamp). Se il tuo progetto ha bisogno di
altre colonne specifiche del tuo dominio, crea una migrazione **nel tuo modulo interno** che
estende o affianca questa tabella — non modificare la migrazione del pacchetto.

## Installazione da GitHub (dopo la pubblicazione)

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/alessandrohgo/yii2-spoki" }
],
"require": {
    "alessandrohgo/yii2-spoki": "^1.0"
}
```
