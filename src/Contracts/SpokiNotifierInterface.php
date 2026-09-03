<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Contracts;

/**
 * Contratto verso il sistema di notifiche dell'app ospite (email, altro canale, o nessuno).
 * Il modulo comunica solo il tipo di evento e a chi è indirizzato, senza sapere come l'host
 * invia effettivamente la notifica.
 */
interface SpokiNotifierInterface
{
    /**
     * Evento: pagamento confermato, attivazione Spoki avviata (l'utente deve ancora completare
     * l'onboarding).
     */
    public const string EVENT_PAYMENT_CONFIRMED = 'payment_confirmed';

    /**
     * Evento: attivazione Spoki completata con successo (onboarding concluso).
     */
    public const string EVENT_ACTIVATED = 'activated';

    /**
     * Invia una notifica relativa a un evento del ciclo di vita di un account Spoki.
     *
     * @param string $eventType Tipo di evento (es. "activated", "recharged").
     * @param string $accountReference Riferimento opaco dell'account destinatario, fornito dall'host.
     * @param array<string, mixed> $context Dati aggiuntivi utili a comporre la notifica.
     */
    public function notify(string $eventType, string $accountReference, array $context): void;
}
