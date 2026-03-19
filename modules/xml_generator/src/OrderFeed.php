<?php
namespace app\modules\xml_generator\src;

use app\models\AppConfig;
use app\models\IntegrationData;
use app\models\Orders;
use app\models\Ordersv2;
use app\models\Queue;
use app\modules\idosellv3\models\ApiClient;
use app\services\FeedStorageService;
use yii;

class OrderFeed extends XmlFeed
{
    use DebugTrait;

    const API_RESULT_COUNT = 100;
    const XML_PAGE_SIZE    = 50000;

    private $request_parameters = [];
    private $apiMethod = '/api/admin/v4/orders/orders/get';
    private $_client;

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------

    public function generate($what = null): int
    {
        if ($what == 'objects') {
            $this->debug('Phase: fetch objects from API');
            return $this->createOrderObjects();
        }

        if (FeedStorageService::isConfigured()) {
            return $this->generateViaStorage();
        }

        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        if (!$this->isFinished()) {
            $created = $this->createOrAddTempOrderXml($temp);
        } elseif (!file_exists($temp)) {
            $this->debug('Temp file missing — resetting XML phase');
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->_queue->save();
            $created = $this->createOrAddTempOrderXml($temp);
        } else {
            $created = $this->createOrderXml($file, $temp);
        }

        return $created;
    }

    // -------------------------------------------------------------------------
    // Storage (MinIO) path
    // -------------------------------------------------------------------------

    private function getStorageKey(bool $temp = false): string
    {
        $ext = $temp ? '.xml.tmp' : '.xml';
        return 'order/' . $this->_user->uuid . '/order' . $ext;
    }

    private function generateViaStorage(): int
    {
        $storage = FeedStorageService::create();
        $tempKey = $this->getStorageKey(true);
        $fileKey = $this->getStorageKey(false);

        if (!$this->isFinished()) {
            return $this->createOrAddTempOrderXmlViaStorage($storage, $tempKey, $fileKey);
        } elseif (!$storage->exists($tempKey)) {
            $this->debug('Temp missing in storage — resetting XML phase');
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->_queue->save();
            return $this->createOrAddTempOrderXmlViaStorage($storage, $tempKey, $fileKey);
        } else {
            return $this->createOrderXmlViaStorage($storage, $fileKey, $tempKey);
        }
    }

    private function createOrAddTempOrderXmlViaStorage(FeedStorageService $storage, string $tempKey, string $fileKey): int
    {
        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage     = $this->_queue->max_page;
        $page_size                  = self::XML_PAGE_SIZE;

        $ordersQuery   = Orders::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);
        $ordersQueryv2 = Ordersv2::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;

        if ($integrationDataMaxPage == 0) {
            $ordersQueryAll   = $ordersQuery->count();
            $ordersv2QueryAll = $ordersQueryv2->count();
            $pages            = ceil($ordersQueryAll / $page_size);
            $pagesv2          = ceil($ordersv2QueryAll / $page_size);
            if ($pagesv2 > $pages) {
                $pages = $pagesv2;
            }
            $this->_queue->max_page = $pages;
            $integrationDataMaxPage = $pages;
            $this->_queue->page     = $page;
            $this->_queue->save();
        }

        $this->debug(sprintf('XML via storage — page %d / %d', $page, $integrationDataMaxPage));

        $existingContent = $storage->exists($tempKey) ? $storage->get($tempKey) : '';
        $buffer = '';

