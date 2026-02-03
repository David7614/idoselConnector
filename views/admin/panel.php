<?php
    use \yii\helpers\Html;
    use yii\helpers\ArrayHelper;
    use kartik\date\DatePicker;
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
                            Samba ustawienia
                        </h2>
                    </div>
                    <div class="panel-body">
                        <?= Html::beginForm('', 'post') ?>
                        <div class="form-group">
                            <?= Html::label('Trackpoint', 'trackpoint') ?>
                            <?= Html::textInput('trackpoint', $user->config->get('trackpoint'), ['class' => 'form-control', 'id' => 'trackpoint']) ?>
                        </div>
                        <!-- <div class="form-group">
                            <?= Html::label('Smartpoint included', 'smartpoint_true') ?>
                            <?= Html::radio('smartpoint', $user->config->get('smartpoint')?true:false, ['class' => 'form-control', 'id' => 'smartpoint_true', 'value'=>1]) ?>
                        </div>
                        <div class="form-group">
                            <?= Html::label('Smartpoint from GTM', 'smartpoint_false') ?>
                            <?= Html::radio('smartpoint', $user->config->get('smartpoint')?false:true, ['class' => 'form-control', 'id' => 'smartpoint_false', 'value'=>0]) ?>
                        </div> -->
                        <div class="form-group">
                            <?= Html::label('Język exportowany (experymentalna)', 'selected_language') ?>
                            <?= Html::dropDownList('selected_language', $user->config->get('selected_language'), ArrayHelper::map($languages, 'lang_id', 'lang_name'), ['class' => 'form-control', 'id' => 'selected_language']); ?>
                        </div>
                        <div class="form-group">
                            <?= Html::label('Agreguj grupy produktowe jako warianty', 'aggregate_groups_as_variants') ?>
                            <?= Html::dropDownList('aggregate_groups_as_variants', $user->config->get('aggregate_groups_as_variants'), 
                                ['0'=>'Nie', '1'=>'Tak']
                            , ['class' => 'form-control', 'id' => 'aggregate_groups_as_variants']); ?>
                        </div>
                        <div class="form-group">
                            <?= Html::label('Wszystkie dane będą pobierane ze sklepu:') ?>
                            <?= Html::dropDownList('customer_set_shop_id',
                                $user->config->get('customer_set_shop_id'),
                              ['0'=>'Nie wybrano (wszystkie)'] + ArrayHelper::map($shops, 'shop_id', 'shop_name')
                            , ['class' => 'form-control', 'id' => 'customer_set_shop_id']); ?>
                        </div>

                        <div class="form-group">
                            <?= Html::label('Pobieraj stany', 'get_quantity_from') ?>
                            <?= Html::dropDownList('get_quantity_from', $user->config->get('get_quantity_from'),
                                ['0'=>'Domyślnie', '1'=>'Z oferty dyspozycyjnej (productSizesDispositions)']
                            , ['class' => 'form-control', 'id' => 'aggregate_groups_as_variants']); ?>
                        </div>
                        <div class="form-group">
                            <?= Html::label('Generuj dane klientów i zamówień od', 'orders_date_from') ?>
                            <?php // echo Html::textInput('orders_date_from', $user->config->getOrdersDateFrom(), ['class' => 'form-control', 'id' => 'orders_date_from']) ?>
                            <?php
                            echo DatePicker::widget([
                                'name' => 'orders_date_from',
                                'id' => 'orders_date_from',
                                'language' => 'pl',
                                'value' => $user->config->getOrdersDateFrom(),
                                'options' => ['placeholder' => 'Wybierz datę ...'],
                                'pluginOptions' => [
                                    'format' => 'yyyy-mm-dd',
                                    'todayHighlight' => true
                                ]
                            ]);
                            ?>

                        </div>


                        <div class="form-group">
                            <?= Html::label('Typ exportu danych', 'export_type') ?>
                            <?= Html::dropDownList('export_type', $user->config->get('export_type'),
                                ['0'=>'Pełna baza', '1'=>'Inkrementalny']
                            , ['class' => 'form-control', 'id' => 'export_type']); ?>
                        </div>

                        <div class="form-group">
                            <?= Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?= Html::endForm() ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
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
                                <td><?= $urls['products'] ?></td>
                                <td><?= $user->countDatabaseElements('products') ?></td>
                                <td><?= $filesInfo['products']['status'] ?> <?= $filesInfo['products']['elements'] ?></td>
                            </tr>
                            <tr>
                                <td>Orders</td>
                                <td><?= $urls['orders'] ?></td>
                                <td><?= $user->countDatabaseElements('order') ?></td>
                                <td><?= $filesInfo['order']['status'] ?> <?= $filesInfo['order']['elements'] ?></td>
                            </tr>
                            <tr>
                                <td>Categories</td>
                                <td><?= $urls['categories'] ?></td>
                                <td>nd</td>
                                <td><?= $filesInfo['category']['status'] ?> <?= $filesInfo['category']['elements'] ?></td>
                            </tr>
                            <tr>
                                <td>Customers</td>
                                <td><?= $urls['customers'] ?></td>
                                <td><?= $user->countDatabaseElements('customer') ?></td>
                                <td><?= $filesInfo['customer']['status'] ?> <?= $filesInfo['customer']['elements'] ?></td>
                            </tr>
                        </table>
                    </div>
                    <?php print_r($filesInfo); ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="panel">
                <div class="panel-body">
                    <div class="panel-title">
                        <h2 class="panel-heading">
                            Feed produktowy
                        </h2>
                    </div>
                    <div class="panel-body">
                        <?= Html::beginForm(\yii\helpers\Url::toRoute(['site/save-product-feed']), 'post') ?>
                        <table class="table">
                            <?php 
                                $stockIdsArray=$user->config->getStockIdsArray();
                                // var_dump($stockIdsArray);
                            ?>
                            <?php if ($stocks){ ?>
                                <?php foreach ($stocks as $stock){ ?>
                                    <tr>
                                        <td style="width:34px;"><?= Html::checkbox('Settings[stock_ids_array][]', in_array($stock->stock_id, $stockIdsArray), ['class' => 'form-control', 'id' => 'stock_ids', 'value'=>$stock->stock_id]); ?></td>
                                        <td><?= Html::label("Pobieraj stany z: ".$stock->stock_name, 'stock_ids'); ?></td>                                    
                                    </tr>
                                <?php } ?>
                            <?php } ?>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_image]', $user->config->get('product_image'), ['class' => 'form-control', 'id' => 'product_image']); ?></td>
                                <td><?= Html::label("Zdjęcie", 'product_image'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_description]', $user->config->get('product_description'), ['class' => 'form-control', 'id' => 'product_description']); ?></td>
                                <td><?= Html::label("Opis produktu", 'product_description');?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_brand]', $user->config->get('product_brand'), ['class' => 'form-control', 'id' => 'product_brand']); ?></td>
                                <td><?= Html::label("Marka produktu", 'product_brand'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_stock]', $user->config->get('product_stock'), ['class' => 'form-control', 'id' => 'product_stock']) ?></td>
                                <td><?= Html::label("Stan magazynowy produktu", 'product_stock') ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_price_before_discount]', $user->config->get('product_price_before_discount'), ['class' => 'form-control', 'id' => 'product_price_before_discount']); ?></td>
                                <td><?= Html::label("Cena przed obniżką", 'product_price_before_discount'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_price_buy]', $user->config->get('product_price_buy'), ['class' => 'form-control', 'id' => 'product_price_buy']); ?></td>
                                <td><?= Html::label("Cena zakupu", 'product_price_buy'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_categorytext]', $user->config->get('product_categorytext'), ['class' => 'form-control', 'id' => 'product_categorytext']); ?></td>
                                <td><?= Html::label("Kategoria", 'product_categorytext'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_line]', $user->config->get('product_line'), ['class' => 'form-control', 'id' => 'product_line']); ?></td>
                                <td><?= Html::label("Linia produktu", 'product_line'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_variant]', $user->config->get('product_variant'), ['class' => 'form-control', 'id' => 'product_variant']); ?></td>
                                <td><?= Html::label("Wariant produktu", 'product_variant'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[product_parameter]', $user->config->get('product_parameter'), ['class' => 'form-control', 'id' => 'product_parameter']); ?></td>
                                <td><?= Html::label("Parametry produktu", 'product_parameter'); ?></td>
                            </tr>
                        </table>
                        <div class="form-group">
                            <?= Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?= Html::endForm() ?>
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
                        <?= Html::beginForm(\yii\helpers\Url::toRoute(['site/save-customer-feed']), 'post') ?>
                        <table class="table">
                            <tr>
                                <td style="width:34px;"><?= Html::checkbox('Settings[customer_feed_email]', $user->config->get('customer_feed_email'), ['class' => 'form-control', 'id' => 'customer_feed_email']); ?></td>
                                <td><?= Html::label("Email klienta", 'customer_feed_email'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[customer_feed_registration]', $user->config->get('customer_feed_registration'), ['class' => 'form-control', 'id' => 'customer_feed_registration']); ?></td>
                                <td><?= Html::label("Data rejestracji", 'customer_feed_registration');?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[customer_feed_first_name]', $user->config->get('customer_feed_first_name'), ['class' => 'form-control', 'id' => 'customer_feed_first_name']); ?></td>
                                <td><?= Html::label("Imię klienta", 'customer_feed_first_name'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[customer_feed_last_name]', $user->config->get('customer_feed_last_name'), ['class' => 'form-control', 'id' => 'customer_feed_last_name']) ?></td>
                                <td><?= Html::label("Nazwisko klienta", 'customer_feed_last_name') ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[customer_zip_code]', $user->config->get('customer_zip_code'), ['class' => 'form-control', 'id' => 'customer_zip_code']); ?></td>
                                <td><?= Html::label("Kod pocztowy klienta", 'customer_zip_code'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[customer_phone]', $user->config->get('customer_phone'), ['class' => 'form-control', 'id' => 'customer_phone']); ?></td>
                                <td><?= Html::label("Numer telefonu klienta", 'customer_phone'); ?></td>
                            </tr>
                            <tr>
                                <td><?= Html::checkbox('Settings[customer_tags]', $user->config->get('customer_tags'), ['class' => 'form-control', 'id' => 'customer_tags']); ?></td>
                                <td><?= Html::label("Tagi klienta", 'customer_tags'); ?></td>
                            </tr>

                            <tr>
                                <td></td>
                                <td><h3>Zgody marketingowe pobierane ze sklepu:</h3></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td><?= Html::dropDownList('Settings[customer_default_approvals_shop_id]', $user->config->get('customer_default_approvals_shop_id')?$user->config->get('customer_default_approvals_shop_id'):1, ArrayHelper::map($shops, 'shop_id', 'shop_name'), ['class' => 'form-control', 'id' => 'customer_default_approvals_shop_id']); ?></td>                                
                            </tr>
                            
                        </table>
                        <div class="form-group">
                            <?= Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?= Html::endForm() ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
