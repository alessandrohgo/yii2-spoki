<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Models;

use AlessandroHgo\Yii2Spoki\Models\SpokiAccount;
use PHPUnit\Framework\TestCase;

/**
 * Copre solo la logica che non richiede una connessione al database (nessun DB disponibile
 * in questo repository standalone). Il comportamento a runtime (query, unique constraint,
 * foreign key) va verificato dopo l'installazione in un'app Yii2 reale.
 */
class SpokiAccountTest extends TestCase
{
    public function testTableNameIsSpokiAccount(): void
    {
        $this->assertSame('{{%spoki_account}}', SpokiAccount::tableName());
    }

    /**
     * Verifica che statusList() copra esattamente le 4 costanti di stato definite, senza
     * duplicati o valori dimenticati.
     */
    public function testStatusListCoversAllStatusConstants(): void
    {
        $expectedStatuses = [
            SpokiAccount::STATUS_PENDING_REQUEST,
            SpokiAccount::STATUS_ONBOARDING_PENDING,
            SpokiAccount::STATUS_ACTIVE,
            SpokiAccount::STATUS_ERROR,
        ];

        $this->assertSame($expectedStatuses, array_keys(SpokiAccount::statusList()));
    }

    /**
     * Verifica che le regole di validazione richiedano owner_reference (il riferimento
     * opaco all'app ospite) e non contengano più alcun riferimento a "user_id" o a una
     * classe User specifica di un host.
     */
    public function testRulesRequireOwnerReferenceAndHaveNoUserCoupling(): void
    {
        $account = new SpokiAccount();

        $rulesAsString = json_encode($account->rules());

        $this->assertStringContainsString('owner_reference', $rulesAsString);
        $this->assertStringNotContainsString('user_id', $rulesAsString);
        $this->assertStringNotContainsString('User', $rulesAsString);
    }

    /**
     * Verifica che le etichette degli attributi coprano owner_reference e non contengano
     * più "User ID", coerentemente con la rimozione della dipendenza da una classe User.
     */
    public function testAttributeLabelsUseOwnerReferenceNotUserId(): void
    {
        $labels = (new SpokiAccount())->attributeLabels();

        $this->assertArrayHasKey('owner_reference', $labels);
        $this->assertArrayNotHasKey('user_id', $labels);
    }
}
