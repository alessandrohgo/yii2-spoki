# alessandrohgo/yii2-spoki

Estensione Yii2 per l'integrazione con [Spoki](https://spoki.app) (attivazione account WhatsApp
Light, onboarding, iframe, ricariche).

> **Stato**: scheletro di pratica. La logica reale del modulo (client API, contracts per
> ordini/fatturazione/notifiche, adapter) sarà portata qui una volta completato il
> disaccoppiamento dal progetto Yoti. Vedi `docs/private/spoki-modulo-riusabile-implementation-plan.md`
> nel repository Yoti per il piano completo.

## Perché un'estensione Yii2 e non un SDK PHP puro

Il modulo Spoki non è solo un client HTTP: registra rotte, controller, viste, migrazioni e si
integra nel DI container dell'applicazione ospite. Per questo il pacchetto usa
`"type": "yii2-extension"` e dipende da `yiisoft/yii2`, a differenza di un SDK generico
(es. `ventoh/active-campaign-sdk`) che non ha alcuna dipendenza dal framework.

## Come si adatta ad app diverse

Ogni applicazione ospite ha un proprio modo di gestire ordini, pagamenti e fatturazione (Yoti ha
`Order`/`AccountPayment`/`Message`, un'altra app potrebbe non avere il concetto di "ordine"
affatto). Il modulo non dipende mai direttamente da queste classi: espone delle interfacce
(contracts) che l'app ospite implementa con i propri adapter e registra nel DI container di
Yii2. Il modulo chiede "dammi qualcosa che sa rispondere a queste domande", non "dammi la classe
Order".

## Sviluppo

```bash
composer install
vendor/bin/phpunit
```

## Installazione in un'app Yii2 (sviluppo locale, prima della pubblicazione)

Nell'app ospite (es. Yoti), aggiungi un path repository che punta a questa cartella:

```json
"repositories": [
    { "type": "path", "url": "../yii2-spoki" }
],
"require": {
    "alessandrohgo/yii2-spoki": "@dev"
}
```

Poi registra il modulo in `common/config/main.php` (o nella config dell'app):

```php
'modules' => [
    'spoki' => [
        'class' => \AlessandroHgo\Yii2Spoki\SpokiModule::class,
    ],
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
