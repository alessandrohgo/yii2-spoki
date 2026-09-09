<?php

declare(strict_types=1);

namespace AlessandroHgo\Yii2Spoki\Assets;

use yii\web\AssetBundle;

/**
 * Registra il JS che rende funzionante l'iframe Spoki nella dashboard (autenticazione, cambio
 * sezione, deep link). Nessuna dipendenza da altri asset bundle (jQuery, Bootstrap, ecc.).
 *
 * Un'app ospite che vuole un proprio JS al posto di questo può registrare un asset bundle
 * diverso nella propria vista, invece di questo — {@see \AlessandroHgo\Yii2Spoki\Controllers\DashboardController}
 * non impone questo bundle, lo registra solo la vista di default del pacchetto.
 */
class DashboardAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/js';

    public $js = [
        'spoki-dashboard.js',
    ];
}
