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
