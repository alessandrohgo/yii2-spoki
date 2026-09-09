<?php

declare(strict_types=1);

use AlessandroHgo\Yii2Spoki\Assets\DashboardAsset;
use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use yii\helpers\Html;
use yii\web\View;

/* @var $this View */
/* @var $spokiAccount SpokiAccountState */
/* @var $sections array<string, string> */
/* @var $language string */
/* @var $authTokenUrl string */

DashboardAsset::register($this);

$this->title = Yii::t('app', 'Spoki');
?>
<!--
    Vista di riferimento, volutamente minimale (nessun framework CSS assunto). Per personalizzare
    l'aspetto, imposta `viewPath` sulla tua cartella nella configurazione del controller/modulo,
    o estendi DashboardController e sovrascrivi questa vista con la tua. Il JS che rende
    funzionante l'iframe è registrato da DashboardAsset — se scrivi una tua vista puoi
    riutilizzarlo (basta mantenere gli stessi id/data attribute) o sostituirlo con un tuo bundle.
-->
<div id="spoki-dashboard">
    <ul>
        <?php foreach ($sections as $slug => $label): ?>
            <li><a href="#<?= Html::encode($slug) ?>" data-spoki-slug="<?= Html::encode($slug) ?>"><?= Html::encode($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <div
        id="spoki-embedding"
        data-auth-token-url="<?= Html::encode($authTokenUrl) ?>"
        data-language="<?= Html::encode($language) ?>"
    ></div>
</div>
