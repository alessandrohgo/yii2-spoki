<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Contracts;

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;

/**
 * Contratto verso lo storage dell'account Spoki. Il modulo non presuppone alcuno schema di
 * tabella: l'app ospite può appoggiarsi a una propria tabella con qualunque struttura (es. con
 * una foreign key verso una tabella "user" specifica), traducendola da/verso
 * {@see SpokiAccountState} in questo adapter.
 */
interface SpokiAccountRepositoryInterface
{
    /**
     * Cerca lo stato dell'account per il riferimento owner indicato.
     *
     * @param string $ownerReference Riferimento opaco fornito dall'app ospite.
     * @return SpokiAccountState|null Null se non esiste ancora alcun account per questo owner.
     */
    public function findByOwnerReference(string $ownerReference): ?SpokiAccountState;

    /**
     * Salva lo stato dell'account (crea se non esiste, aggiorna altrimenti).
     *
     * @param SpokiAccountState $state Lo stato da salvare.
     * @return SpokiAccountState Lo stato effettivamente salvato (es. con un id assegnato).
     */
    public function save(SpokiAccountState $state): SpokiAccountState;
}
