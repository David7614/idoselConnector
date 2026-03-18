<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Ustawienia aplikacji';
?>

<style>
.app-settings { margin: 20px; max-width: 700px; }
.app-settings h2 { margin: 0 0 24px; }
.setting-row  { display:flex; align-items:center; padding:16px 0; border-bottom:1px solid #f0f0f0; gap:16px; }
.setting-row:last-child { border-bottom:none; }
.setting-info { flex:1; }
.setting-info strong { font-size:14px; display:block; margin-bottom:4px; }
.setting-info span   { font-size:12px; color:#888; }
.setting-ctrl { width:120px; text-align:right; }
</style>

<div class="app-settings">
    <h2>Ustawienia aplikacji</h2>

    <?php if ($saved): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            Zapisano.
        </div>
    <?php endif ?>

    <?= Html::beginForm(['admin/app-settings'], 'post') ?>

    <div class="panel panel-default">
        <div class="panel-heading" style="font-weight:600;">Synchronizacja</div>
        <div class="panel-body">

            <div class="setting-row">
                <div class="setting-info">
                    <strong>Wymuś tryb inkrementalny dla wszystkich</strong>
                    <span>Gdy włączone, każdy użytkownik synchronizuje tylko dane z ostatnich 2 tygodni, niezależnie od indywidualnych ustawień.</span>
                </div>
                <div class="setting-ctrl">
                    <?= Html::dropDownList('force_all_incremental', $forceAllIncremental, [
                        0 => 'Wyłączone',
                        1 => 'Włączone',
                    ], ['class' => 'form-control input-sm']) ?>
                </div>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <strong>Tryb debug</strong>
                    <span>Wyświetla dodatkowe informacje diagnostyczne podczas synchronizacji.</span>
                </div>
                <div class="setting-ctrl">
                    <?= Html::dropDownList('display_debug', $displayDebug, [
                        0 => 'Wyłączone',
                        1 => 'Włączone',
                    ], ['class' => 'form-control input-sm']) ?>
                </div>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <strong>Domyślny zakres historii zamówień (lata)</strong>
                    <span>Gdy użytkownik nie ma ustawionej daty "od" dla zamówień, pobierane są dane z ostatnich X lat. Domyślnie: 10.</span>
                </div>
                <div class="setting-ctrl">
                    <?= Html::input('number', 'default_orders_years_back', $defaultOrdersYearsBack, [
                        'class' => 'form-control input-sm',
                        'min'   => 1,
                        'max'   => 30,
                    ]) ?>
                </div>
            </div>

        </div>
    </div>

    <?= Html::submitButton('Zapisz ustawienia', ['class' => 'btn btn-primary']) ?>

    <?= Html::endForm() ?>
</div>
