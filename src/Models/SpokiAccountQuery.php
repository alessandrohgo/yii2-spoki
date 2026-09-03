<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Models;

/**
 * ActiveQuery per {@see SpokiAccount}.
 */
class SpokiAccountQuery extends \yii\db\ActiveQuery
{
    /**
     * Filtra per owner_reference.
     */
    public function byOwnerReference(string $ownerReference): SpokiAccountQuery
    {
        return $this->andWhere(['owner_reference' => $ownerReference]);
    }

    /**
     * Filtra per status.
     */
    public function byStatus(int $status): SpokiAccountQuery
    {
        return $this->andWhere(['status' => $status]);
    }

    /**
     * Filtra per account in onboarding pending.
     */
    public function onboardingPending(): SpokiAccountQuery
    {
        return $this->byStatus(SpokiAccount::STATUS_ONBOARDING_PENDING);
    }

    /**
     * Filtra per account attivi.
     */
    public function active(): SpokiAccountQuery
    {
        return $this->byStatus(SpokiAccount::STATUS_ACTIVE);
    }

    /**
     * Filtra per account in errore.
     */
    public function error(): SpokiAccountQuery
    {
        return $this->byStatus(SpokiAccount::STATUS_ERROR);
    }

    /**
     * {@inheritdoc}
     * @return SpokiAccount[]|array
     */
    public function all($db = null): array
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpokiAccount|array|null
     */
    public function one($db = null): SpokiAccount|array|null
    {
        return parent::one($db);
    }
}
