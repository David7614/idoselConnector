<?php
    use \yii\helpers\Html;
    use \yii\helpers\Url;

    $params = Yii::$app->request->get();
?>

<p style="text-align:center; width:100%;"><img src="https://doc.samba.ai/wp-content/uploads/2019/06/extended-e1559896302899.png"></p>
<p style="text-align:center;"><h2 style="text-align:center; color: #f37221;">Witaj, dziękujemy za instalację Samba.ai</h2></p>
<p>
    Uwaga! Przed rozpoczęciem konfiguracji connectora przygotuj swój tracking ID z
aplikacji Samba.ai. Jeśli nie posiadasz jeszcze jeszcze swojego konta w aplikacji
Samba.ai możesz je utworzyć bezpłatnie pod tym linkiem..
</p>
<p>
    <a href="https://app.samba.ai/signup?utm_campaign=Shoper-new-users&utm_medium=appstore&utm_source=shoper" class="btn btn-samba" target="_blank">Kliknij tutaj</a>
</p>
<p>
    ...i skorzystać z nielimitowanej czasowo, darmowej wersji aplikacji z limitem do
aż 1 000 kontaktów lub 10 000 maili!
</p>
<p>
    Posiadasz już konto Samba.ai i tracking ID?
</p>

<p>
    <?php
        $url = Url::to(array_merge(["/shoper"], $params));
    ?>
    <?= Html::a('Przejdź dalej', $url, ['class' => "btn btn-samba2"]) ?>
</p>
