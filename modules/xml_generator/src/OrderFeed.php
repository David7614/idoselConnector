<?php
namespace app\modules\xml_generator\src;

use app\models\IntegrationData;
use app\models\Orders;
use app\models\Ordersv2;
use app\models\Queue;
use app\modules\idosellv3\models\ApiClient;
use Cassandra\Date;
use yii;

class OrderFeed extends XmlFeed
{
    /**
     * @param null $what
     * @return bool
     *
     */
    const API_RESULT_COUNT = 100;
    const XML_PAGE_SIZE    = 50000;

    private $request_parameters = [];
    private $apiMethod = '/api/admin/v4/orders/orders/get';
    private $_client;

    public function generate($what = null): int
    {
        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        if ($what == 'objects') {
            echo "creating objects" . PHP_EOL;
            return $this->createOrderObjects();

        }

        if (! $this->isFinished()) {
            $created = $this->createOrAddTempOrderXml($temp);
        } elseif (!file_exists($temp)) {
            echo "temp file missing - resetting xml phase" . PHP_EOL;
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->_queue->save();
            $created = $this->createOrAddTempOrderXml($temp);
        } else {
            $created = $this->createOrderXml($file, $temp);
        }

        return $created;
    }

    /**
     * @param bool $get_file_path
     * @param bool $temp
     *
     * @return string
     */
    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return parent::getFile($get_file_path, $temp);
    }

    public function checkOrdersDateFrom()
    {
        $dateFrom = $this->_user->getConfig()->getOrdersDateFrom();
        if (! $dateFrom) {
            return true;
        }
        $dateFrom = date('Y-m-d', strtotime($dateFrom . ' -10 day'));
        echo $dateFrom . PHP_EOL;
        $check = Orders::find()->where(['user_id' => $this->_user->id])->andWhere(['<', 'created_on', $dateFrom])->limit(1)->one();
        if ($check) {
            var_dump($check);
            Orders::deleteAll(['and',
                ['user_id' => $this->_user->id],
                ['<', 'created_on', $dateFrom],
            ]);
            // Orders::deleteAll(['user_id'=>$this->_user->id]);
            IntegrationData::setData('INITIAL_ORDERS_DONE', 0, $this->_user->id);
            IntegrationData::setData('last_orders_integration_date', $dateFrom, $this->_user->id);
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            echo "DELETED ORDERS, RECOMMENCING" . PHP_EOL;
            throw new \Exception('Orders date range reset — recommencing from ' . $dateFrom);
        }
        $check = Ordersv2::find()->where(['user_id' => $this->_user->id])->andWhere(['<', 'created_on', $dateFrom])->limit(1)->one();
        if ($check) {
            var_dump($check);
            Ordersv2::deleteAll(['and',
                ['user_id' => $this->_user->id],
                ['<', 'created_on', $dateFrom],
            ]);
            IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $this->_user->id);
            IntegrationData::setData('last_orders_integration_date', $dateFrom, $this->_user->id);
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            echo "DELETED ORDERS2, RECOMMENCING" . PHP_EOL;
            throw new \Exception('Orders v2 date range reset — recommencing from ' . $dateFrom);
        }
        // die("!!");
        // getOrdersDateFrom
    }

    private function checkQueueConstraints()
    {

        $this->checkOrdersDateFrom();

        if ($this->_queue->max_page == $this->_queue->page && $this->_queue->max_page != 0) {
            IntegrationData::setData('last_orders_integration_date', date('Y-m-d'), $this->_user->id);
        }

        if ($this->_queue->max_page > 0) {
            return false; // no need every time
        }
        $request                           = $this->request_parameters;
        $request['params']['resultsLimit'] = 1;
        print_r($request);
        $response = $this->_client->post($this->apiMethod, $request);
        var_dump($response);

        if (empty($response) || !is_array($response)) {
            throw new \Exception('Gateway did not respond (checkQueueConstraints)');
        }

        if (isset($response['errors']) && $response['errors']['faultCode'] == 2) {
            echo "api fault code 2" . PHP_EOL;
            return 10;
        }
        // die();
        if (! $response['resultsNumberAll']) {
            echo "no results" . PHP_EOL;
            return false;
        }

        $maxPage = ceil($response['resultsNumberAll'] / self::API_RESULT_COUNT);
        if ($this->_queue->max_page < $maxPage) {
            $this->_queue->max_page = $maxPage;
            $this->_queue->save();
        }

        return true;
    }

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

    private function createOrderObjects()
    {

        if ($this->_user->getIncrementalFeedFlag()) { // incremental
            if ($this->_queue->page == 0) {
                Orders::deleteAll(['user_id' => $this->_user->id]);   // delete all obsolete entries
                Ordersv2::deleteAll(['user_id' => $this->_user->id]); // delete all obsolete entries
            }
            $date2weeksago = date('Y-m-d', strtotime('-2 weeks'));
            // echo $date2weeksago;
            // die();
            IntegrationData::setLastOrdersIntegrationDate($date2weeksago, $this->_user->id);
            // die ("!i");
        }

        echo "creating (createOrderObjects)" . PHP_EOL;
        if (! $this->_user->getApiKey()) {
            throw new \Exception('No API key configured');
        }
        $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());
        // die("!");
        // $this->request_parameters['clientIsActive'] = 'yes';

        if (IntegrationData::getData('INITIAL_ORDERS_DONE', $this->_user->id) && $lastOrderIntegrationDate = IntegrationData::getDataValue('last_orders_integration_date', $this->_user->id)) {
            $begin = date('Y-m-d H:i:s', strtotime($lastOrderIntegrationDate . " - 1 week"));
        }else{
            $dateFrom = $this->_user->getConfig()->getOrdersDateFrom();
            $begin  = date('Y-m-d H:i:s', strtotime($dateFrom));
        }

        echo "BEGIN DATE: " . $begin . PHP_EOL;
            if ($begin) {
                $this->request_parameters['params']['ordersRange'] = [
                    'ordersDateRange' => [
                        'ordersDateType'  => 'modified',
                        'ordersDateBegin' => $begin,
                    ],
                ];
            }

        if ($selectedShopId = $this->_user->config->get('customer_set_shop_id')) {
            $this->request_parameters['params']['orderSource'] ['shopsIds'] = $selectedShopId;

        }

        $this->checkQueueConstraints();

        $this->request_parameters['params']['resultsLimit'] = self::API_RESULT_COUNT;

        echo "request start";

        $request = $this->request_parameters;
        try {

            $request['params']['ordersBy'] = [
                [
                    'elementName'   => 'order_time',
                    'sortDirection' => 'ASC',
                ],
            ];
            // if ($selectedShopId = $this->_user->config->get('customer_set_shop_id')) {
            //     $request['params']['orderSource'] ['shopsIds'] = $selectedShopId;

            // }

            $allItems = [];

            $request['params']['resultsPage'] = $this->_queue->page;
            var_dump($request);
            // die();
            $response = $this->_client->post($this->apiMethod, $request);
            var_dump($response);
            // die();

            try {
                // $this->_queue->setMaxPages($response->resultsNumberPage);
                // print_r($response->Results); die;

                if ($this->_queue->page >= $this->_queue->max_page) {
                    IntegrationData::setIsNew('ORDER', false, $this->_user->id);
                    IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $this->_user->id);
                    // IntegrationData::setData('last_orders_integration_date', date('Y-m-d'), $this->_user->id);
                    return 10;
                }

                if (! isset($response)) {
                    echo "no res";
                    $this->_queue->increasePage();
                    return 1;
                }

            } catch (\yii\base\ErrorException $e) {
                echo "exception";
                throw $e;
            } catch (\Exception $e) {
                echo "exception";
                throw $e;
            }

            if (isset($response['errors']) && ! empty($response['errors']['faultString'])) {
                throw new \Exception('API fault: ' . $response['errors']['faultString']);
            }

            // Mapping order statuses from
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
                echo "check " . $order['orderId'] . PHP_EOL;
                if ($order['orderDetails']['productsResults'] == null) {
                    echo "empty order" . PHP_EOL;
                    continue;
                }
                if ($grabOrdersdateFrom) {
                    if (strtotime($order['orderDetails']['orderAddDate']) < strtotime($grabOrdersdateFrom)) {
                        echo $order['orderDetails']['orderAddDate']." older than date from ".$grabOrdersdateFrom . PHP_EOL;

                        continue;
                    }
                }

                echo "process products " . PHP_EOL;

                $status = isset($order_statuses_map[$order['orderDetails']['orderStatus']]) ? $order_statuses_map[$order['orderDetails']['orderStatus']] : 'created';
                echo $status . PHP_EOL;
                echo $order['orderDetails']['orderDispatchDate'] . PHP_EOL;

                $order_item                      = [];
                $order_item['order_id']          = $order['orderId'];
                $order_item['orderSerialNumber'] = $order['orderSerialNumber'];
                $order_item['customer_id']       = $order['clientResult']['clientAccount']['clientId'];
                $order_item['created_on']        = $order['orderDetails']['orderAddDate'];
                $order_item['finished_on']       = $status == 'finished' ? $order['orderDetails']['orderDispatchDate'] : null;
                if ($status == 'finished' && ! $order_item['finished_on']) {
                    $order_item['finished_on'] = date('Y-m-d');
                }
                $order_item['status']       = $status;
                $order_item['email']        = $order['clientResult']['clientAccount']['clientEmail'];
                $order_item['phone']        = str_replace(' ', '', $order['clientResult']['clientAccount']['clientPhone1']);
                $order_item['zip_code']     = $order['clientResult']['clientBillingAddress']['clientZipCode'];
                $order_item['country_code'] = $order['clientResult']['clientBillingAddress']['clientCountryId'];

                $currency = $this->getCurrency($order);

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
                echo "new id " . $order_object->id . PHP_EOL;
            }
            $this->_queue->increasePage();

            return 1;
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            throw $e;
        }
    }

    private function createOrAddTempOrderXml($temp): int
    {
        echo "creating file";
        touch($temp);
        $orders = new \SimpleXmlElement('<ORDERS/>');

        // $year = (int) date('Y');
        // $since_year = $year - 4;

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

        echo " PAGE " . $page . " of " . $integrationDataMaxPage . PHP_EOL;
        // $customers_db = $customers_query->limit($page_size)->offset(($page - 1) * $page_size)->all();
        echo "offset " . ($page) * $page_size;
        echo PHP_EOL;
        $orders_db = $ordersQuery->limit($page_size)->offset(($page) * $page_size)->all();
        if (count($orders_db) > 0) {
            echo "ORDERS DB ";
            foreach ($orders_db as $order) {
                echo ".";
                // if($order->customer == null){
                //     echo "null customer";
                //     continue;
                // }
                // if($order->customer->email == null){
                //     echo "null customer email";
                //     continue;
                // }
                // echo "one process";

                if (Queue::isDisallowedEmail($order->email)) { // omit allegro etc
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
                echo $order->id . PHP_EOL;
                $ordItems = $ordChild->addChild('ITEMS');
                foreach ($order->getPositions() as $product) {
                    $prodItem = $ordItems->addChild('ITEM');
                    $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                    $prodItem->addChild('AMOUNT', $product['amount']);
                    $prodItem->addChild('PRICE', $product['amount'] * $product['price']);
                }

                $file_handle = fopen($temp, 'a+');
                fwrite($file_handle, $ordChild->asXml());
                fclose($file_handle);
                // echo "one processed".PHP_EOL;
            }
        }
        $orders_dbv2 = $ordersQueryv2->limit($page_size)->offset(($page) * $page_size)->all();
        if (count($orders_dbv2) > 0) {
            echo "ORDERS DB 2 ";
            foreach ($orders_dbv2 as $order) {
                echo ".";
                // if($order->customer == null){
                //     echo "null customer";
                //     continue;
                // }
                // if($order->customer->email == null){
                //     echo "null customer email";
                //     continue;
                // }
                // echo "one process";

                if (Queue::isDisallowedEmail($order->email)) { // omit allegro etc
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
                $phone = str_replace(' ', '', $order->phone);
                $phone = (int) $phone;
                $ordChild->addChild('PHONE', str_replace(' ', '', $phone));
                $ordChild->addChild('ZIP_CODE', $order->zip_code);
                $ordChild->addChild('COUNTRY_CODE', $order->country_code);
                echo $order->id . PHP_EOL;
                $ordItems = $ordChild->addChild('ITEMS');
                foreach ($order->getPositions() as $product) {
                    $prodItem = $ordItems->addChild('ITEM');
                    $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                    $prodItem->addChild('AMOUNT', $product['amount']);
                    $prodItem->addChild('PRICE', $product['amount'] * $product['price']);
                }

                if ('12890215' == $order->id){
                    var_dump($ordChild->asXml());
                    // die();
                }
                $file_handle = fopen($temp, 'a+');
                fwrite($file_handle, $ordChild->asXml());
                fclose($file_handle);
                // echo "one processed".PHP_EOL;
            }
        }
        echo "----";
        $page++;
        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            // echo $page.PHP_EOL;
            // echo $integrationDataMaxPage.PHP_EOL;
            // die ("JUZ !!!!!");
            echo "FINISHED ";
            return $this->createOrderXml($file, $temp);
        }
        return 1;
    }

    private function createOrderXml(string $file, string $temp)
    {
        // $orders = new \SimpleXMLElement('<ORDERS/>');
        // $orders->addChild('ORDER');
        file_put_contents($file, '');
        $fileContent = file_get_contents($temp);
        $file_handle = fopen($file, 'a+');
        fwrite($file_handle, '<?xml version="1.0"?> <ORDERS>');
        fwrite($file_handle, $fileContent);
        fwrite($file_handle, "</ORDERS>");
        fclose($file_handle);
        file_put_contents($temp, '');
        return is_file($file) ? 10 : 0;
    }
}
