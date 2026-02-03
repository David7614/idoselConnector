<?php

    /* @var $this \yii\web\View */
    /* @var $content string */

    use app\assets\AppAsset;
    use app\models\User;
    use app\modules\IAI\Application\Config;
    use yii\helpers\Html;

    AppAsset::register($this);
?>
<?php $this->beginPage()?>
<!DOCTYPE html>
<html lang="<?php echo Yii::$app->language?>">
<head>
    <meta charset="<?php echo Yii::$app->charset?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags()?>
    <title><?php echo Html::encode($this->title)?></title>
    <?php $this->head()?>
<?php if (Yii::$app->user->getIdentity() === null): ?>

    <?php else: ?>
<?php
    $user              = User::findIdentity(Yii::$app->user->id);
    $applicationConfig = new Config($user);
    echo '<link rel="stylesheet" href=' . ((new \app\modules\IAI\Application\Resources($applicationConfig))->getStyleSheetUrl()) . ' />';
?>
<?php endif;?>
</head>
<body>
<?php $this->beginBody()?>

<div class="wrap">
    <?php echo \app\widgets\Alert::widget()?>

    <div class="container">
        <?php echo $content?>
    </div>
</div>


<?php $this->endBody()?>
</body>
</html>
<?php $this->endPage()?>
