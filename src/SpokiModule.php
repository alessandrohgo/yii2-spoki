<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki;

use yii\base\Module;

/**
 * Modulo Yii2 per l'integrazione Spoki.
 *
 * Passo di pratica: verifica che il modulo, installato via Composer in un'altra app Yii2,
 * si registri e si istanzi correttamente. La logica reale (contracts/adapter per
 * ordini/fatturazione/notifiche) verrà portata qui solo dopo aver completato il
 * disaccoppiamento dall'app aziendale ospite (piano in `docs/private/`, non pubblicato).
 */
class SpokiModule extends Module
{
    public const VERSION = '0.1.0';

    /**
     * Restituisce la versione del modulo installato.
     *
     * @return string La versione corrente del pacchetto.
     */
    public function version(): string
    {
        return self::VERSION;
    }
}
