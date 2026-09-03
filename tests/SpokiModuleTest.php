<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests;

use AlessandroHgo\Yii2Spoki\SpokiModule;
use PHPUnit\Framework\TestCase;

class SpokiModuleTest extends TestCase
{
    /**
     * Verifica che il modulo si istanzi correttamente e restituisca la versione attesa,
     * a conferma che l'autoload PSR-4 e la dipendenza da yiisoft/yii2 funzionino.
     */
    public function testVersionReturnsExpectedValue(): void
    {
        $module = new SpokiModule('spoki');

        $this->assertSame('0.1.0', $module->version());
    }
}
