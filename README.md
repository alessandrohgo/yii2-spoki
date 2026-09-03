# alessandrohgo/yii2-spoki

Estensione Yii2 per l'integrazione con [Spoki](https://spoki.app) (attivazione account WhatsApp
Light, onboarding, iframe, ricariche).

> **Stato**: scheletro di pratica. La logica reale del modulo (client API, contracts per
> ordini/fatturazione/notifiche, adapter) sarà portata qui una volta completato il
> disaccoppiamento dall'applicazione aziendale che lo usa oggi. Vedi
> `docs/private/spoki-modulo-riusabile-implementation-plan.md` (cartella privata, non pubblicata)
> per il piano completo.

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
