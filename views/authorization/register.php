<?php

use \yii\helpers\Html;
/* @var $this yii\web\View */


$this->title = 'Authorization | SAMBA';
?>

<div class="row mt-3">
    <div class="col-md-12">
        <div class="panel panel-primary" style="max-width: 400px; margin: 0 auto;">
            <div class="panel-heading text-center">
                <h3 class="panel-title">Zarejestruj</h3>
            </div>
            <div class="panel-body">
                <?php $form = \yii\bootstrap\ActiveForm::begin(['id' => 'register-form', 'action' => \yii\helpers\Url::toRoute(['authorization/register'])]) ?>
                <div class="form-group">
                    <?= $form->field($model, 'username')->textInput() ?>
                    <?= $form->field($model, 'shop_type')->label(false)->hiddenInput(['value'=>'idiosell'])?>
                    <?= $form->field($model, 'active')->label(false)->hiddenInput(['value'=>'1'])?>
                </div>
                <div class="form-group">
                    <?= $form->field($model, 'email')->textInput() ?>
                </div>
                <div class="form-group">
                    <?= $form->field($model, 'password')->passwordInput() ?>
                </div>
                <div class="form-group text-right">
                    <?= \yii\helpers\Html::submitButton('Zarejestruj', ['class' => 'btn btn-primary']) ?>
                </div>
                <?php  \yii\bootstrap\ActiveForm::end(); ?>
                <div class="text-center">
                    <span>
                        Masz już konto? <?= Html::a('Zaloguj się', \yii\helpers\Url::toRoute(['authorization/login'])) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>