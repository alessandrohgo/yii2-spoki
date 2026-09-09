<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Contracts;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiInvoicingInterface;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use PHPUnit\Framework\TestCase;

class SpokiInvoicingInterfaceTest extends TestCase
{
    public function testAdapterReceivesPurchaseContextAndEnqueuesInvoice(): void
    {
        $invoicing = new class implements SpokiInvoicingInterface {
            public ?SpokiPurchaseContext $received = null;

            public function enqueueInvoice(SpokiPurchaseContext $context): bool
            {
                $this->received = $context;

                return true;
            }
        };

        $context = new SpokiPurchaseContext('purchase-2', 2500, 'EUR');
        $this->assertTrue($invoicing->enqueueInvoice($context));
        $this->assertSame($context, $invoicing->received);
    }
}
