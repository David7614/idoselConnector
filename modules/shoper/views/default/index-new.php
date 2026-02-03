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
            <div class="row">
            <?php $form = \yii\bootstrap\ActiveForm::begin()?>
                <?php foreach ($languages as $language): ?>
                    <div class="col-md-6">
                        <div class="panel">
                            <div class="panel-body">
                                <div class="panel-title">
                                    <h3 class="panel-heading">
                                        Język: <?=$language->locale?>, Waluta <?=\app\modules\shoper\models\ShoperCurrenciesList::find(['id' => $language->currency_id])->one()->name?>:
                                    </h3>
                                </div>
                                <div class="panel-body">
                                    <div class="form-group">
                                        <?=Html::label('Trackpoint', 'trackpoint')?>
                                        <?=Html::textInput('languagesettings[' . $language->locale . '][trackpoint]', $user->getConfigValue('trackpoint', $language->locale, false), ['class' => 'form-control', 'id' => 'trackpoint'])?>
                                    </div>
                                    <div class="form-group">
                                        <?=Html::label('Smartpoint included', 'smartpoint_true')?>
                                        <?=Html::radio('languagesettings[' . $language->locale . '][smartpoint]', $user->getConfigValue('smartpoint', $language->locale, false) ? true : false, ['class' => 'form-control', 'id' => 'smartpoint_true', 'value' => 1])?>
                                    </div>
                                    <div class="form-group">
                                        <?=Html::label('Smartpoint from GTM', 'smartpoint_false')?>
                                        <?=Html::radio('languagesettings[' . $language->locale . '][smartpoint]', $user->getConfigValue('smartpoint', $language->locale, false) ? false : true, ['class' => 'form-control', 'id' => 'smartpoint_false', 'value' => 0])?>
                                    </div>
                                    <div class="form-group">
                                        <?=Html::submitButton('Zapisz', ['class' => 'btn btn-primary'])?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach;?>
            <?php \yii\bootstrap\ActiveForm::end();?>
            </div>

            <div class="col-md-12">
                <div class="panel">
                    <div class="panel-body">
                        <div class="panel-title">
                            <h2 class="panel-heading">
                                Samba feeds urls
                            </h2>
                        </div>
                        <div class="panel-body">
                            <table class="table">
                                <tr>
                                    <td>Products</td>
                                    <td><a href="<?=$user->productsUrl?>" target="_blank"><?=$user->productsUrl?></a></td>
                                </tr>
                                <tr>
                                    <td>Orders</td>
                                    <td><a href="<?=$user->ordersUrl?>" target="_blank"><?=$user->ordersUrl?></a></td>
                                </tr>
                                <tr>
                                    <td>Categories</td>
                                    <td><a href="<?=$user->categoriesUrl?>" target="_blank"><?=$user->categoriesUrl?></a></td>
                                </tr>
                                <tr>
                                    <td>Customers</td>
                                    <td><a href="<?=$user->customersUrl?>" target="_blank"><?=$user->customersUrl?></a></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


    </div>
    <div class="row">
    <?php $form = \yii\bootstrap\ActiveForm::begin()?>
        <div class="col-md-6">
            <div class="panel">
                <div class="panel-body">
                    <div class="panel-title">
                        <h2 class="panel-heading">
                            Feed produktowy
                        </h2>
                    </div>
                    <div class="panel-body">
                        <table class="table">
                            <tr>
                                <td><?=Html::checkbox('Settings[stock_ids]', $user->config->get('stock_ids'), ['class' => 'form-control', 'id' => 'stock_ids']);?></td>
                                <td><?=Html::label("Id magazynów", 'stock_ids');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_image]', $user->config->get('product_image'), ['class' => 'form-control', 'id' => 'product_image']);?></td>
                                <td><?=Html::label("Zdjęcie", 'product_image');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_description]', $user->config->get('product_description'), ['class' => 'form-control', 'id' => 'product_description']);?></td>
                                <td><?=Html::label("Opis produktu", 'product_description');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_brand]', $user->config->get('product_brand'), ['class' => 'form-control', 'id' => 'product_brand']);?></td>
                                <td><?=Html::label("Marka produktu", 'product_brand');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_stock]', $user->config->get('product_stock'), ['class' => 'form-control', 'id' => 'product_stock'])?></td>
                                <td><?=Html::label("Stan magazynowy produktu", 'product_stock')?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_price_before_discount]', $user->config->get('product_price_before_discount'), ['class' => 'form-control', 'id' => 'product_price_before_discount']);?></td>
                                <td><?=Html::label("Cena przed obniżką", 'product_price_before_discount');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_price_buy]', $user->config->get('product_price_buy'), ['class' => 'form-control', 'id' => 'product_price_buy']);?></td>
                                <td><?=Html::label("Cena zakupu", 'product_price_buy');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_categorytext]', $user->config->get('product_categorytext'), ['class' => 'form-control', 'id' => 'product_categorytext']);?></td>
                                <td><?=Html::label("Kategoria", 'product_categorytext');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_line]', $user->config->get('product_line'), ['class' => 'form-control', 'id' => 'product_line']);?></td>
                                <td><?=Html::label("Linia produktu", 'product_line');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_variant]', $user->config->get('product_variant'), ['class' => 'form-control', 'id' => 'product_variant']);?></td>
                                <td><?=Html::label("Wariant produktu", 'product_variant');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[product_parameter]', $user->config->get('product_parameter'), ['class' => 'form-control', 'id' => 'product_parameter']);?></td>
                                <td><?=Html::label("Parametry produktu", 'product_parameter');?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel">
                <div class="panel-body">
                    <div class="panel-title">
                        <h2 class="panel-heading">
                            Feed klientów
                        </h2>
                    </div>
                    <div class="panel-body">
                        <table class="table">
                            <tr>
                                <td><?=Html::checkbox('Settings[customer_feed_email]', $user->config->get('customer_feed_email'), ['class' => 'form-control', 'id' => 'customer_feed_email']);?></td>
                                <td><?=Html::label("Email klienta", 'customer_feed_email');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[customer_feed_registration]', $user->config->get('customer_feed_registration'), ['class' => 'form-control', 'id' => 'customer_feed_registration']);?></td>
                                <td><?=Html::label("Data rejestracji", 'customer_feed_registration');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[customer_feed_first_name]', $user->config->get('customer_feed_first_name'), ['class' => 'form-control', 'id' => 'customer_feed_first_name']);?></td>
                                <td><?=Html::label("Imię klienta", 'customer_feed_first_name');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[customer_feed_last_name]', $user->config->get('customer_feed_last_name'), ['class' => 'form-control', 'id' => 'customer_feed_last_name'])?></td>
                                <td><?=Html::label("Nazwisko klienta", 'customer_feed_last_name')?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[customer_zip_code]', $user->config->get('customer_zip_code'), ['class' => 'form-control', 'id' => 'customer_zip_code']);?></td>
                                <td><?=Html::label("Kod pocztowy klienta", 'customer_zip_code');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[customer_phone]', $user->config->get('customer_phone'), ['class' => 'form-control', 'id' => 'customer_phone']);?></td>
                                <td><?=Html::label("Numer telefonu klienta", 'customer_phone');?></td>
                            </tr>
                            <tr>
                                <td><?=Html::checkbox('Settings[customer_tags]', $user->config->get('customer_tags'), ['class' => 'form-control', 'id' => 'customer_tags']);?></td>
                                <td><?=Html::label("Tagi klienta", 'customer_tags');?></td>
                            </tr>
                        </table>
                        <div class="form-group">
                            <?=Html::submitButton('Zapisz', ['class' => 'btn btn-primary'])?>
                        </div>
                    </div>
                </div>
            </div>
        </div>



    </div>
    <?php \yii\bootstrap\ActiveForm::end();?>
</div>
