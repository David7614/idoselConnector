<?php
    use kartik\date\DatePicker;
    use yii\helpers\ArrayHelper;
    use \yii\helpers\Html;
    use app\models\IntegrationData;
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
                        <?php echo Html::beginForm('', 'post') ?>
                        <div class="form-group">
                            <?php echo Html::label('Trackpoint', 'trackpoint') ?>
<?php echo Html::textInput('trackpoint', $user->config->get('trackpoint'), ['class' => 'form-control', 'id' => 'trackpoint']) ?>
                        </div>
                        <!-- <div class="form-group">
                            <?php echo Html::label('Smartpoint included', 'smartpoint_true') ?>
<?php echo Html::radio('smartpoint', $user->config->get('smartpoint') ? true : false, ['class' => 'form-control', 'id' => 'smartpoint_true', 'value' => 1]) ?>
                        </div>
                        <div class="form-group">
                            <?php echo Html::label('Smartpoint from GTM', 'smartpoint_false') ?>
<?php echo Html::radio('smartpoint', $user->config->get('smartpoint') ? false : true, ['class' => 'form-control', 'id' => 'smartpoint_false', 'value' => 0]) ?>
                        </div> -->
                        <div class="form-group">
                            <?php echo Html::label('Język exportowany (experymentalna)', 'selected_language') ?>
<?php echo Html::dropDownList('selected_language', $user->config->get('selected_language'), ArrayHelper::map($languages, 'lang_id', 'lang_name'), ['class' => 'form-control', 'id' => 'selected_language']); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Html::label('Agreguj grupy produktowe jako warianty', 'aggregate_groups_as_variants') ?>
<?php echo Html::dropDownList('aggregate_groups_as_variants', $user->config->get('aggregate_groups_as_variants'),
        ['0' => 'Nie', '1' => 'Tak']
    , ['class' => 'form-control', 'id' => 'aggregate_groups_as_variants']); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Html::label('Wszystkie dane będą pobierane ze sklepu:') ?>
<?php echo Html::dropDownList('customer_set_shop_id',
	$user->config->get('customer_set_shop_id'), 
	ArrayHelper::map($shops, 'shop_id', 'shop_name') + ['0' => 'Nie wybrano (wszystkie)'], ['class' => 'form-control', 'id' => 'customer_set_shop_id']); ?>
                        </div>

                        <div class="form-group">
                            <?php echo Html::label('Pobieraj stany', 'get_quantity_from') ?>
<?php echo Html::dropDownList('get_quantity_from', $user->config->get('get_quantity_from'),
        ['0' => 'Domyślnie', '1' => 'Z oferty dyspozycyjnej (productSizesDispositions)']
    , ['class' => 'form-control', 'id' => 'aggregate_groups_as_variants']); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Html::label('Używaj menu kategorii', 'get_menu_from') ?>
                            <?php echo Html::dropDownList('get_menu_from', $user->config->get('get_menu_from'),
                                [
                                    '0' => 'Standardowe kategorie idosell',
                                    '1' => 'Węzeł menu 1 (z reguły domyślny)',
                                    '2' => 'Węzeł menu 2',
                                    '3' => 'Węzeł menu 3',
                                    '4' => 'Węzeł menu 4',
                                    '5' => 'Węzeł menu 5'
                                ]
                                , ['class' => 'form-control', 'id' => 'aggregate_groups_as_variants']); ?>
                        </div>
                        <div class="form-group">
                            <?php echo Html::label('Generuj dane klientów i zamówień od', 'orders_date_from') ?>
<?php // echo Html::textInput('orders_date_from', $user->config->getOrdersDateFrom(), ['class' => 'form-control', 'id' => 'orders_date_from']) ?>
<?php
    echo DatePicker::widget([
        'name'          => 'orders_date_from',
        'id'            => 'orders_date_from',
        'language'      => 'pl',
        'value'         => $user->config->getOrdersDateFrom(),
        'options'       => ['placeholder' => 'Wybierz datę ...'],
        'pluginOptions' => [
            'format'         => 'yyyy-mm-dd',
            'todayHighlight' => true,
        ],
    ]);
