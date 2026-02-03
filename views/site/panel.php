<?php

use kartik\date\DatePicker;
use yii\helpers\ArrayHelper;
use \yii\helpers\Html;
?>

<div class="row">
    <div class="col-half">
        <div class="section-title">Samba ustawienia</div>
        <div class="section-desc">
            Skonfiguruj integrację z Samba.ai. Wprowadź wymagane dane i wybierz odpowiednie opcje.
        </div>
        <?php echo Html::beginForm('', 'post') ?>
        <div class="form-group">
            <div class="label-with-hint">
                <?= Html::label('Trackpoint', 'trackpoint') ?>
                <span class="info-tooltip" tabindex="0">
                    <span class="info-icon">&#x1F6C8;</span>
                    <span class="tooltip-text">
                        Trackpoint ID to indywidualny numer identyfikatora.<br>
                        Znajdziesz go w panelu samba.ai!<br>
                        Przejdź do <b>Ustawienia sklepu &gt; Przegląd</b>.<br>
                        Twój trackpoint ID znajduje się w sekcji <b>Konto sklepu</b>.
                    </span>
                </span>
            </div>
            <?php echo Html::textInput('trackpoint', $user->config->get('trackpoint'), ['class' => 'form-control', 'id' => 'trackpoint']) ?>
        </div>

        <div class="form-group">
            <div class="label-with-hint">
                <?= Html::label('Czy chcesz korzystać z naszego feeda produktowego?', 'product_feed_disable') ?>
                <span class="info-tooltip" tabindex="0">
                    <span class="info-icon">&#x1F6C8;</span>
                    <span class="tooltip-text">
                        Jest to opcja dla osób, które chcą korzystać z własnego, niestandardowego skryptu do
                        generowania bazy
                        produktów
                    </span>
                </span>
            </div>
            <?= Html::dropDownList(
                'product_feed_disable',
                $user->config->get('product_feed_disable'),
                ['0' => 'Feed produktowy włączony', '1' => 'Feed produktowy wyłączony'],
                ['class' => 'form-control', 'id' => 'product_feed_disable']
            ); ?>
        </div>

        <div class="form-group">
            <?php echo Html::label('Wersja językowa danych z e-sklepu:', 'selected_language') ?>
            <?php echo Html::dropDownList('selected_language', $user->config->get('selected_language'), ArrayHelper::map($languages, 'lang_id', 'lang_name'), ['class' => 'form-control', 'id' => 'selected_language']); ?>
        </div>

        <div class="form-group">
            <?php echo Html::label('Agreguj grupy produktowe jako warianty', 'aggregate_groups_as_variants') ?>
            <?php echo Html::dropDownList(
                'aggregate_groups_as_variants',
                $user->config->get('aggregate_groups_as_variants'),
                ['0' => 'Nie', '1' => 'Tak'],
                ['class' => 'form-control', 'id' => 'aggregate_groups_as_variants']
            ); ?>
        </div>

        <div class="form-group">
            <?php echo Html::label('Pobieraj dane produktowe i transakcyjne z:') ?>
            <?php echo Html::dropDownList(
                'customer_set_shop_id',
                $user->config->get('customer_set_shop_id'),
                ArrayHelper::map($shops, 'shop_id', 'shop_name') + ['0' => 'Wszystkich sklepów'],
                ['class' => 'form-control', 'id' => 'customer_set_shop_id']
            ); ?>
        </div>

        <div class="form-group" style="margin-top: -8px;">
            <?php echo Html::label('Zgody marketingowe pobierane ze sklepu:') ?>
            <?php echo Html::dropDownList('Settings[customer_default_approvals_shop_id]', $user->config->get('customer_default_approvals_shop_id') ? $user->config->get('customer_default_approvals_shop_id') : 1, ArrayHelper::map($shops, 'shop_id', 'shop_name'), ['class' => 'form-control', 'id' => 'customer_default_approvals_shop_id']); ?>
        </div>

        <div class="form-group">
            <?php echo Html::label('Pobieraj stany', 'get_quantity_from') ?>
            <?php echo Html::dropDownList(
                'get_quantity_from',
                $user->config->get('get_quantity_from'),
                ['0' => 'Domyślnie', '1' => 'Z oferty dyspozycyjnej (productSizesDispositions)'],
                ['class' => 'form-control', 'id' => 'get_quantity_from']
            ); ?>
        </div>

        <div class="form-group">
            <?php echo Html::label('Używaj menu kategorii', 'get_menu_from') ?>
            <?php echo Html::dropDownList(
                'get_menu_from',
                $user->config->get('get_menu_from'),
                [
                    '0' => 'Standardowe kategorie idosell',
                    '1' => 'Węzeł menu 1 (z reguły domyślny)',
                    '2' => 'Węzeł menu 2',
                    '3' => 'Węzeł menu 3',
                    '4' => 'Węzeł menu 4',
                    '5' => 'Węzeł menu 5'
                ],
                ['class' => 'form-control', 'id' => 'aggregate_groups_as_variants']
            ); ?>
        </div>

        <div class="form-group">
            <?php echo Html::label('Generuj dane zamówień od:', 'orders_date_from') ?>
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
            <?php echo Html::label('Typ eksportu danych', 'export_type') ?>
            <?php echo Html::dropDownList(
                'export_type',
                $user->config->get('export_type'),
                ['0' => 'Pełna baza', '1' => 'Inkrementalny (rekomendowany)'],
                ['class' => 'form-control', 'id' => 'export_type']
            ); ?>
        </div>

        <div class="form-group">
            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
        </div>
        <?php echo Html::endForm() ?>
    </div>

    <div class="col-half">
        <div class="section-title">Wygenerowane feedy</div>

        <div class="section-desc">
            Po zapisaniu konfiguracji poniżej pojawią się adresy feedów oraz statusy pobranych danych.
            Skopiuj je i wklej do aplikacji Samba.ai
            w odpowiednich miejscach.
        </div>

        <table class="feeds-table" style="table-layout: fixed; width: 100%;">
            <colgroup>
                <col style="width: 26%;">
                <col style="width: 30%;">
                <col style="width: 22%;">
                <col style="width: 23%;">
            </colgroup>

            <thead>
                <tr>
                    <th>Nazwa feeda</th>
                    <th>URL</th>
                    <th>Dane (suma)</th>
                    <th>Ostatnia synch.</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>Produkty</td>
                    <td>
                        <button class="copy-feed-btn" data-feed-url="<?php echo $urls['products'] ?>">Skopiuj link</button>
                    </td>
                    <td><?php echo $user->countDatabaseElements('products') ?></td>
                    <td><?php echo $filesInfo['products']['status'] ?> <?php echo $filesInfo['products']['elements'] ?></td>
                </tr>
                <tr>
                    <td>Zamówienia</td>
                    <td>
                        <button class="copy-feed-btn" data-feed-url="<?php echo $urls['orders'] ?>">Skopiuj link</button>
                    </td>
                    <td><?php echo $user->countDatabaseElements('order') ?></td>
                    <td><?php echo $filesInfo['order']['status'] ?> <?php echo $filesInfo['order']['elements'] ?></td>
                </tr>
                <tr>
                    <td>Kategorie</td>
                    <td>
                        <button class="copy-feed-btn" data-feed-url="<?php echo $urls['categories'] ?>">Skopiuj link</button>
                    </td>
                    <td>nd</td>
                    <td><?php echo $filesInfo['category']['status'] ?> <?php echo $filesInfo['category']['elements'] ?></td>
                </tr>
                <tr>
                    <td>Klienci</td>
                    <td>
                        <button class="copy-feed-btn" data-feed-url="<?php echo $urls['customers'] ?>">Skopiuj link</button>
                    </td>
                    <td><?php echo $user->countDatabaseElements('customer') ?></td>
                    <td><?php echo $filesInfo['customer']['status'] ?> <?php echo $filesInfo['customer']['elements'] ?></td>
                </tr>
            </tbody>
        </table>

        <div class="section-title" style="margin-top: 36px;">Instrukcja wideo</div>
        <div class="section-desc" style="margin-bottom: 18px;">
            Sprawdź, jak łatwo zintegrować się z Samba.ai i zamienić dane transakcyjne w wymierną korzyść dla
            Twojego
            biznesu!
        </div>
        <a href="https://samba.wistia.com/medias/k1d4v0a1i2?wvideo=k1d4v0a1i2" target="_blank"
            class="video-thumb-link">
            <img src="https://embed-ssl.wistia.com/deliveries/a0db7a8798050d029698f347f0a84be9.jpg?image_play_button_size=2x&image_crop_resized=960x540&image_play_button_rounded=1&image_play_button_color=fe9a38e0"
                alt="Instrukcja wideo Samba.ai" width="400" height="225"
                style="width: 100%; max-width: 420px; height: auto; border-radius: 12px; display: block; margin: 0 auto; box-shadow: 0 4px 24px #e5e7eb55;">
        </a>
        <div style="margin-top: 32px;">
            <a href="https://doc.samba.ai/knowledge-base/wsparcie/?lang=pl" target="_blank"
                class="header-btn docs-btn support-btn"
                style="text-decoration: none; font-size: 1.22rem; font-weight: 700;">
                Masz pytania? Napisz do nas!
            </a>
        </div>
    </div>
