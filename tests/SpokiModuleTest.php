<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests;

use AlessandroHgo\Yii2Spoki\SpokiModule;
use AlessandroHgo\Yii2Spoki\SpokiService;
use PHPUnit\Framework\TestCase;
use Yii;
use yii\di\Container;

class SpokiModuleTest extends TestCase
{
    /**
     * Il container DI di Yii2 è uno stato globale statico: senza un reset esplicito, un
     * binding registrato in un test resterebbe visibile ai test successivi.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$container = new Container();
    }

    /**
     * Verifica che il modulo registri un binding di default per SpokiService, così l'app
     * ospite può usare Yii::$container->get(SpokiService::class) da subito.
     */
    public function testInitRegistersDefaultSpokiServiceBindingWhenNoneExists(): void
    {
        $this->assertFalse(Yii::$container->has(SpokiService::class));

        new SpokiModule('spoki');

        $this->assertTrue(Yii::$container->has(SpokiService::class));
        $this->assertInstanceOf(SpokiService::class, Yii::$container->get(SpokiService::class));
    }

    /**
     * Verifica che il modulo non sovrascriva un binding già registrato dall'app ospite
     * (es. con la propria baseUrl/apiKey configurate) — il modulo fornisce solo un default,
     * non impone la propria configurazione.
     */
    public function testInitDoesNotOverrideAnExistingSpokiServiceBinding(): void
    {
        Yii::$container->set(SpokiService::class, ['class' => SpokiService::class, 'baseUrl' => 'https://custom.example']);

        new SpokiModule('spoki');

        $service = Yii::$container->get(SpokiService::class);
        $this->assertSame('https://custom.example', $service->baseUrl);
    }
}
