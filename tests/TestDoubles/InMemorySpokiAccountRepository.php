<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\TestDoubles;

use AlessandroHgo\Yii2Spoki\Contracts\SpokiAccountRepositoryInterface;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;

/**
 * Adapter in memoria di SpokiAccountRepositoryInterface, per i test: dimostra e verifica il
 * comportamento dei job senza alcun database reale.
 */
final class InMemorySpokiAccountRepository implements SpokiAccountRepositoryInterface
{
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
}