</div>

<div class="row">
    <!-- Feed produktowy -->
    <div class="col-half">
        <div class="section-title">Feed produktowy</div>

        <div class="section-desc">
            Zaznacz, jakie dane mają być eksportowane w feedzie produktowym.
        </div>

        <?php echo Html::beginForm(\yii\helpers\Url::toRoute(['site/save-product-feed']), 'post') ?>
        <div class="checkbox-list">
            <?php $stockIdsArray = $user->config->getStockIdsArray(); ?>
            <?php if ($stocks) : ?>
                <?php foreach ($stocks as $stock) : ?>
                    <div class="checkbox-row">
                        <?php echo Html::checkbox('Settings[stock_ids_array][]', in_array($stock['stock_id'], $stockIdsArray), ['class' => 'form-control', 'id' => 'stock_ids_' . $stock['stock_id'], 'value' => $stock['stock_id']]); ?>
                        <?php echo Html::label("Pobieraj stany z: " . $stock['stock_name'], 'stock_ids_' . $stock['stock_id']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_image]', $user->config->get('product_image'), ['class' => 'form-control', 'id' => 'product_image']); ?>
                <?php echo Html::label("Zdjęcie", 'product_image'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_description]', $user->config->get('product_description'), ['class' => 'form-control', 'id' => 'product_description']); ?>
                <?php echo Html::label("Opis produktu", 'product_description'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_brand]', $user->config->get('product_brand'), ['class' => 'form-control', 'id' => 'product_brand']); ?>
                <?php echo Html::label("Marka produktu", 'product_brand'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_stock]', $user->config->get('product_stock'), ['class' => 'form-control', 'id' => 'product_stock']) ?>
                <?php echo Html::label("Stan magazynowy produktu", 'product_stock') ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_price_before_discount]', $user->config->get('product_price_before_discount'), ['class' => 'form-control', 'id' => 'product_price_before_discount']); ?>
                <?php echo Html::label("Cena przed obniżką", 'product_price_before_discount'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_price_buy]', $user->config->get('product_price_buy'), ['class' => 'form-control', 'id' => 'product_price_buy']); ?>
                <?php echo Html::label("Cena zakupu", 'product_price_buy'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_categorytext]', $user->config->get('product_categorytext'), ['class' => 'form-control', 'id' => 'product_categorytext']); ?>
                <?php echo Html::label("Kategoria", 'product_categorytext'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_line]', $user->config->get('product_line'), ['class' => 'form-control', 'id' => 'product_line']); ?>
                <?php echo Html::label("Linia produktu", 'product_line'); ?>
            </div>
            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_line_omnibus]', $user->config->get('product_line_omnibus'), ['class' => 'form-control', 'id' => 'product_line_omnibus']); ?>
                <?php echo Html::label("Ceny omnibusowe (agreguj zamiast linii produktowej)", 'product_line_omnibus'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_variant]', $user->config->get('product_variant'), ['class' => 'form-control', 'id' => 'product_variant']); ?>
                <?php echo Html::label("Warianty produktu", 'product_variant'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[product_parameter]', $user->config->get('product_parameter'), ['class' => 'form-control', 'id' => 'product_parameter']); ?>
                <?php echo Html::label("Parametry produktu", 'product_parameter'); ?>
            </div>


            <?php if ($user->config->get('feature_enabled_product_displayed_code')) : ?>
                <div class="checkbox-row">
                    <?php echo Html::checkbox('Settings[product_displayed_code]', $user->config->get('product_displayed_code'), ['class' => 'form-control', 'id' => 'product_displayed_code']); ?>
                    <?php echo Html::label("Adnotacja produktu", 'product_displayed_code'); ?>
                </div>
            <?php endif; ?>

            <?php if ($user->config->get('feature_enabled_product_note')) : ?>
                <div class="checkbox-row">
                    <?php echo Html::checkbox('Settings[product_note]', $user->config->get('product_note'), ['class' => 'form-control', 'id' => 'product_note']); ?>
                    <?php echo Html::label("Kod produktu", 'product_note'); ?>
                </div>
            <?php endif; ?>

            <?php if ($user->config->get('feature_enabled_product_url_lang_variants')) : ?>
                <div class="checkbox-row">
                    <?php echo Html::checkbox('Settings[product_url_lang_variants]', $user->config->get('product_url_lang_variants'), ['class' => 'form-control', 'id' => 'product_url_lang_variants']); ?>
                    <?php echo Html::label("URL wsparcie wariantów językowych", 'product_url_lang_variants'); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
        </div>
        <?php echo Html::endForm() ?>
    </div>

    <!-- Feed klientów -->
    <div class="col-half">
        <div class="section-title">Feed klientów</div>
        <div class="section-desc">
            Zaznacz, jakie dane mają być eksportowane w feedzie klientów.
        </div>
        <?php echo Html::beginForm(\yii\helpers\Url::toRoute(['site/save-customer-feed']), 'post') ?>
        <div class="checkbox-list">
            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_feed_email]', $user->config->get('customer_feed_email'), ['class' => 'form-control', 'id' => 'customer_feed_email']); ?>
                <?php echo Html::label("Email klienta", 'customer_feed_email'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_feed_registration]', $user->config->get('customer_feed_registration'), ['class' => 'form-control', 'id' => 'customer_feed_registration']); ?>
                <?php echo Html::label("Data rejestracji", 'customer_feed_registration'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_feed_first_name]', $user->config->get('customer_feed_first_name'), ['class' => 'form-control', 'id' => 'customer_feed_first_name']); ?>
                <?php echo Html::label("Imię klienta", 'customer_feed_first_name'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_feed_last_name]', $user->config->get('customer_feed_last_name'), ['class' => 'form-control', 'id' => 'customer_feed_last_name']) ?>
                <?php echo Html::label("Nazwisko klienta", 'customer_feed_last_name') ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_zip_code]', $user->config->get('customer_zip_code'), ['class' => 'form-control', 'id' => 'customer_zip_code']); ?>
                <?php echo Html::label("Kod pocztowy klienta", 'customer_zip_code'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_phone]', $user->config->get('customer_phone'), ['class' => 'form-control', 'id' => 'customer_phone']); ?>
                <?php echo Html::label("Numer telefonu klienta", 'customer_phone'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_language]', $user->config->get('customer_language'), ['class' => 'form-control', 'id' => 'customer_language']); ?>
                <?php echo Html::label("Język klienta", 'customer_language'); ?>
            </div>

            <div class="checkbox-row">
                <?php echo Html::checkbox('Settings[customer_country]', $user->config->get('customer_country'), ['class' => 'form-control', 'id' => 'customer_country']); ?>
                <?php echo Html::label("Kraj pochodzenia klienta", 'customer_country'); ?>
            </div>

            <div class="checkbox-row">
                <td><?php echo Html::checkbox('Settings[customer_tags]', $user->config->get('customer_tags'), ['class' => 'form-control', 'id' => 'customer_tags']); ?></td>
                <td><?php echo Html::label("Tagi klienta", 'customer_tags'); ?></td>
            </div>
        </div>

        <div class="form-group">
            <?php echo Html::submitButton('Zapisz', ['class' => 'btn btn-primary']) ?>
        </div>
        <?php echo Html::endForm() ?>
    </div>

    <div class="copy-popup" id="copyPopup">
        Skopiowano do schowka!
    </div>
</div>
