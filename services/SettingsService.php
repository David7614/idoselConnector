<?php

namespace app\services;

use Yii;
use app\models\User;
use app\modules\idosellv3\models\ApiClient;
use yii\helpers\ArrayHelper;

class SettingsService
{
    public function saveProductFeed($user = null)
    {
        $settings = Yii::$app->request->post('Settings');

        $product_settings = [
            'product_image',
            'product_description',
            'product_brand',
            'product_stock',
            'product_price_before_discount',
            'product_price_buy',
            'product_categorytext',
            'product_line',
            'product_variant',
            'product_parameter',
            'stock_ids',
            'product_line_omnibus',
            'feature_enabled_product_displayed_code',
            'feature_enabled_product_note',
            'feature_enabled_product_url_lang_variants',
            'feature_enabled_product_aggregate_sizes_as_products'
        ];

        if (!$user) {
            $user = User::findIdentity(Yii::$app->user->id);
        } 

        if ($user->config->get('feature_enabled_product_displayed_code'))  {
            $product_settings[] = 'product_displayed_code';
        }

        if ($user->config->get('feature_enabled_product_note'))  {
            $product_settings[] = 'product_note';
        }

        if ($user->config->get('feature_enabled_product_url_lang_variants'))  {
            $product_settings[] = 'product_url_lang_variants';
        }

        if ($user->config->get('feature_enabled_product_aggregate_sizes_as_products'))  {
            $product_settings[] = 'product_aggregate_sizes_as_products';
        }

        if (isset($settings['stock_ids_array'])) {
            $settings['stock_ids'] = implode(',', $settings['stock_ids_array']);
        }
        unset($settings['stock_ids_array']);

        list($ok, $errors) = $this->saveSettings($product_settings, $settings, $user);

        if (! $ok) {
            Yii::$app->session->addFlash('error', 'Podczas przetwarzania zapytania wystąpiły błędy: <br>' . implode('<br>', $errors));
            return false;
        }

        Yii::$app->session->addFlash('success', 'Wysyłanie zapytania powiodło się');

        return true;
    }

    public function saveCustomerFeed($user = null)
    {
        $settings = Yii::$app->request->post('Settings');

        $product_settings = [
            'customer_feed_email',
            'customer_feed_registration',
            'customer_feed_first_name',
            'customer_feed_last_name',
            'customer_zip_code',
            'customer_phone',
            'customer_tags',
            'customer_language',
            'customer_country',
            'customer_default_approvals_shop_id',
        ];

        list($ok, $errors) = $this->saveSettings($product_settings, $settings, $user);

        if (!$ok) {
            Yii::$app->session->addFlash('error', 'Podczas przetwarzania zapytania wystąpiły błędy: <br>' . implode('<br>', $errors));
            return false;
        }

        Yii::$app->session->addFlash('success', 'Wysyłanie zapytania powiodło się');

        return true;
    }

    public function saveOrderFeed($user = null)
    {
        $settings = Yii::$app->request->post('Settings');

        $feed_settings = [
            'feature_enabled_order_currency_conversion',
        ];

        list($ok, $errors) = $this->saveSettings($feed_settings, $settings, $user);

        if (!$ok) {
            Yii::$app->session->addFlash('error', 'Podczas przetwarzania zapytania wystąpiły błędy: <br>' . implode('<br>', $errors));
            return false;
        }

        Yii::$app->session->addFlash('success', 'Wysyłanie zapytania powiodło się');

        return true;
    }

    private function saveSettings($settings_array, $settings_post, $user = null)
    {
        if (!$user) {
            $user = User::findIdentity(Yii::$app->user->id);
        }

        $ok = true;

        if ($user == null) {
            $errors = ['User is not logged in'];

            return [$ok, $errors];
        }

        $selected_product_settings = [];
        $errors                    = [];

        if (! is_array($settings_post)) {
            foreach ($settings_array as $item) {
                if (! $user->config->set($item, '')) {
                    $ok       = false;
                    $errors[] = "Dodawanie konfiguracji '{$item}' nie powiodło się";
                    continue;
                }
            }

            return [$ok, $errors];
        }

        foreach ($settings_post as $key => $value) {
            if (! $user->config->set($key, $value)) {
                $ok       = false;
                $errors[] = "Dodawanie konfiguracji '{$key}' nie powiodło się";
                continue;
            }
            $selected_product_settings[] = $key;
        }

        $difference = array_diff($settings_array, $selected_product_settings);

        foreach ($difference as $key => $value) {
            if ($value === 'customer_country') {
                var_dump($key);
                var_dump($value);
                if (! $user->config->set($value, '0')) {
                    $ok       = false;
                    $errors[] = "Dodawanie konfiguracji '{$value}' nie powiodło się";
                    continue;
                }
                continue;
            }

            if (! $user->config->set($value, '')) {
                $ok       = false;
                $errors[] = "Dodawanie konfiguracji '{$value}' nie powiodło się";
                continue;
            }
        }

        $shopId = $user->getCustomerShopId();

        if (!$shopId) {
            $marketingShopId = $settings_post['customer_default_approvals_shop_id'] ?? null;

            if ($marketingShopId) {
                $shopsData = $this->fetchShopsData($user);
                $user->setCustomerShopUrl($this->getShopMarketingUrl($shopsData, $user, $marketingShopId));
            }
        }

        return [$ok, $errors];
    }

    private function fetchShopsData($user)
    {
        $client = new ApiClient($user->username, $user->getApiKey());
        $res = $client->sendRequest('/api/admin/v3/system/config');
        return $res['shops'];
    }

    public function checkShopUrl($user)
    {
        /** @var \app\models\User $user */

        $shopUrl = $user->getShopUrl();

        if (!$shopUrl) {
            $shopId = $user->getCustomerShopId();
            $shopsData = $this->fetchShopsData($user);
            $this->saveShopUrl($shopId, $user, $shopsData);
        }
    }

    private function getUrlByShopId($shopId, $shopsData)
    {
        $shops = ArrayHelper::map($shopsData, 'shop_id', 'shop_name');

        if ($shopId === '0') {
            return null;
        }

        if (!isset($shops[$shopId])) {
            return null;
        }

        return $shops[$shopId];
    }

    private function getShopMarketingUrl($shopsData, $user, $shopId = null)
    {
        $marketingShopId = $shopId ? $shopId : $user->config->get('customer_default_approvals_shop_id');

        if (!$marketingShopId) {
            return null;
        }

        return $this->getUrlByShopId($marketingShopId, $shopsData);
    }

    private function getShopUrl($customerShopId, $shopsData, $user)
    {
        $url = $this->getUrlByShopId($customerShopId, $shopsData);


        return $url ?? $this->getShopMarketingUrl($shopsData, $user);
    }

    public function saveShopUrl($customerShopId, $user, $shopsData)
    {
        $user->setCustomerShopUrl($this->getShopUrl($customerShopId, $shopsData, $user));
    }
}
