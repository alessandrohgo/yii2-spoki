<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\ValueObjects;

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;
use PHPUnit\Framework\TestCase;

class SpokiPurchaseContextTest extends TestCase
{
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

    public function testMetadataDefaultsToEmptyArray(): void
    {
        $context = new SpokiPurchaseContext(
            purchaseReference: 'purchase-456',
            amountInCents: 500,
            currency: 'EUR',
        );

        $this->assertSame([], $context->metadata);
    }

    public function testOwnerReferenceReadsFromMetadata(): void
    {
        $context = new SpokiPurchaseContext(
            purchaseReference: 'purchase-789',
            amountInCents: 500,
            currency: 'EUR',
            metadata: [SpokiPurchaseContext::METADATA_OWNER_REFERENCE => '42'],
        );

        $this->assertSame('42', $context->ownerReference());
    }

    public function testOwnerReferenceIsEmptyStringWhenMissing(): void
    {
        $context = new SpokiPurchaseContext(purchaseReference: 'x', amountInCents: 0, currency: 'EUR');

        $this->assertSame('', $context->ownerReference());
    }
}
