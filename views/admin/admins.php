<?php
use yii\helpers\Html;

$this->title = 'Administratorzy';
$currentId   = Yii::$app->user->id;
?>

<style>
.admins-page { margin: 20px; max-width: 740px; }
.admins-page h2 { margin: 0 0 24px; }
</style>

<div class="admins-page">
    <h2>Administratorzy</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= Html::encode($error) ?>
        </div>
    <?php endif ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= Html::encode($success) ?>
        </div>
    <?php endif ?>

    <!-- Lista -->
    <div class="panel panel-default">
        <div class="panel-heading" style="font-weight:600;">Aktualni administratorzy</div>
        <table class="table table-bordered" style="margin:0;">
            <thead>
                <tr>
                    <th style="width:50px;">ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th style="width:220px;">Zmień hasło</th>
                    <th style="width:70px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $admin): ?>
                <tr>
                    <td style="color:#999; font-size:12px;"><?= $admin->id ?></td>
                    <td>
                        <?= Html::encode($admin->username) ?>
                        <?php if ($admin->id === $currentId): ?>
                            <span style="color:#888; font-size:12px;">(Ty)</span>
                        <?php endif ?>
                    </td>
                    <td style="font-size:12px; color:#666;"><?= Html::encode($admin->email) ?></td>
                    <td>
                        <?= Html::beginForm(['admin/admins'], 'post', ['style' => 'display:flex; gap:4px;']) ?>
                        <?= Html::hiddenInput('action', 'password') ?>
                        <?= Html::hiddenInput('user_id', $admin->id) ?>
                        <?= Html::input('password', 'password', '', [
                            'class'       => 'form-control input-sm',
                            'placeholder' => 'Nowe hasło…',
                            'minlength'   => 6,
                            'required'    => true,
                        ]) ?>
                        <?= Html::submitButton('Zmień', ['class' => 'btn btn-xs btn-default', 'style' => 'white-space:nowrap']) ?>
                        <?= Html::endForm() ?>
                    </td>
                    <td>
                        <?php if ($admin->id !== $currentId): ?>
                            <?= Html::beginForm(['admin/admins'], 'post') ?>
                            <?= Html::hiddenInput('action', 'remove') ?>
                            <?= Html::hiddenInput('user_id', $admin->id) ?>
                            <?= Html::submitButton('Usuń', [
                                'class'        => 'btn btn-xs btn-danger',
                                'data-confirm' => 'Usunąć konto administratora ' . Html::encode($admin->username) . '?',
                            ]) ?>
                            <?= Html::endForm() ?>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Dodaj -->
    <div class="panel panel-default">
        <div class="panel-heading" style="font-weight:600;">Dodaj administratora</div>
        <div class="panel-body">
            <?= Html::beginForm(['admin/admins'], 'post') ?>
            <?= Html::hiddenInput('action', 'add') ?>
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        <?= Html::label('Username') ?>
                        <?= Html::textInput('username', '', ['class' => 'form-control', 'required' => true]) ?>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <?= Html::label('Email') ?>
                        <?= Html::input('email', 'email', '', ['class' => 'form-control', 'required' => true]) ?>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <?= Html::label('Hasło') ?>
                        <?= Html::input('password', 'password', '', [
                            'class'     => 'form-control',
                            'required'  => true,
                            'minlength' => 6,
                        ]) ?>
                    </div>
                </div>
            </div>
            <?= Html::submitButton('Dodaj administratora', ['class' => 'btn btn-primary']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
