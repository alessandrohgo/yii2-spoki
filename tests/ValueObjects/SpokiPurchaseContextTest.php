<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\ValueObjects;

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use PHPUnit\Framework\TestCase;

class SpokiPurchaseContextTest extends TestCase
{
    /**
     * Verifica che tutti i campi passati al costruttore siano accessibili come proprietà readonly.
     */
    public function testConstructorStoresAllFields(): void
    {
        $context = new SpokiPurchaseContext(
            purchaseReference: 'purchase-123',
            amountInCents: 1999,
            currency: 'EUR',
            metadata: ['planCode' => 'light'],
        );

        $this->assertSame('purchase-123', $context->purchaseReference);
        $this->assertSame(1999, $context->amountInCents);
        $this->assertSame('EUR', $context->currency);
        $this->assertSame(['planCode' => 'light'], $context->metadata);
    }

    /**
     * Verifica che metadata sia opzionale e valga un array vuoto di default, per gli host che
     * non hanno dati aggiuntivi da allegare.
     */
    public function testMetadataDefaultsToEmptyArray(): void
    {
        $context = new SpokiPurchaseContext(
            purchaseReference: 'purchase-456',
            amountInCents: 500,
            currency: 'EUR',
        );

        $this->assertSame([], $context->metadata);
    }
}
