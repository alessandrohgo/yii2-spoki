# alessandrohgo/yii2-spoki

Estensione Yii2 per l'integrazione con [Spoki](https://spoki.app) (attivazione account WhatsApp
Light, onboarding, iframe, ricariche).

> **Stato**: in costruzione. Presenti: client API (`SpokiService`), modello account
> (`SpokiAccount`), contracts, e i job di attivazione. Mancano ancora i controller/viste per
> onboarding, dashboard e iframe.

## Perché un'estensione Yii2 e non un SDK PHP puro

Il modulo Spoki non è solo un client HTTP: registra rotte, controller, viste, migrazioni e si
integra nel DI container dell'applicazione ospite. Per questo il pacchetto usa
`"type": "yii2-extension"` e dipende da `yiisoft/yii2`, a differenza di un SDK generico
(es. `ventoh/active-campaign-sdk`) che non ha alcuna dipendenza dal framework.

## Come si adatta ad app diverse

Ogni applicazione ospite ha un proprio modo di gestire acquisti, pagamenti e fatturazione — una
può avere ordini/`Order`, un'altra potrebbe non avere questo concetto affatto, o richiedere il
pagamento dopo l'attivazione invece che prima. Il modulo non dipende mai direttamente da classi
specifiche di un'app: espone delle interfacce (contracts), con nomi volutamente neutri
(es. `SpokiPurchaseGatewayInterface`, non "Order"), che l'app ospite implementa con i propri
adapter e registra nel DI container di Yii2. Un'app che non ha bisogno di una determinata
interfaccia (es. nessuna fatturazione automatica) scrive semplicemente un adapter no-op. Il
modulo chiede "dammi qualcosa che sa rispondere a queste domande", mai "dammi la tua classe X".

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

Poi registra il modulo nella configurazione dell'app, impostando esplicitamente `viewPath` e
`controllerNamespace` (il modulo non li indovina in base all'id dell'applicazione):

```php
'modules' => [
    'spoki' => [
        'class' => \AlessandroHgo\Yii2Spoki\SpokiModule::class,
        'viewPath' => '@app/modules/spoki/views',
        'controllerNamespace' => 'app\modules\spoki\controllers',
    ],
],
```

Configura `SpokiService` (baseUrl, apiKey) tramite il DI container, nel bootstrap dell'app:

```php
Yii::$container->set(\AlessandroHgo\Yii2Spoki\SpokiService::class, [
    'class' => \AlessandroHgo\Yii2Spoki\SpokiService::class,
    'baseUrl' => 'https://api.spoki.com/api/1',
    'apiKey' => getenv('SPOKI_API_KEY'),
]);
```

Infine registra i tuoi 3 adapter per le interfacce del modulo (vedi sezione precedente):

```php
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface::class, MyPurchaseGateway::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiInvoicingInterface::class, MyInvoicing::class);
Yii::$container->set(\AlessandroHgo\Yii2Spoki\Contracts\SpokiNotifierInterface::class, MyNotifier::class);
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
