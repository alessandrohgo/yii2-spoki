<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Models;

use AlessandroHgo\Yii2Spoki\Models\SpokiAccount;
use AlessandroHgo\Yii2Spoki\Models\SpokiAccountQuery;
use PHPUnit\Framework\TestCase;

/**
 * Verifica solo la condizione WHERE costruita da ciascun metodo di filtro — costruire una
 * condizione non richiede una connessione al database, eseguirla sì (non disponibile qui).
 */
class SpokiAccountQueryTest extends TestCase
{
    public function testByOwnerReferenceBuildsExpectedCondition(): void
    {
        $query = new SpokiAccountQuery(SpokiAccount::class);

        $query->byOwnerReference('owner-123');

        $this->assertSame(['owner_reference' => 'owner-123'], $query->where);
    }

    public function testByStatusBuildsExpectedCondition(): void
    {
        $query = new SpokiAccountQuery(SpokiAccount::class);

        $query->byStatus(SpokiAccount::STATUS_ACTIVE);

        $this->assertSame(['status' => SpokiAccount::STATUS_ACTIVE], $query->where);
    }

    public function testOnboardingPendingFiltersByOnboardingPendingStatus(): void
    {
        $query = new SpokiAccountQuery(SpokiAccount::class);

        $query->onboardingPending();

        $this->assertSame(['status' => SpokiAccount::STATUS_ONBOARDING_PENDING], $query->where);
    }

    public function testActiveFiltersByActiveStatus(): void
    {
        $query = new SpokiAccountQuery(SpokiAccount::class);

        $query->active();

        $this->assertSame(['status' => SpokiAccount::STATUS_ACTIVE], $query->where);
    }

    public function testErrorFiltersByErrorStatus(): void
    {
        $query = new SpokiAccountQuery(SpokiAccount::class);

        $query->error();

        $this->assertSame(['status' => SpokiAccount::STATUS_ERROR], $query->where);
    }

    /**
     * Verifica che due filtri incatenati vengano combinati in AND, non che l'ultimo
     * sovrascriva il primo.
     */
    public function testChainedFiltersAreCombinedWithAnd(): void
    {
        $query = new SpokiAccountQuery(SpokiAccount::class);

        $query->byOwnerReference('owner-123')->byStatus(SpokiAccount::STATUS_ACTIVE);

        $this->assertSame(
            ['and', ['owner_reference' => 'owner-123'], ['status' => SpokiAccount::STATUS_ACTIVE]],
            $query->where
        );
    }
}
