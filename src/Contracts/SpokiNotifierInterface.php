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
     * Invia una notifica relativa a un evento del ciclo di vita di un account Spoki.
     *
     * @param string $eventType Tipo di evento (es. "activated", "recharged").
     * @param string $accountReference Riferimento opaco dell'account destinatario, fornito dall'host.
     * @param array<string, mixed> $context Dati aggiuntivi utili a comporre la notifica.
     */
    public function notify(string $eventType, string $accountReference, array $context): void;
}
