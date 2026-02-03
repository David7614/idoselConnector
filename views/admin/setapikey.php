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
                        <?php echo Html::beginForm('', 'post') ?>
                        <div class="form-group">
                            <?php echo Html::label('api3_key', 'trackpoint') ?>
<?php echo Html::textInput('api3_key', $user->getUserDataValue('api3_key'), ['class' => 'form-control', 'id' => 'api3_key']) ?>
                        </div>


                        <div class="form-group">
                            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?php echo Html::endForm() ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
