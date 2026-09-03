<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki;

use Yii;
use yii\base\Module;

/**
 * Modulo Yii2 per l'integrazione con Spoki (attivazione account WhatsApp Light, onboarding,
 * iframe, ricariche).
 *
 * L'app ospite registra il modulo nella propria configurazione, impostando `viewPath` e
 * `controllerNamespace` secondo le proprie esigenze (es. viste diverse per un'app frontend e
 * una backend) — il modulo non indovina nulla in base all'id dell'applicazione:
 *
 * ```php
 * 'modules' => [
 *     'spoki' => [
 *         'class' => \AlessandroHgo\Yii2Spoki\SpokiModule::class,
 *         'viewPath' => '@app/modules/spoki/views',
 *         'controllerNamespace' => 'app\modules\spoki\controllers',
 *     ],
 * ],
 * ```
 *
 * La configurazione di {@see SpokiService} (baseUrl, apiKey) è responsabilità dell'app ospite,
 * tramite il proprio binding DI — vedi il docblock di SpokiService per un esempio.
 */
class SpokiModule extends Module
{
    public function init(): void
    {
        parent::init();

        if (!Yii::$container->has(SpokiService::class)) {
            Yii::$container->set(SpokiService::class, SpokiService::class);
        }
    }
}
