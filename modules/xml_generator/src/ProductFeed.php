<?php
namespace app\modules\xml_generator\src;

use app\models\IntegrationData;
use app\models\Product;
use app\modules\idosellv3\models\ApiClient;
use app\services\FeedStorageService;
use app\services\SettingsService;

class ProductFeed extends XmlFeed
{
    /**
     * @param null $what
     *
     * @return bool
     *
     */

    const API_RESULT_COUNT      = 40;  // default 100
    const XML_PAGE_SIZE         = 100; // default 100
    private $request_parameters = [];
    private $apiMethod          = '/api/admin/v4/products/products/get';
    private $_client;

    public function generate($what = null): int
    {

        if ($this->_user->config->get('product_feed_disable') == 1) {
            throw new \app\services\FeedDisabledException('Product feed disabled');
        }


        // echo "*** ";
        // var_dump($this->isFinished());
        // echo "*** ";

        if ($what == 'objects') {
            if (! $this->_user->getApiKey()) {
                throw new \Exception('No API key configured');
            }
            $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());
            return $this->createOrAddTempProductXml($this->getFile(true, true));
        }

        if (FeedStorageService::isConfigured()) {
            return $this->generateXmlViaStorage();
        }

        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        if (! $this->isFinished()) {
            $created = $this->prepareProductXml($temp);
        } elseif (!file_exists($temp)) {
            echo "temp file missing - resetting xml phase" . PHP_EOL;
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->_queue->save();
            $created = $this->prepareProductXml($temp);
        } else {
            $created = $this->createProductsXml($file, $temp);
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

    private function getStorageKey(bool $temp = false): string
    {
        $ext = $temp ? '.xml.tmp' : '.xml';
        return 'product/' . $this->_user->uuid . '/product' . $ext;
    }

    private function generateXmlViaStorage(): int
    {
        $storage = FeedStorageService::create();
        $tempKey = $this->getStorageKey(true);
        $fileKey = $this->getStorageKey(false);

        if (!$this->isFinished()) {
            return $this->prepareProductXmlViaStorage($storage, $tempKey, $fileKey);
        } elseif (!$storage->chunksExist($tempKey)) {
            echo "temp chunks missing in storage - resetting xml phase" . PHP_EOL;
            $this->_queue->page     = 0;
            $this->_queue->max_page = 0;
            $this->_queue->save();
            return $this->prepareProductXmlViaStorage($storage, $tempKey, $fileKey);
        } else {
            return $this->createProductsXmlViaStorage($storage, $fileKey, $tempKey, (int) $this->_queue->max_page);
        }
    }

    private function prepareProductXmlViaStorage(FeedStorageService $storage, string $tempKey, string $fileKey): int
    {
        echo "FUNCTION prepareProductXmlViaStorage" . PHP_EOL;

        $aggregate_groups_as_variants = $this->_user->config->get('aggregate_groups_as_variants');
        $integrationDataCurrentPage   = $this->_queue->page;
        $integrationDataMaxPage       = $this->_queue->max_page;
        $page_size                    = self::XML_PAGE_SIZE;

        $query = Product::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;
        if ($integrationDataMaxPage == 0) {
            $customers_all          = $query->count();
            $pages                  = ceil($customers_all / $page_size);
            $this->_queue->max_page = $pages;
            $integrationDataMaxPage = $pages;
            $this->_queue->page     = $page;
            $this->_queue->save();
        }

        echo " PAGE " . $page . " of " . $integrationDataMaxPage . PHP_EOL;

        $res          = $query->limit($page_size)->offset($page * $page_size)->all();
        $products_str = '';
        foreach ($res as $product) {
            if ($product->response == '-') {
                $par['aggregate_groups_as_variants'] = $aggregate_groups_as_variants;
                $products_str .= $product->getXmlEntity($par);
            } else {
                $products_str .= unserialize($product->response);
            }
        }

        if (!empty($products_str)) {
            $storage->putChunk($tempKey, $page, $products_str);
        }

        $page++;
        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            echo "FINISHED" . PHP_EOL;
            return $this->createProductsXmlViaStorage($storage, $fileKey, $tempKey, (int) $integrationDataMaxPage);
        }

        return 1;
    }

    private function createProductsXmlViaStorage(FeedStorageService $storage, string $fileKey, string $tempKey, int $expectedChunks): int
    {
        echo "FINALIZING XML (storage) — expected chunks: {$expectedChunks}" . PHP_EOL;

        $content  = $storage->collectAndDeleteChunks($tempKey, $expectedChunks);
        $finalXml = '<?xml version="1.0" encoding="UTF-8"?><PRODUCTS>' . $content . '</PRODUCTS>';

        $storage->put($fileKey, $finalXml, 'application/xml');

        echo "XML uploaded to storage: " . $fileKey . PHP_EOL;
        return 10;
    }

