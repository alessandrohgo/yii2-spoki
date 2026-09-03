<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\ValueObjects;

/**
 * Dati neutri su un acquisto legato a un'attivazione/ricarica Spoki, passati tra il modulo
 * e gli adapter dell'app ospite senza esporre le classi reali dell'host (es. un Order).
 *
 * Volutamente minimo: contiene solo i campi che il modulo stesso deve poter leggere o
 * ricostruire. Qualunque dato aggiuntivo specifico di un singolo host va in `metadata`,
 * mai aggiunto come nuova proprietà tipizzata qui.
 */
final class SpokiPurchaseContext
{
    /**
     * @param string $purchaseReference Identificatore opaco lato host (un id ordine, un id
     *   transazione, o qualunque altra cosa l'host usi per ritrovare l'acquisto).
     * @param int $amountInCents Importo in centesimi, per evitare errori di arrotondamento.
     * @param string $currency Codice valuta ISO 4217 (es. "EUR").
     * @param array<string, mixed> $metadata Dati aggiuntivi specifici dell'host, opachi per il modulo.
     */
    public function __construct(
        public readonly string $purchaseReference,
        public readonly int $amountInCents,
        public readonly string $currency,
        public readonly array $metadata = [],
    ) {
    }
}