        $orders_db = $ordersQuery->limit($page_size)->offset($page * $page_size)->all();
        if (count($orders_db) > 0) {
            $this->debug(sprintf('Orders v1: %d records', count($orders_db)));
            foreach ($orders_db as $order) {
                if (Queue::isDisallowedEmail($order->email)) {
                    continue;
                }
                $ordChild = new \SimpleXMLElement('<ORDER/>');
                $ordChild->addChild('ORDER_ID', $order->order_id);
                $ordChild->addChild('CUSTOMER_ID', $order->customer_id);
                $ordChild->addChild('CREATED_ON', $this->getCorrectSambaDate($order->created_on));
                if ($order->status == 'finished') {
                    $ordChild->addChild('FINISHED_ON', $this->getCorrectSambaDate($order->finished_on));
                }
                $ordChild->addChild('STATUS', $order->status);
                $ordChild->addChild('EMAIL', $order->email);
                $ordChild->addChild('PHONE', str_replace(' ', '', $order->phone));
                $ordChild->addChild('ZIP_CODE', $order->zip_code);
                $ordChild->addChild('COUNTRY_CODE', $order->country_code);
                $ordItems = $ordChild->addChild('ITEMS');
                foreach ($order->getPositions() as $product) {
                    $prodItem = $ordItems->addChild('ITEM');
                    $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                    $prodItem->addChild('AMOUNT', $product['amount']);
                    $prodItem->addChild('PRICE', $product['amount'] * $product['price']);
                }
                $xml = $ordChild->asXml();
                $xml = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $xml);
                $buffer .= $xml;
            }
        }

        $orders_dbv2 = $ordersQueryv2->limit($page_size)->offset($page * $page_size)->all();
        if (count($orders_dbv2) > 0) {
            $this->debug(sprintf('Orders v2: %d records', count($orders_dbv2)));
            foreach ($orders_dbv2 as $order) {
                if (Queue::isDisallowedEmail($order->email)) {
                    continue;
                }
                $ordChild = new \SimpleXMLElement('<ORDER/>');
                $ordChild->addChild('ORDER_ID', $order->order_id);
                $ordChild->addChild('CUSTOMER_ID', $order->customer_id);
                $ordChild->addChild('CREATED_ON', $this->getCorrectSambaDate($order->created_on));
                if ($order->status == 'finished') {
                    $ordChild->addChild('FINISHED_ON', $this->getCorrectSambaDate($order->finished_on));
                }
                $ordChild->addChild('STATUS', $order->status);
                $ordChild->addChild('EMAIL', $order->email);
                $phone = (int) str_replace(' ', '', $order->phone);
                $ordChild->addChild('PHONE', $phone);
                $ordChild->addChild('ZIP_CODE', $order->zip_code);
                $ordChild->addChild('COUNTRY_CODE', $order->country_code);
                $ordItems = $ordChild->addChild('ITEMS');
                foreach ($order->getPositions() as $product) {
                    $prodItem = $ordItems->addChild('ITEM');
                    $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                    $prodItem->addChild('AMOUNT', $product['amount']);
                    $prodItem->addChild('PRICE', $product['amount'] * $product['price']);
                }
                $xml = $ordChild->asXml();
                $xml = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $xml);
                $buffer .= $xml;
            }
        }

        $page++;
        $this->_queue->page = $page;
        $this->_queue->save();

        $storage->put($tempKey, $existingContent . $buffer);

        if ($page > (int) $integrationDataMaxPage) {
            $this->debug('All pages done — finalizing XML');
            return $this->createOrderXmlViaStorage($storage, $fileKey, $tempKey);
        }

        return 1;
    }

    private function createOrderXmlViaStorage(FeedStorageService $storage, string $fileKey, string $tempKey): int
    {
        $this->debug('Finalizing XML (storage)');
        $tempContent = $storage->get($tempKey);
        $finalXml    = '<?xml version="1.0"?><ORDERS>' . $tempContent . '</ORDERS>';
        $storage->put($fileKey, $finalXml, 'application/xml');
        $storage->delete($tempKey);
        $this->debug('XML uploaded to storage: ' . $fileKey);
        return 10;
    }

    // -------------------------------------------------------------------------
    // File path helper
    // -------------------------------------------------------------------------

    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return parent::getFile($get_file_path, $temp);
    }

    // -------------------------------------------------------------------------
    // Date range checks
    // -------------------------------------------------------------------------

    public function checkOrdersDateFrom()
    {
        $dateFrom = $this->_user->getConfig()->getOrdersDateFrom();
        if (!$dateFrom) {
            return true;
        }
        $dateFrom = date('Y-m-d', strtotime($dateFrom . ' -10 day'));
        $this->debug('Checking orders date boundary: ' . $dateFrom);

        $check = Orders::find()->where(['user_id' => $this->_user->id])->andWhere(['<', 'created_on', $dateFrom])->limit(1)->one();
        if ($check) {
            $this->debugDump($check, 'Order v1 out of range');
            Orders::deleteAll(['and', ['user_id' => $this->_user->id], ['<', 'created_on', $dateFrom]]);
            IntegrationData::setData('INITIAL_ORDERS_DONE', 0, $this->_user->id);
            IntegrationData::setData('last_orders_integration_date', $dateFrom, $this->_user->id);
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->debug('Deleted orders v1 older than ' . $dateFrom . ' — recommencing');
            throw new \Exception('Orders date range reset — recommencing from ' . $dateFrom);
        }

        $check = Ordersv2::find()->where(['user_id' => $this->_user->id])->andWhere(['<', 'created_on', $dateFrom])->limit(1)->one();
        if ($check) {
            $this->debugDump($check, 'Order v2 out of range');
            Ordersv2::deleteAll(['and', ['user_id' => $this->_user->id], ['<', 'created_on', $dateFrom]]);
            IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $this->_user->id);
            IntegrationData::setData('last_orders_integration_date', $dateFrom, $this->_user->id);
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->debug('Deleted orders v2 older than ' . $dateFrom . ' — recommencing');
            throw new \Exception('Orders v2 date range reset — recommencing from ' . $dateFrom);
        }
    }

    private function checkQueueConstraints()
    {
        $this->checkOrdersDateFrom();

        if ($this->_queue->max_page == $this->_queue->page && $this->_queue->max_page != 0) {
            IntegrationData::setData('last_orders_integration_date', date('Y-m-d'), $this->_user->id);
        }

        if ($this->_queue->max_page > 0) {
            return false;
        }

        $request                           = $this->request_parameters;
        $request['params']['resultsLimit'] = 1;
        $this->debugPrint($request, 'Constraints request');

        $response = $this->_client->post($this->apiMethod, $request);
        $this->debugDump($response, 'Constraints response');

        if (empty($response) || !is_array($response)) {
            throw new \Exception('Gateway did not respond (checkQueueConstraints)');
        }

        if (isset($response['errors']) && $response['errors']['faultCode'] == 2) {
            $this->debug('API fault code 2');
            return 10;
        }

        if (!$response['resultsNumberAll']) {
            $this->debug('No results from API');
            return false;
        }

        $maxPage = ceil($response['resultsNumberAll'] / self::API_RESULT_COUNT);
        if ($this->_queue->max_page < $maxPage) {
            $this->_queue->max_page = $maxPage;
            $this->_queue->save();
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Currency helpers
    // -------------------------------------------------------------------------

    private function getCurrencyConversionEnabled()
    {
        return $this->_user->config->get('feature_enabled_order_currency_conversion') == 1;
    }

    private function getCurrency($order)
    {
        if (
            !$order['orderDetails'] ||
            !$order['orderDetails']['payments'] ||
            !$order['orderDetails']['payments']['orderCurrency'] ||
            !$order['orderDetails']['payments']['orderCurrency']['currencyId'] ||
            !$order['orderDetails']['payments']['orderCurrency']['orderCurrencyValue'] ||
            !$order['orderDetails']['payments']['orderCurrency']['billingCurrencyRate']
        ) {
            return null;
        }

        return $order['orderDetails']['payments']['orderCurrency'];
    }

    private function getConvertedPrice($price, $currencyId, $currencyValue, $currencyRate)
    {
        $mainCurrencyId = 'PLN';

        if ($currencyId === $mainCurrencyId) {
            return $price;
        }

        if ($currencyValue > 1 && $currencyRate === 1) {
            return round($price * $currencyValue, 2);
        }

        if ($currencyRate === 1) {
            return $price;
        }

        return round($price * $currencyRate, 2);
    }

    // -------------------------------------------------------------------------
    // Begin date resolution
    // -------------------------------------------------------------------------

    private function getOrdersBeginDate(): string
    {
        if ($this->_user->getIncrementalFeedFlag()) {
            return date('Y-m-d H:i:s', strtotime('-2 weeks'));
        }

        if (
            IntegrationData::getData('INITIAL_ORDERS_DONE', $this->_user->id) &&
            $lastDate = IntegrationData::getDataValue('last_orders_integration_date', $this->_user->id)
        ) {
            return date('Y-m-d H:i:s', strtotime($lastDate . ' -1 week'));
        }

        $dateFrom = $this->_user->getConfig()->getOrdersDateFrom();
        if ($dateFrom) {
            return date('Y-m-d H:i:s', strtotime($dateFrom));
        }

        $yearsBack = (int) (AppConfig::getValue(AppConfig::DEFAULT_ORDERS_YEARS_BACK) ?? 10);
        return date('Y-m-d H:i:s', strtotime("-{$yearsBack} years"));
    }

    // -------------------------------------------------------------------------
    // Object phase (API → DB)
    // -------------------------------------------------------------------------

    private function createOrderObjects()
    {
        if ($this->_user->getIncrementalFeedFlag()) {
            if ($this->_queue->page == 0) {
                Orders::deleteAll(['user_id' => $this->_user->id]);
                Ordersv2::deleteAll(['user_id' => $this->_user->id]);
                $this->debug('Incremental mode — cleared existing orders');
            }
            $date2weeksago = date('Y-m-d', strtotime('-2 weeks'));
            IntegrationData::setLastOrdersIntegrationDate($date2weeksago, $this->_user->id);
        }

        if (!$this->_user->getApiKey()) {
            throw new \Exception('No API key configured');
        }
        $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());

        $begin = $this->getOrdersBeginDate();
        $this->debug('Begin date: ' . $begin);

        if ($begin) {
            $this->request_parameters['params']['ordersRange'] = [
                'ordersDateRange' => [
                    'ordersDateType'  => 'modified',
                    'ordersDateBegin' => $begin,
                ],
            ];
        }

        if ($selectedShopId = $this->_user->config->get('customer_set_shop_id')) {
            $this->request_parameters['params']['orderSource']['shopsIds'] = $selectedShopId;
        }

        $this->checkQueueConstraints();

        $this->request_parameters['params']['resultsLimit'] = self::API_RESULT_COUNT;

        $request = $this->request_parameters;
        $request['params']['ordersBy'] = [
            ['elementName' => 'order_time', 'sortDirection' => 'ASC'],
        ];

        try {
            $request['params']['resultsPage'] = $this->_queue->page;
            $this->debugPrint($request, 'API request');

            $response = $this->_client->post($this->apiMethod, $request);
            $this->debugDump($response, 'API response');

            if ($this->_queue->page >= $this->_queue->max_page) {
                IntegrationData::setIsNew('ORDER', false, $this->_user->id);
                IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $this->_user->id);
                return 10;
            }

            if (!isset($response)) {
                $this->debug('No response — skipping page');
                $this->_queue->increasePage();
                return 1;
            }

            if (isset($response['errors']) && !empty($response['errors']['faultString'])) {
                throw new \Exception('API fault: ' . $response['errors']['faultString']);
            }

            $order_statuses_map = [
                'finished_ext'       => 'finished',
                'finished'           => 'finished',
                'new'                => 'created',
                'payment_waiting'    => 'created',
                'delivery_waiting'   => 'created',
                'on_order'           => 'created',
                'packed'             => 'created',
                'packed_fulfillment' => 'created',
                'packet_ready'       => 'created',
                'ready'              => 'created',
                'wait_for_dispatch'  => 'created',
                'joined'             => 'created',
                'packed_ready'       => 'created',
                'suspended'          => 'canceled',
                'returned'           => 'canceled',
                'missing'            => 'canceled',
                'lost'               => 'canceled',
                'false'              => 'canceled',
                'canceled'           => 'canceled',
                'complainted'        => 'canceled',
            ];

            $grabOrdersdateFrom = $this->_user->getConfig()->getOrdersDateFrom();

            foreach ($response['Results'] as $order) {
                $this->debug('Processing order: ' . $order['orderId']);

                if ($order['orderDetails']['productsResults'] == null) {
                    $this->debug('  → skipped (empty order)');
                    continue;
                }

                if ($grabOrdersdateFrom) {
                    if (strtotime($order['orderDetails']['orderAddDate']) < strtotime($grabOrdersdateFrom)) {
                        $this->debug(sprintf('  → skipped (%s older than date-from %s)', $order['orderDetails']['orderAddDate'], $grabOrdersdateFrom));
                        continue;
                    }
                }

                $status = $order_statuses_map[$order['orderDetails']['orderStatus']] ?? 'created';
                $this->debug(sprintf('  → status: %s  dispatch: %s', $status, $order['orderDetails']['orderDispatchDate']));

                $order_item                      = [];
                $order_item['order_id']          = $order['orderId'];
                $order_item['orderSerialNumber'] = $order['orderSerialNumber'];
                $order_item['customer_id']       = $order['clientResult']['clientAccount']['clientId'];
                $order_item['created_on']        = $order['orderDetails']['orderAddDate'];
                $order_item['finished_on']       = $status == 'finished' ? $order['orderDetails']['orderDispatchDate'] : null;
                if ($status == 'finished' && !$order_item['finished_on']) {
                    $order_item['finished_on'] = date('Y-m-d');
                }
                $order_item['status']       = $status;
                $order_item['email']        = $order['clientResult']['clientAccount']['clientEmail'];
                $order_item['phone']        = str_replace(' ', '', $order['clientResult']['clientAccount']['clientPhone1']);
                $order_item['zip_code']     = $order['clientResult']['clientBillingAddress']['clientZipCode'];
                $order_item['country_code'] = $order['clientResult']['clientBillingAddress']['clientCountryId'];

                $currency  = $this->getCurrency($order);
                $positions = [];

                foreach ($order['orderDetails']['productsResults'] as $product) {
                    $price = $product['productOrderPrice'];

                    if ($this->getCurrencyConversionEnabled() && $currency) {
                        $price = $this->getConvertedPrice($price, $currency['currencyId'], $currency['orderCurrencyValue'], $currency['billingCurrencyRate']);
                    }

                    $productId = $product['productId'];

                    if (
                        $this->_user->config->get('product_aggregate_sizes_as_products') == '1' &&
                        isset($product['sizeId']) &&
                        $product['sizeId'] != 'uniw' &&
                        isset($product['sizePanelName'])
                    ) {
                        $productId = $productId . '-' . $product['sizePanelName'];
                    }

                    $positions[] = [
                        'product_id' => $productId,
                        'amount'     => $product['productQuantity'],
                        'price'      => $price,
                    ];
                }

                $order_item['order_positions'] = serialize($positions);
                $order_object                  = Ordersv2::addOrder($order_item, $this->_user->id, $this->_queue->page);
                $this->debug('  → saved with id: ' . $order_object->id);
            }

            $this->_queue->increasePage();
            return 1;

        } catch (\Exception $e) {
            $this->debug('Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // XML phase (DB → file)
    // -------------------------------------------------------------------------

    private function createOrAddTempOrderXml($temp): int
    {
        touch($temp);
        $file = $this->getFile(true, false);

        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage     = $this->_queue->max_page;
        $page_size                  = self::XML_PAGE_SIZE;

        $ordersQuery   = Orders::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);
        $ordersQueryv2 = Ordersv2::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;

        if ($integrationDataMaxPage == 0) {
            $ordersQueryAll   = $ordersQuery->count();
            $ordersv2QueryAll = $ordersQueryv2->count();
            $pages            = ceil($ordersQueryAll / $page_size);
            $pagesv2          = ceil($ordersv2QueryAll / $page_size);
            if ($pagesv2 > $pages) {
                $pages = $pagesv2;
            }
            $this->_queue->max_page = $pages;
            $integrationDataMaxPage = $pages;
            $this->_queue->page     = $page;
            $this->_queue->save();
        }

        $this->debug(sprintf('XML build — page %d / %d  (offset %d)', $page, $integrationDataMaxPage, $page * $page_size));

        $file_handle = fopen($temp, 'a+');

        $orders_db = $ordersQuery->limit($page_size)->offset($page * $page_size)->all();
        if (count($orders_db) > 0) {
            $this->debug('Orders v1: ' . count($orders_db) . ' records');
            $orders = new \SimpleXmlElement('<ORDERS/>');
            foreach ($orders_db as $order) {
                if (Queue::isDisallowedEmail($order->email)) {
                    continue;
                }
                $ordChild = $orders->addChild('ORDER');
                $ordChild->addChild('ORDER_ID', $order->order_id);
                $ordChild->addChild('CUSTOMER_ID', $order->customer_id);
                $ordChild->addChild('CREATED_ON', $this->getCorrectSambaDate($order->created_on));
                if ($order->status == 'finished') {
                    $ordChild->addChild('FINISHED_ON', $this->getCorrectSambaDate($order->finished_on));
                }
                $ordChild->addChild('STATUS', $order->status);
                $ordChild->addChild('EMAIL', $order->email);
                $ordChild->addChild('PHONE', str_replace(' ', '', $order->phone));
                $ordChild->addChild('ZIP_CODE', $order->zip_code);
                $ordChild->addChild('COUNTRY_CODE', $order->country_code);
                $this->debug('  → order id: ' . $order->id);
                $ordItems = $ordChild->addChild('ITEMS');
                foreach ($order->getPositions() as $product) {
                    $prodItem = $ordItems->addChild('ITEM');
                    $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                    $prodItem->addChild('AMOUNT', $product['amount']);
                    $prodItem->addChild('PRICE', $product['amount'] * $product['price']);
                }
                fwrite($file_handle, $ordChild->asXml());
            }
        }

        $orders_dbv2 = $ordersQueryv2->limit($page_size)->offset($page * $page_size)->all();
        if (count($orders_dbv2) > 0) {
            $this->debug('Orders v2: ' . count($orders_dbv2) . ' records');
            $orders = new \SimpleXmlElement('<ORDERS/>');
            foreach ($orders_dbv2 as $order) {
                if (Queue::isDisallowedEmail($order->email)) {
                    continue;
                }
                $ordChild = $orders->addChild('ORDER');
                $ordChild->addChild('ORDER_ID', $order->order_id);
                $ordChild->addChild('CUSTOMER_ID', $order->customer_id);
                $ordChild->addChild('CREATED_ON', $this->getCorrectSambaDate($order->created_on));
                if ($order->status == 'finished') {
                    $ordChild->addChild('FINISHED_ON', $this->getCorrectSambaDate($order->finished_on));
                }
                $ordChild->addChild('STATUS', $order->status);
                $ordChild->addChild('EMAIL', $order->email);
                $phone = (int) str_replace(' ', '', $order->phone);
                $ordChild->addChild('PHONE', $phone);
                $ordChild->addChild('ZIP_CODE', $order->zip_code);
                $ordChild->addChild('COUNTRY_CODE', $order->country_code);
                $this->debug('  → order id: ' . $order->id);
                $ordItems = $ordChild->addChild('ITEMS');
                foreach ($order->getPositions() as $product) {
                    $prodItem = $ordItems->addChild('ITEM');
                    $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                    $prodItem->addChild('AMOUNT', $product['amount']);
                    $prodItem->addChild('PRICE', $product['amount'] * $product['price']);
                }
                fwrite($file_handle, $ordChild->asXml());
            }
        }

        fclose($file_handle);

        $page++;
        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            $this->debug('All pages done — finalizing XML');
            return $this->createOrderXml($file, $temp);
        }

        return 1;
    }

    private function createOrderXml(string $file, string $temp)
    {
        file_put_contents($file, '');
        $fileContent = file_get_contents($temp);
        $file_handle = fopen($file, 'a+');
        fwrite($file_handle, '<?xml version="1.0"?> <ORDERS>');
        fwrite($file_handle, $fileContent);
        fwrite($file_handle, '</ORDERS>');
        fclose($file_handle);
        file_put_contents($temp, '');
        return is_file($file) ? 10 : 0;
    }
}