?>

                        </div>


                        <div class="form-group">
                            <?php echo Html::label('Typ exportu danych', 'export_type') ?>
                            <?php echo Html::dropDownList('export_type', $user->config->get('export_type'),
                                    ['0' => 'Pełna baza', '1' => 'Inkrementalny']
                                , ['class' => 'form-control', 'id' => 'export_type']); ?>
                        </div>

                        <div class="form-group">
                            <?= Html::label('Działanie feeda produktowego', 'product_feed_disable') ?>
                            <?= Html::dropDownList('product_feed_disable', $user->config->get('product_feed_disable'),
                                ['0'=>'Feed produktowy włączony', '1'=>'Feed produktowy wyłączony']
                            , ['class' => 'form-control', 'id' => 'product_feed_disable']); ?>
                        </div>

                        <div class="form-group">
                            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?php echo Html::endForm() ?>
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
                                <td><a href="<?php echo $urls['products'] ?>" target="_blank"><?php echo $urls['products'] ?></a></td>
                                <td><?php echo $user->countDatabaseElements('products') ?></td>
                                <td><?php echo $filesInfo['products']['status'] ?><?php echo $filesInfo['products']['elements'] ?></td>
                            </tr>
                            <tr>
                                <td>Orders</td>
                                <td><a href="<?php echo $urls['orders'] ?>" target="_blank"><?php echo $urls['orders'] ?></a></td>
                                <td><?php echo $user->countDatabaseElements('order') ?></td>
                                <td><?php echo $filesInfo['order']['status'] ?><?php echo $filesInfo['order']['elements'] ?></td>
                            </tr>
                            <tr>
                                <td>Categories</td>
                                <td><a href="<?php echo $urls['categories'] ?>" target="_blank"><?php echo $urls['categories'] ?></a></td>
                                <td>nd</td>
                                <td><?php echo $filesInfo['category']['status'] ?><?php echo $filesInfo['category']['elements'] ?></td>
                            </tr>
                            <tr>
                                <td>Customers</td>
                                <td><a href="<?php echo $urls['customers'] ?>" target="_blank"><?php echo $urls['customers'] ?></a></td>
                                <td><?php echo $user->countDatabaseElements('customer') ?></td>
                                <td><?php echo $filesInfo['customer']['status'] ?> <?php echo $filesInfo['customer']['elements'] ?></td>
                            </tr>
                        </table>
                    </div>

                </div>
            </div>
            <div class="panel">
                <div class="panel-body">
                    <div class="panel-title">
                        <h2 class="panel-heading">
                            Samba urls settings
                        </h2>
                    </div>
                    <div class="panel-body">
                        <table class="table">
                            <tr>
                                <th>Typ</th>
                                <th>Ostatnia integracja</th>
                            </tr>
                            <tr>
                                <td>Products</td>
                                <td><?= IntegrationData::getDataValue('last_products_integration_date', $user->id) ?></td>
                            </tr>
                            <tr>
                                <td>Orders</td>
                                <td><?= IntegrationData::getDataValue('last_orders_integration_date', $user->id) ?></td>
                            </tr>
                            <tr>
                                <td>Customers</td>
                                <td><?= IntegrationData::getDataValue('last_customer_integration_date', $user->id) ?></td>
                            </tr>
                            <tr>
                                <td>Subscribers</td>
                                <td><?= IntegrationData::getDataValue('LAST_SUBSCRIBER_INTEGRATION_DATE', $user->id) ?></td>
                            </tr>
                            <tr>
                                <td>SMS subscribers</td>
                                <td><?= IntegrationData::getDataValue('LAST_PHONESUBSCRIBER_INTEGRATION_DATE', $user->id) ?></td>
                            </tr>
                        </table>
                    </div>

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
                        <?php echo Html::beginForm(\yii\helpers\Url::toRoute(['admin/save-product-feed', 'id' => $user->id]), 'post') ?>

                        <table class="table">
                            <?php
                                $stockIdsArray = $user->config->getStockIdsArray();
                                // var_dump($stockIdsArray);
                            ?>
