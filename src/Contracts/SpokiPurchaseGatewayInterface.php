<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Contracts;

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;

/**
 * Contratto verso l'acquisto (ordine, transazione, o equivalente) collegato a
 * un'attivazione/ricarica Spoki. L'app ospite implementa questa interfaccia; se non ha alcun
 * concetto di acquisto da gestire, può fornire un adapter no-op.
 */
interface SpokiPurchaseGatewayInterface
{
    /**
     * Cerca l'acquisto in attesa collegato al riferimento indicato.
     *
     * @param string $purchaseReference Riferimento opaco fornito dall'app ospite.
     * @return SpokiPurchaseContext|null Null se non esiste alcun acquisto in attesa.
     */
    public function findPendingPurchase(string $purchaseReference): ?SpokiPurchaseContext;

    /**
     * Marca l'acquisto come completato dopo l'attivazione/ricarica avvenuta con successo.
     *
     * @param SpokiPurchaseContext $context Il contesto restituito da findPendingPurchase().
     * @return bool True se l'operazione è andata a buon fine.
     */
    public function markFulfilled(SpokiPurchaseContext $context): bool;
}
