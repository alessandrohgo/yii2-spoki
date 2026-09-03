<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Contracts;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiPurchaseGatewayInterface;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use PHPUnit\Framework\TestCase;

class SpokiPurchaseGatewayInterfaceTest extends TestCase
{
    /**
     * Verifica che un adapter minimo (anche "no-op", per un host senza concetto di acquisto)
     * possa implementare il contratto senza alcuna dipendenza da classi del modulo diverse
     * dall'interfaccia stessa e dal value object.
     */
    public function testNoOpAdapterSatisfiesTheContract(): void
    {
        $gateway = new class implements SpokiPurchaseGatewayInterface {
            public function findPendingPurchase(string $purchaseReference): ?SpokiPurchaseContext
            {
                return null;
            }

            public function markFulfilled(SpokiPurchaseContext $context): bool
            {
                return true;
            }
        };

        $this->assertNull($gateway->findPendingPurchase('any-reference'));

        $context = new SpokiPurchaseContext('purchase-1', 1000, 'EUR');
        $this->assertTrue($gateway->markFulfilled($context));
    }
}
