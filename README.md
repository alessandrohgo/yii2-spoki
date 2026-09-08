# alessandrohgo/yii2-spoki

SDK per Yii2 per l'integrazione con [Spoki](https://spoki.app) (attivazione account Spoki,
onboarding, iframe, ricariche): tutte le chiamate API con gestione errori, più un
modello/tabella generico per salvare gli account. **Nessuna logica di business**: come e quando
chiamare gli endpoint, quali dati usare, come collegarli al proprio flusso applicativo (ordini,
pagamenti, fatturazione, email) è responsabilità del modulo interno di ogni progetto che lo
installa.

## Cosa contiene

- **`SpokiService`** — client per tutti gli endpoint API Spoki (partner e account diretto).
  Ogni metodo ritorna sempre un oggetto uniforme: `{ success, status, message, data }`. Nessuna
  eccezione lanciata per errori HTTP/API — vedi [Gestione errori](#gestione-errori).
- **`SpokiAccount`** (+ `SpokiAccountQuery`, `SpokiAccountSearch`) — ActiveRecord generico per
  salvare gli account Spoki collegati alla propria app. Usa `owner_reference`, un riferimento
  opaco deciso da chi installa il pacchetto (non presuppone una tabella "user" specifica).
- **Migrazione** — crea la tabella `spoki_account`.

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

## Embedding via iframe: il flusso completo

1. `generatePrivateKey($roleId)` → salva il valore restituito come `private_key` sull'account.
2. Quando serve mostrare l'iframe, `getAuthenticationToken($email, $privateKey)` → restituisce
   `{ token, uid }`.
3. Costruisci l'URL dell'iframe:
   ```
   https://spoki.app/{pagina}?auth_token={token}&auth_uid={uid}&language={it|en}
   ```
   dove `{pagina}` è una sezione valida (es. `dashboard`, `chats`, `templates`, `automations`,
   `contacts`, `lists`, `tags`).

Il passo 2 va rifatto a ogni caricamento dell'iframe (il token non è persistente); il passo 1 va
fatto una sola volta per account.

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

## Installazione da GitHub (dopo la pubblicazione)

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/alessandrohgo/yii2-spoki" }
],
"require": {
    "alessandrohgo/yii2-spoki": "^1.0"
}
```
