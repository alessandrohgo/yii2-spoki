<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Tests\Models;

use AlessandroHgo\Yii2Spoki\Models\SpokiAccountSearch;
use PHPUnit\Framework\TestCase;
use yii\base\Model;

/**
 * search() istanzia un ActiveDataProvider, che richiede una connessione al database già in
 * fase di costruzione (anche con parametri vuoti) — non testabile in questo repository
 * standalone. Qui si verifica solo la logica pura: regole di validazione e scenari.
 */
class SpokiAccountSearchTest extends TestCase
{
    /**
     * Verifica che i campi di ricerca siano tutti "safe" o validati come interi, senza
     * più alcun riferimento a "user_id" (rimosso da SpokiAccount, vedi SpokiAccountTest).
     */
    public function testRulesIncludeOwnerReferenceAsSafe(): void
    {
        $model = new SpokiAccountSearch();

        $rulesAsString = json_encode($model->rules());

        $this->assertStringContainsString('owner_reference', $rulesAsString);
    }

    /**
     * Verifica che scenarios() esponga un solo scenario "default" con tutti gli attributi
     * attivi (nessuno scenario specifico che escluderebbe attributi dalla ricerca).
     */
    public function testScenariosExposeAllActiveAttributesInDefaultScenario(): void
    {
        $model = new SpokiAccountSearch();

        $this->assertSame([Model::SCENARIO_DEFAULT => $model->activeAttributes()], $model->scenarios());
    }
}
