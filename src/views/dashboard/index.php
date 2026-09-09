<?php

declare(strict_types=1);

use AlessandroHgo\Yii2Spoki\ValueObjects\SpokiAccountState;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/* @var $this View */
/* @var $spokiAccount SpokiAccountState */
/* @var $sections array<string, string> */

$this->title = Yii::t('app', 'Spoki');
$authTokenUrl = Url::to(['auth-token']);
?>
<!--
    Vista di riferimento, volutamente minimale (nessun framework CSS assunto). Per personalizzare
    l'aspetto, imposta `viewPath` sulla tua cartella nella configurazione del controller/modulo,
    o estendi DashboardController e sovrascrivi questa vista con la tua.
-->
<div id="spoki-dashboard">
    <ul>
        <?php foreach ($sections as $slug => $label): ?>
            <li><a href="#<?= Html::encode($slug) ?>" data-spoki-slug="<?= Html::encode($slug) ?>"><?= Html::encode($label) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <div id="spoki-embedding" data-auth-token-url="<?= Html::encode($authTokenUrl) ?>"></div>
</div>
