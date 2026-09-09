<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\ValueObjects;

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use PHPUnit\Framework\TestCase;

class SpokiAccountStateTest extends TestCase
{
    public function testConstructorDefaultsToPendingRequestStatus(): void
    {
        $state = new SpokiAccountState(ownerReference: 'owner-1');

        $this->assertSame(SpokiAccountState::STATUS_PENDING_REQUEST, $state->status);
        $this->assertNull($state->spokiAccountId);
    }

    /**
     * Verifica che with() produca una NUOVA istanza (immutabilità) invece di mutare l'originale.
     */
    public function testWithReturnsNewInstanceWithoutMutatingOriginal(): void
    {
        $original = new SpokiAccountState(ownerReference: 'owner-1', status: SpokiAccountState::STATUS_PENDING_REQUEST);

        $updated = $original->with(['spokiAccountId' => 99, 'status' => SpokiAccountState::STATUS_ACTIVE]);

        $this->assertNotSame($original, $updated);
        $this->assertSame(SpokiAccountState::STATUS_PENDING_REQUEST, $original->status);
        $this->assertNull($original->spokiAccountId);
        $this->assertSame(SpokiAccountState::STATUS_ACTIVE, $updated->status);
        $this->assertSame(99, $updated->spokiAccountId);
    }

    /**
     * Verifica che i campi non passati a with() vengano preservati dall'istanza originale.
     */
    public function testWithPreservesUnchangedFields(): void
    {
        $original = new SpokiAccountState(ownerReference: 'owner-1', email: 'test@example.com', apiKey: 'key-123');

        $updated = $original->with(['status' => SpokiAccountState::STATUS_ERROR]);

        $this->assertSame('owner-1', $updated->ownerReference);
        $this->assertSame('test@example.com', $updated->email);
        $this->assertSame('key-123', $updated->apiKey);
    }
}
