<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Contracts;

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiPurchaseContext;

/**
 * Contratto verso il sistema di fatturazione dell'app ospite (può essere FattureInCloud,
 * Fiskaly, un altro sistema, o nessuno). Il modulo chiede solo "emetti un documento per questo
 * acquisto", senza sapere quale sistema risponde alla richiesta.
 */
interface SpokiInvoicingInterface
{
    /**
     * Mette in coda l'emissione di un documento di fatturazione per l'acquisto indicato.
     *
     * @param SpokiPurchaseContext $context Il contesto dell'acquisto da fatturare.
     * @return bool True se la richiesta è stata accodata con successo (o se l'host decide
     *   deliberatamente di non fatturare in questo caso — es. un whitelabel non root).
     */
    public function enqueueInvoice(SpokiPurchaseContext $context): bool;
}