    private function checkQueueConstraints()
    {
        // incremental
        //if ($this->_user->getIncrementalFeedFlag()) {
        //    $obsoleteDate = date('Y-m-d', strtotime('-7 days'));
	//
        //    Product::deleteAll([
        //        'and',
        //        ['user_id' => $this->_user->id],
        //        ['<', 'created', $obsoleteDate],
        //    ]);
        //}

        // todo

        if ($this->_queue->max_page == $this->_queue->page && $this->_queue->max_page != 0) {
            IntegrationData::setData('last_products_integration_date', date('Y-m-d'), $this->_user->id);
        }

        if ($this->_queue->max_page > 0) {
            return false; // no need every time
        }
        $request                           = $this->request_parameters;
        $request['params']['resultsLimit'] = 1;
        $response                          = $this->_client->post($this->apiMethod, $request);
        // var_dump($request);
        // var_dump($response['resultsNumberAll']);
        // die("!!!");
        if (empty($response) || !is_array($response)) {
            throw new \Exception('Gateway did not respond (checkQueueConstraints)');
        }
        if (! $response['resultsNumberAll']) {
            // echo "Res no: ";
            var_dump($response);
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

    private function createOrAddTempProductXml($temp): int
    {
        echo "FUNC createOrAddTempProductXml" . PHP_EOL;

        $this->request_parameters['params']['returnProducts'] = 'active';
        if ($selectedShopId = $this->_user->config->get('customer_set_shop_id')) {
            $productShops = [];
            // $productShops[]=$selectedShopId;
            $shop         = new \stdClass();
            $shop->shopId = $selectedShopId;

            $productShops[]                                     = $shop;
            $this->request_parameters['params']['productShops'] = $productShops;
            // die ("!!");
        }
        $this->request_parameters['params']['resultsLimit']         = self::API_RESULT_COUNT;
        $this->request_parameters['params']['showPromotionsPrices'] = 'y';

        if (IntegrationData::getData('INITIAL_PRODUCTS_DONE', $this->_user->id)) {
            $this->request_parameters['params']['productDate'] = [
                'productDateMode'  => 'modified',
                'productDateBegin' => IntegrationData::getDataValue('last_products_integration_date', $this->_user->id),
            ];
        }

        $this->checkQueueConstraints();

        // $apiClient = $this->_client;
        echo "start " . PHP_EOL;

        /*
			        if ($this->_queue->id==147923){
			            $request = new SoapRequest();
			            $request->addParam('returnProducts', 'active');
			            $request->addParam('resultsPage', 1671);
			            $request->addParam('resultsLimit', 10);
			            $response = $this->_client->get($request);
			            foreach ($response->results as $p){
			                    echo $p->productId.PHP_EOL;
			                if ($p->productId == 18379){
			                    $idiosellProduct=new \app\models\IdiosellProduct($p, $this->_user);
			                    // var_dump($p->productStocksData->productStocksQuantities);
			                    $idiosellProduct->prepareFromApi();
			                    die ("MAKAO");
			                }
			            }
			            // print_r($response);
			            die ("waitwait");
		*/

        try {
            $settingsService = new SettingsService();
            $settingsService->checkShopUrl($this->_user);

            //building request
            $request                                   = $this->request_parameters;
            $request['params']['returnProducts']       = 'active';
            $request['params']['resultsPage']          = $this->_queue->page;
            $request['params']['resultsLimit']         = self::API_RESULT_COUNT;
            $request['params']['showPromotionsPrices'] = 'y';

            var_dump($request);
            try {
                $response = $this->_client->post($this->apiMethod, $request);

                if (isset($response['errors']) && isset($response['errors']['faultCode']) && $response['errors']['faultCode'] == 2) {
                    $this->_queue->max_page = $this->_queue->page;
                    $this->_queue->save();
                    IntegrationData::setData('INITIAL_PRODUCTS_DONE', 1, $this->_user->id);
                    echo "finished";
                    return 10;
                }

            } catch (\Throwable $e) {
                echo "throwable! ";
                echo $e->getMessage();
                var_dump($request);
                return 1;
            }

            // $this->_queue->setMaxPages($response->resultsNumberPage);
            $products = new \SimpleXMLElement('<PRODUCTS/>');

            echo "[product] " . "page " . $this->_queue->page . " of " . $this->_queue->max_page . PHP_EOL;

            if ($this->_queue->page >= $this->_queue->max_page) {
                // IntegrationData::setData('last_products_integration_date', (date('Y-m-d'), $this->_user->id));
                // IntegrationData::setIsNew('PRODUCTS', 0, $this->_user->id);
                IntegrationData::setData('INITIAL_PRODUCTS_DONE', 1, $this->_user->id);
                echo "finished";
                return 10;
            }
            // print_r($response['resultsPage']);die;
            if (isset($response['errors']) && ! empty($response['errors']['faultString'])) {
                throw new \Exception('API fault: ' . $response['errors']['faultString']);
            }

            $selectedLanguage             = $this->_user->config->get('selected_language');
            $aggregate_groups_as_variants = $this->_user->config->get('aggregate_groups_as_variants');
            if (! $selectedLanguage) {
                $selectedLanguage = 'pol';
            }

            echo "**** LANG " . $selectedLanguage . " ******" . PHP_EOL;

            $productIds=[];

            foreach ($response['results'] as $product) {
                $productIds[]=$product['productId'];
                $product['from_api_page'] = $this->_queue->page;
                $idiosellProduct          = new \app\models\IdiosellProduct($product, $this->_user);
                if (! $idiosellProduct->prepareFromApi()) {
                    $this->_queue->setErrorStatus('Błąd zapisu produktu');
                    return 0;
                }
                
            }
            $this->fillOmnibusPrices($productIds);
            $this->_queue->increasePage();
            return 1;
        } catch (\Exception $e) {
            echo "main error" . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            throw $e;
        }
    }

    private function fillOmnibusPrices($productIds)
    {
        $product_line_omnibus=$this->_user->config->get('product_line_omnibus');
        if (!$product_line_omnibus){
            return;
        }

        $productIdsString= implode(',', $productIds);
        $request=[];
        $request['identType']='id';
        $request['products']=$productIdsString;

        $selectedShopId = $this->_user->config->get('customer_set_shop_id');
        if (!$selectedShopId){
            $selectedShopId=1;
        }

        $result=$this->_client->get('/api/admin/v5/products/omnibusPrices', $request);
        foreach ($result['products'] as $ident => $product)
        {
            $productId = str_replace('id:', '', $ident);
            $omnibusPrice = null;
            foreach ($product['shops'] as $shop)
            {
                if ($shop['shop_id'] == $selectedShopId) {
                    $omnibusPrice = $shop['omnibusPriceRetail'];
                }
            }
            if ($omnibusPrice === null) {
                continue;
            }
            $updated = Product::updateAll(
                ['omnibus_price' => $omnibusPrice],
                ['user_id' => $this->_user->id, 'PRODUCT_ID' => $productId]
            );
            echo "Product with ID " . $productId . ($updated ? " saved : " . $omnibusPrice : " not found.") . PHP_EOL;
        }
    }

    /**
     * @param $file
     * @param $temp
     *
     * @return bool
     */

    private function prepareProductXml($temp)
    {
        echo "FUNCTION prepareProductXml" . PHP_EOL;
        touch($temp);
        $aggregate_groups_as_variants = $this->_user->config->get('aggregate_groups_as_variants');
        $products                     = new \SimpleXMLElement('<PRODUCTS/>');
        $integrationDataCurrentPage   = $this->_queue->page;
        $integrationDataMaxPage       = $this->_queue->max_page;
        $page_size                    = self::XML_PAGE_SIZE;

        $query = Product::find()
            ->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;
        if ($integrationDataMaxPage == 0) {
            $customers_all = $query->count();
            $pages         = ceil($customers_all / $page_size);
            // $pages+=1; // to fit everything else
            $this->_queue->max_page = $pages;
            $integrationDataMaxPage = $pages;
            $this->_queue->page     = $page;
            $this->_queue->save();
        }
        echo " PAGE " . $page . " of " . $integrationDataMaxPage . PHP_EOL;
        $res          = $query->limit($page_size)->offset(($page) * $page_size)->all();
        $products_str = "";
        foreach ($res as $product) {
            if ($product->response == '-') {
                $par['aggregate_groups_as_variants'] = $aggregate_groups_as_variants;
                $products_str .= $product->getXmlEntity($par);
            } else {
                $products_str .= unserialize($product->response);
            }
        }
        $file_handle = fopen($temp, 'a+');
        fwrite($file_handle, $products_str);
        fclose($file_handle);
        $page++;
        $this->_queue->page = $page;
        $this->_queue->save();
        if ($page > (int) $integrationDataMaxPage) {
            // echo $page.PHP_EOL;
            // echo $integrationDataMaxPage.PHP_EOL;
            // die ("JUZ !!!!!");
            echo "FINISHED ";
            return $this->createProductsXml($file, $temp);
        }
        return 1;
    }

    private function createProductsXml($file, $temp): int
    {
        $products = new \SimpleXMLElement('<PRODUCTS/>');
        $products->addChild('PRODUCT');
        $products_str = "";
        file_put_contents($file, str_replace('<PRODUCT/>', file_get_contents($temp), $products->asXML()));
        file_put_contents($temp, '');
        return is_file($file) ? 10 : 0;

    }
}
