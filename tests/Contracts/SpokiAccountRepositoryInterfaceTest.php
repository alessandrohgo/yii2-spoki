<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Contracts;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use PHPUnit\Framework\TestCase;

class SpokiAccountRepositoryInterfaceTest extends TestCase
{
    /**
     * Verifica che un adapter in memoria (senza alcuna tabella reale) soddisfi il contratto:
     * dimostra che un host può implementarlo appoggiandosi a qualunque storage, non solo un
     * ActiveRecord Yii2.
     */
    public function testInMemoryAdapterSatisfiesTheContract(): void
    {
        $repository = new class implements SpokiAccountRepositoryInterface {
            /** @var array<string, SpokiAccountState> */
            private array $states = [];

            public function findByOwnerReference(string $ownerReference): ?SpokiAccountState
            {
                return $this->states[$ownerReference] ?? null;
            }

            public function save(SpokiAccountState $state): SpokiAccountState
            {
                $this->states[$state->ownerReference] = $state;

                return $state;
            }
        };

        $this->assertNull($repository->findByOwnerReference('owner-1'));

        $saved = $repository->save(new SpokiAccountState(ownerReference: 'owner-1', spokiAccountId: 42));

        $this->assertSame(42, $repository->findByOwnerReference('owner-1')?->spokiAccountId);
        $this->assertSame($saved, $repository->findByOwnerReference('owner-1'));
    }
}