<?php if ($stocks) {?>
<?php foreach ($stocks as $stock) {?>
                                    <tr>
                                        <td style="width:34px;"><?php echo Html::checkbox('Settings[stock_ids_array][]', in_array($stock['stock_id'], $stockIdsArray), ['class' => 'form-control', 'id' => 'stock_ids', 'value' => $stock['stock_id']]); ?></td>
                                        <td><?php echo Html::label("Pobieraj stany z: " . $stock['stock_name'], 'stock_ids'); ?></td>
                                    </tr>
                                <?php }?>
<?php }?>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_image]', $user->config->get('product_image'), ['class' => 'form-control', 'id' => 'product_image']); ?></td>
                                <td><?php echo Html::label("Zdjęcie", 'product_image'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_description]', $user->config->get('product_description'), ['class' => 'form-control', 'id' => 'product_description']); ?></td>
                                <td><?php echo Html::label("Opis produktu", 'product_description'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_brand]', $user->config->get('product_brand'), ['class' => 'form-control', 'id' => 'product_brand']); ?></td>
                                <td><?php echo Html::label("Marka produktu", 'product_brand'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_stock]', $user->config->get('product_stock'), ['class' => 'form-control', 'id' => 'product_stock']) ?></td>
                                <td><?php echo Html::label("Stan magazynowy produktu", 'product_stock') ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_price_before_discount]', $user->config->get('product_price_before_discount'), ['class' => 'form-control', 'id' => 'product_price_before_discount']); ?></td>
                                <td><?php echo Html::label("Cena przed obniżką", 'product_price_before_discount'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_price_buy]', $user->config->get('product_price_buy'), ['class' => 'form-control', 'id' => 'product_price_buy']); ?></td>
                                <td><?php echo Html::label("Cena zakupu", 'product_price_buy'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_categorytext]', $user->config->get('product_categorytext'), ['class' => 'form-control', 'id' => 'product_categorytext']); ?></td>
                                <td><?php echo Html::label("Kategoria", 'product_categorytext'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_line]', $user->config->get('product_line'), ['class' => 'form-control', 'id' => 'product_line']); ?></td>
                                <td><?php echo Html::label("Linia produktu", 'product_line'); ?></td>
                            </tr>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_line_omnibus]', $user->config->get('product_line_omnibus'), ['class' => 'form-control', 'id' => 'product_line_omnibus']); ?></td>
                                <td><?php echo Html::label("Ceny omnibusowe (agreguj zamiast linii produktowej)", 'product_line_omnibus'); ?></td>
                            </tr>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_variant]', $user->config->get('product_variant'), ['class' => 'form-control', 'id' => 'product_variant']); ?></td>
                                <td><?php echo Html::label("Wariant produktu", 'product_variant'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[product_parameter]', $user->config->get('product_parameter'), ['class' => 'form-control', 'id' => 'product_parameter']); ?></td>
                                <td><?php echo Html::label("Parametry produktu", 'product_parameter'); ?></td>
                            </tr>


                            <?php if ($user->config->get('feature_enabled_product_displayed_code')) : ?>
                                <tr>
                                    <td><?php echo Html::checkbox('Settings[product_displayed_code]', $user->config->get('product_displayed_code'), ['class' => 'form-control', 'id' => 'product_displayed_code']); ?></td>
                                    <td><?php echo Html::label("Adnotacja produktu", 'product_displayed_code'); ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($user->config->get('feature_enabled_product_note')) : ?>
                                <tr>
                                    <td><?php echo Html::checkbox('Settings[product_note]', $user->config->get('product_note'), ['class' => 'form-control', 'id' => 'product_note']); ?></td>
                                    <td><?php echo Html::label("Kod produktu", 'product_note'); ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($user->config->get('feature_enabled_product_url_lang_variants')) : ?>
                                <tr>
                                    <td><?php echo Html::checkbox('Settings[product_url_lang_variants]', $user->config->get('product_url_lang_variants'), ['class' => 'form-control', 'id' => 'product_url_lang_variants']); ?></td>
                                    <td><?php echo Html::label("URL wsparcie wariantów językowych", 'product_url_lang_variants'); ?></td>
                                </tr>
                            <?php endif; ?>

				<?php if ($user->config->get('feature_enabled_product_aggregate_sizes_as_products')) : ?>
					<tr>
						<td><?php echo Html::checkbox('Settings[product_aggregate_sizes_as_products]', $user->config->get('product_aggregate_sizes_as_products'), ['class' => 'form-control', 'id' => 'product_aggregate_sizes_as_products']); ?></td>
						<td><?php echo Html::label("Agreguj rozmiary produktowe jako osobne produkty", 'product_aggregate_sizes_as_products'); ?></td>
					</tr>
				<?php endif; ?>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[feature_enabled_product_displayed_code]', $user->config->get('feature_enabled_product_displayed_code'), ['class' => 'form-control', 'id' => 'feature_enabled_product_displayed_code']); ?></td>
                                <td><?php echo Html::label("[ADMIN] Feature - Adnotacja produktu", 'feature_enabled_product_displayed_code'); ?></td> 
                            </tr>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[feature_enabled_product_note]', $user->config->get('feature_enabled_product_note'), ['class' => 'form-control', 'id' => 'feature_enabled_product_note']); ?></td>
                                <td><?php echo Html::label("[ADMIN] Feature - Kod produktu", 'feature_enabled_product_note'); ?></td> 
                            </tr>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[feature_enabled_product_url_lang_variants]', $user->config->get('feature_enabled_product_url_lang_variants'), ['class' => 'form-control', 'id' => 'feature_enabled_product_url_lang_variants']); ?></td>
                                <td><?php echo Html::label("[ADMIN] Feature - URL wsparcie wariantów językowych", 'feature_enabled_product_url_lang_variants'); ?></td> 
                            </tr>
				<tr>
					<td><?php echo Html::checkbox('Settings[feature_enabled_product_aggregate_sizes_as_products]', $user->config->get('feature_enabled_product_aggregate_sizes_as_products'), ['class' => 'form-control', 'id' => 'feature_enabled_product_aggregate_sizes_as_products']); ?></td>
					<td><?php echo Html::label("[ADMIN] Feature - Agreguj rozmiary produktowe jako osobne produkty", 'feature_enabled_product_aggregate_sizes_as_products'); ?></td>
				</tr>
                        </table>
                        <div class="form-group">
                            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?php echo Html::endForm() ?>
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
                        <?php echo Html::beginForm(\yii\helpers\Url::toRoute(['admin/save-customer-feed', 'id' => $user->id]), 'post') ?>
                        <table class="table">
                            <tr>
                                <td style="width:34px;"><?php echo Html::checkbox('Settings[customer_feed_email]', $user->config->get('customer_feed_email'), ['class' => 'form-control', 'id' => 'customer_feed_email']); ?></td>
                                <td><?php echo Html::label("Email klienta", 'customer_feed_email'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_feed_registration]', $user->config->get('customer_feed_registration'), ['class' => 'form-control', 'id' => 'customer_feed_registration']); ?></td>
                                <td><?php echo Html::label("Data rejestracji", 'customer_feed_registration'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_feed_first_name]', $user->config->get('customer_feed_first_name'), ['class' => 'form-control', 'id' => 'customer_feed_first_name']); ?></td>
                                <td><?php echo Html::label("Imię klienta", 'customer_feed_first_name'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_feed_last_name]', $user->config->get('customer_feed_last_name'), ['class' => 'form-control', 'id' => 'customer_feed_last_name']) ?></td>
                                <td><?php echo Html::label("Nazwisko klienta", 'customer_feed_last_name') ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_zip_code]', $user->config->get('customer_zip_code'), ['class' => 'form-control', 'id' => 'customer_zip_code']); ?></td>
                                <td><?php echo Html::label("Kod pocztowy klienta", 'customer_zip_code'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_phone]', $user->config->get('customer_phone'), ['class' => 'form-control', 'id' => 'customer_phone']); ?></td>
                                <td><?php echo Html::label("Numer telefonu klienta", 'customer_phone'); ?></td>
                            </tr>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_language]', $user->config->get('customer_language'), ['class' => 'form-control', 'id' => 'customer_language']); ?></td>
                                <td><?php echo Html::label("Język klienta", 'customer_language'); ?></td>
                            </tr>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_country]', $user->config->get('customer_country'), ['class' => 'form-control', 'id' => 'customer_country']); ?></td>
                                <td><?php echo Html::label("Kraj pochodzenia klienta", 'customer_country'); ?></td>
                            </tr>

                            <tr>
                                <td><?php echo Html::checkbox('Settings[customer_tags]', $user->config->get('customer_tags'), ['class' => 'form-control', 'id' => 'customer_tags']); ?></td>
                                <td><?php echo Html::label("Tagi klienta", 'customer_tags'); ?></td>
                            </tr>

                            <tr>
                                <td></td>
                                <td><h3>Zgody marketingowe pobierane ze sklepu:</h3></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td><?php echo Html::dropDownList('Settings[customer_default_approvals_shop_id]', $user->config->get('customer_default_approvals_shop_id') ? $user->config->get('customer_default_approvals_shop_id') : 1, ArrayHelper::map($shops, 'shop_id', 'shop_name'), ['class' => 'form-control', 'id' => 'customer_default_approvals_shop_id']); ?></td>
                            </tr>

                        </table>
                        <div class="form-group">
                            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>
                        <?php echo Html::endForm() ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel">
                <div class="panel-body">
                    <div class="panel-title">
                        <h2 class="panel-heading">
                            Feed zamówień
                        </h2>
                    </div>

                    <div class="panel-body">
                        <?php echo Html::beginForm(\yii\helpers\Url::toRoute(['admin/save-order-feed', 'id' => $user->id]), 'post') ?>
                        
                        <table class="table">
                            <tr>
                                <td style="width:34px;"><?php echo Html::checkbox('Settings[feature_enabled_order_currency_conversion]', $user->config->get('feature_enabled_order_currency_conversion'), ['class' => 'form-control', 'id' => 'feature_enabled_order_currency_conversion']); ?></td>
                                <td><?php echo Html::label("[ADMIN] Feature - Przewalutowanie", 'feature_enabled_order_currency_conversion'); ?></td>
                            </tr>
                        </table>

                        <div class="form-group">
                            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
                        </div>

                        <?php echo Html::endForm() ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel">
                <div class="panel-body">
                    <div class="panel-title">
                        <h2 class="panel-heading">
                            Dodatkowe ustawienia tylko dla admina
                        </h2>
                    </div>
                    <div class="panel-body">
                        <?= $user->getUserDataValue('api3_key') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
