<?php
use yii\helpers\Html;
use yii\helpers\Url;
?>

<style>
.settings-page .panel          { margin-bottom: 20px; border-radius: 6px; }
.settings-page .panel-heading  { font-size: 15px; font-weight: 600; }
.settings-page .check-row      { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
.settings-page .check-row:last-child { border-bottom: none; }
.settings-page .check-label    { flex: 1; font-size: 14px; }
.settings-page .check-path     { flex: 2; font-size: 12px; color: #999; font-family: monospace; }
.settings-page .check-status   { width: 120px; text-align: right; }
.settings-page .status-ok      { color: #2e7d32; font-weight: 600; }
.settings-page .status-err     { color: #c62828; font-weight: 600; }
.settings-page .api-key-val    { font-family: monospace; font-size: 12px; background: #f5f5f5;
                                  padding: 6px 10px; border-radius: 4px; word-break: break-all;
                                  border: 1px solid #ddd; display: block; margin-bottom: 8px; }
</style>

<div class="settings-page" style="margin: 20px; max-width: 860px;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <h2 style="margin:0;">
            Ustawienia: <span style="color:#337ab7;"><?= Html::encode($user->username) ?></span>
        </h2>
        <div>
            <?= Html::a('&larr; Lista', Url::to(['admin/index']), ['class' => 'btn btn-default btn-sm']) ?>
            <?= Html::a('Kolejka', Url::to(['admin/view', 'id' => $user->id]), ['class' => 'btn btn-default btn-sm']) ?>
            <?= Html::a('Panel', Url::to(['admin/dashboard', 'id' => $user->id]), ['class' => 'btn btn-info btn-sm']) ?>
        </div>
    </div>

    <?php if ($savedOk): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            Ustawienia zostały zapisane.
        </div>
    <?php endif ?>

    <?php if (isset($checkResults['error'])): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= Html::encode($checkResults['error']) ?>
        </div>
    <?php endif ?>

    <!-- Ustawienia konta -->
    <div class="panel panel-default">
        <div class="panel-heading">Ustawienia konta</div>
        <div class="panel-body">
            <?= Html::beginForm(['admin/update', 'id' => $user->id], 'post') ?>
            <div class="form-group">
                <?= Html::label('Domena techniczna (username)') ?>
                <input type="text" class="form-control" value="<?= Html::encode($user->username) ?>" disabled>
            </div>
            <div class="form-group">
                <?= Html::label('Typ sklepu', 'shop_type') ?>
                <?= Html::dropDownList('shop_type', $user->shop_type,
                    ['idiosell' => 'idosell', 'idiosell_test' => 'idosell (test)'],
                    ['class' => 'form-control', 'id' => 'shop_type']
                ) ?>
            </div>
            <div class="form-group">
                <?= Html::label('Status konta', 'active') ?>
                <?= Html::dropDownList('active', $user->active,
                    [1 => 'Aktywny', 0 => 'Nieaktywny'],
                    ['class' => 'form-control', 'id' => 'active']
                ) ?>
            </div>
            <?= Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>

    <!-- Klucz API -->
    <div class="panel panel-default">
        <div class="panel-heading">Klucz API (idosell v3)</div>
        <div class="panel-body">
            <?php $apiKey = $user->getUserDataValue('api3_key'); ?>
            <?php if ($apiKey): ?>
                <label>Aktualny klucz:</label>
                <code class="api-key-val"><?= Html::encode($apiKey) ?></code>
            <?php else: ?>
                <div class="alert alert-warning" style="margin-bottom:12px;">Brak klucza API — integracja nie będzie działać.</div>
            <?php endif ?>

            <?= Html::beginForm(['admin/update', 'id' => $user->id], 'post') ?>
            <div class="form-group">
                <?= Html::label($apiKey ? 'Zmień klucz API' : 'Ustaw klucz API', 'api3_key') ?>
                <?= Html::input('text', 'api3_key', '', [
                    'class'       => 'form-control',
                    'id'          => 'api3_key',
                    'placeholder' => 'Wklej nowy klucz API…',
                ]) ?>
            </div>
            <?= Html::submitButton($apiKey ? 'Aktualizuj klucz' : 'Zapisz klucz', ['class' => 'btn btn-primary']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>

    <!-- Test integracji -->
    <div class="panel panel-default">
        <div class="panel-heading">
            Test integracji
            <span style="float:right;">
                <?= Html::beginForm(['admin/update', 'id' => $user->id], 'post', ['style' => 'display:inline']) ?>
                <?= Html::hiddenInput('_action', 'check') ?>
                <?= Html::submitButton('▶ Uruchom test', [
                    'class' => 'btn btn-sm ' . ($apiKey ? 'btn-primary' : 'btn-default'),
                    'disabled' => !$apiKey,
                    'title' => $apiKey ? 'Testuj połączenie z idosell API' : 'Najpierw ustaw klucz API',
                ]) ?>
                <?= Html::endForm() ?>
            </span>
        </div>
        <div class="panel-body">
            <?php if ($checkResults): ?>
                <?php
                    $allOk = array_reduce($checkResults, fn($carry, $r) => $carry && $r['ok'], true);
                ?>
                <div class="alert alert-<?= $allOk ? 'success' : 'danger' ?>" style="margin-bottom:16px;">
                    <?php if ($allOk): ?>
                        <strong>✔ Integracja działa poprawnie.</strong> Wszystkie moduły odpowiadają.
                    <?php else: ?>
                        <strong>✖ Wykryto problemy z integracją.</strong> Sprawdź szczegóły poniżej.
                    <?php endif ?>
                </div>
                <?php foreach ($checkResults as $r): ?>
                    <div class="check-row">
                        <div class="check-label"><strong><?= Html::encode($r['label']) ?></strong></div>
                        <div class="check-path"><?= Html::encode($r['path']) ?></div>
                        <div class="check-status">
                            <?php if ($r['ok']): ?>
                                <span class="status-ok">✔ OK</span>
                            <?php else: ?>
                                <span class="status-err">✖ Błąd</span>
                            <?php endif ?>
                        </div>
                    </div>
                <?php endforeach ?>
            <?php else: ?>
                <p style="color:#999; margin:0;">
                    Kliknij <em>Uruchom test</em>, aby sprawdzić połączenie z każdym modułem idosell API (System, CRM, OMS, PIM).
                </p>
            <?php endif ?>
        </div>
    </div>

</div>
