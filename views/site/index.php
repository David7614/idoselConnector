<?php
    use \yii\helpers\Html;

?>


<div id="sites_panel">
    <div class="row">
        <div class="col-md-12 text-center m-3">
            <img src="https://doc.samba.ai/wp-content/uploads/2019/06/extended-e1559896302899.png" alt="">
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="panel">
                <div class="panel-body">
                    <div class="panel-title">
                        <h2 class="panel-heading">
                            Klucz API v3
                        </h2>
                    </div>
                    <div class="panel-body">
                        <?php $form = \yii\bootstrap\ActiveForm::begin()?>
                        <div class="form-group">
                            <?php echo Html::label('username', 'username') ?>
<?php echo Html::textInput('username', $user->username, ['class' => 'form-control', 'id' => 'username']) ?>
                        </div>
                        <div class="form-group">
                            <?php echo Html::label('api3_key', 'api3_key') ?>
<?php echo Html::textInput('api3_key', $user->getUserDataValue('api3_key'), ['class' => 'form-control', 'id' => 'api3_key']) ?>
                        </div>


                        <div class="form-group">
                            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?php \yii\bootstrap\ActiveForm::end();?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
