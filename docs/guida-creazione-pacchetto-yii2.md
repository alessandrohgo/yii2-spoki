# Guida personale — Come creare un pacchetto Yii2 riusabile

Guida generica, valida per `yii2-spoki` e per ogni futura estrazione (es. Fiskaly). Riassume il
percorso seguito passo per passo, inclusi gli errori incontrati e come risolverli.

## 1. Decidere se serve un'estensione Yii2 o un SDK PHP puro

| Caratteristica del codice da estrarre | Tipo di pacchetto | Esempio |
|---|---|---|
| Parla solo con un'API esterna (HTTP, niente rotte/controller/DI Yii2) | SDK PHP puro, nessuna dipendenza da `yiisoft/yii2` | `ventoh/active-campaign-sdk` |
| Registra moduli, controller, viste, migrazioni, si aggancia al DI container | Estensione Yii2 (`"type": "yii2-extension"`, dipende da `yiisoft/yii2`) | `alessandrohgo/yii2-spoki` |

## 2. Struttura minima del repository

```
nome-pacchetto/
├── composer.json
├── phpunit.xml
├── .gitignore
├── README.md              (documentazione per chi CONSUMA il pacchetto)
├── docs/                  (guide interne, come questa)
├── src/                   (PSR-4)
└── tests/                 (PHPUnit puro, non Codeception)
```

## 3. `composer.json` — le voci che servono per un'estensione Yii2

```json
{
    "name": "vendor/nome-pacchetto",
    "type": "yii2-extension",
    "repositories": [
        { "type": "composer", "url": "https://asset-packagist.org" }
    ],
    "require": {
        "php": "^8.1",
        "yiisoft/yii2": "^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0"
    },
    "config": {
        "allow-plugins": {
            "yiisoft/yii2-composer": true
        }
    },
    "autoload": {
        "psr-4": { "Vendor\\NomePacchetto\\": "src/" }
    }
}
```

**Due trappole in cui si cade sempre, incontrate creando `yii2-spoki`:**
1. `yiisoft/yii2` dipende da pacchetti `bower-asset/*` — senza il repository `asset-packagist.org`
   Composer fallisce con "could not find package bower-asset/jquery". Va sempre aggiunto.
2. Composer blocca di default il plugin `yiisoft/yii2-composer` per sicurezza
   ("contains a Composer plugin which is blocked by your allow-plugins config") — va autorizzato
   esplicitamente in `config.allow-plugins`, altrimenti l'installazione si ferma.

## 4. Test: PHPUnit puro, senza rete reale

Come `ventoh/active-campaign-sdk`: PHPUnit (non Codeception, legato all'app aziendale ospite), niente
mock quando possibile per gli SDK con sandbox reale; per un'estensione Yii2 come questa, i test
verificano che le classi si istanzino e si comportino correttamente **senza bisogno di un'app
Yii2 completa avviata** (niente `Yii::$app`), così restano veloci e non richiedono setup.

Comando di verifica (in un container isolato, senza toccare l'ambiente Lando dell'app ospite):
```bash
docker run --rm -v $(pwd):/app -w /app composer:2 composer install --no-interaction
docker run --rm -v $(pwd):/app -w /app php:8.3-cli vendor/bin/phpunit
```

## 5. Ciclo di sviluppo: prima locale, poi pubblicato

**Fase A — sviluppo locale (path repository), nessuna pubblicazione**
Nell'app che consuma il pacchetto:
```json
"repositories": [{ "type": "path", "url": "../nome-pacchetto" }],
"require": { "vendor/nome-pacchetto": "@dev" }
```
Ogni modifica al pacchetto è immediatamente visibile nell'app ospite (symlink), senza commit/push.

**Fase B — pubblicazione su GitHub (repo privato)**
1. Crea il repository su github.com (privato, sotto il proprio account personale).
2. `git remote add origin git@github.com:<utente>/<nome-pacchetto>.git && git push -u origin master`
3. Tagga una versione: `git tag v0.1.0 && git push --tags`
4. Autenticazione Composer per repo privati — **una tantum, a livello globale** (non per singolo
   progetto, per non rischiare di committare token):
   ```bash
   composer config --global github-oauth.github.com <personal-access-token>
   ```
5. Nell'app ospite, sostituisci il `path` repository con:
   ```json
   "repositories": [{ "type": "vcs", "url": "https://github.com/<utente>/<nome-pacchetto>" }],
   "require": { "vendor/nome-pacchetto": "^0.1" }
   ```

## 6. Documentazione da mantenere

- **README.md del pacchetto**: come lo installa e usa un consumatore (esempi di codice,
  configurazione minima, come registrare il modulo).
- **Questa guida** (`docs/guida-creazione-pacchetto-yii2.md`): il "come si fa", riusabile per il
  prossimo pacchetto — va aggiornata ogni volta che si scopre una nuova trappola o convenzione.

## 7. Progettare interfacce domain-neutral, non "come le chiama la mia app"

Quando si disaccoppia un modulo tramite interfacce (Dependency Inversion), è facile copiare nel
nome dell'interfaccia il vocabolario dell'app da cui si estrae il codice — es. `OrderGateway`,
`closeOrder()` — perché è lì che quella logica vive oggi. È un errore: un'altra app che consuma il
modulo potrebbe non avere affatto quel concetto (niente "ordini"), gestirlo con un sistema esterno
diverso, o innescare l'azione in un momento diverso del flusso (es. pagamento richiesto **dopo**
un'attivazione invece che prima).

Il meccanismo a interfacce/DI risolve già da solo "sistema diverso" o "azione non necessaria": chi
consuma il modulo scrive semplicemente un adapter no-op per l'interfaccia che non gli serve. Quello
che *non* risolve da solo è il naming: un metodo chiamato `closeOrder()` costringe chiunque lo
implementi a ragionare in termini di "ordine" anche se il suo dominio non ne ha uno.

**Regola pratica**: quando si nomina un'interfaccia o un metodo di un modulo condiviso, chiedersi
"questo nome ha senso per un'app che non assomiglia affatto a quella da cui sto estraendo il
codice?". Se la risposta è no, usare un termine più neutro (es. `Purchase` invece di `Order`,
`markFulfilled()` invece di `closeOrder()`) che descriva **cosa il modulo chiede**, non **come
l'app di origine chiama le cose**.

## 8. Materiale privato di pianificazione: nel repository, ma mai pubblicato

I documenti di pianificazione interni (piani di refactoring, note su come un'app aziendale usa
oggi il modulo, nomi di classi/tabelle specifici di quell'app) sono utili come riferimento locale
mentre si lavora, ma non vanno mai pubblicati: rivelerebbero dettagli interni dell'azienda e
non hanno valore per chi installa il pacchetto. Vanno tenuti in una cartella dedicata e
**gitignorata** (qui `docs/private/`), mai nella cartella `docs/` tracciata. Prima di scrivere
qualunque contenuto nei file tracciati (`README.md`, `docs/*.md` pubblici, docblock nel codice),
verificare che non contengano il nome dell'app aziendale di origine o altri dettagli interni.
