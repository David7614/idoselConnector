<?php
namespace app\commands;

use app\models\Customers;
use app\models\Queue;
use app\models\IdoselSubscriptions;
use app\models\User;
use app\modules\api\src\Connection;
use app\modules\shoper\models\Integrator;
use app\modules\xml_generator\src\CustomersPartialFeed;
use app\modules\xml_generator\src\IdioselClient;
use app\modules\xml_generator\src\Magazine;
use app\modules\xml_generator\src\OrderFeed;
use app\modules\xml_generator\src\SoapRequest;
use app\modules\xml_generator\src\XmlFeed;
use Exception;
use InvalidArgumentException;
use yii\console\Controller;
use yii\console\ExitCode;

class XmlGeneratorController extends Controller
{
    public $what;

    public function options($actionsID)
    {
        return ['what'];
    }

    public function actionPrepareQueue()
    {
        Queue::prepareQueue(XmlFeed::CUSTOMER);
        Queue::prepareQueue(XmlFeed::PRODUCT);
        Queue::prepareQueue(XmlFeed::CATEGORY);
        Queue::prepareQueue(XmlFeed::ORDER);
        Queue::prepareQueue(XmlFeed::TAGS);
        Queue::prepareQueue('countries');
        Queue::prepareQueue('subscribers');
        Queue::prepareQueue('phonesubscribers');
    }

    private function testWholesale()
    {
        $userId     = 35;
        $user       = User::findOne($userId);
        $connection = new Connection($user);
        $gate       = 'http://' . $user->username . '/api/?gate=clients/get/170/soap/wsdl&lang=pol';
        $client     = new IdioselClient($gate, $connection->getToken()->getToken());
        $request    = new SoapRequest();
        $request->addParam('resultsLimit', 1);
        $request->addParam('clients', ['clientsIds' => [26]]);
        // var_dump($request->getRequest());
        // die();
        $response = $client->get($request->getRequest());
        var_dump($response);
    }

    public function actionSubscriberSync()
    {
        IdoselSubscriptions::sync();
        die ("sync sub");
    }

    public function actionUrlFixer()
    {
        echo "URL FIXER" . PHP_EOL;
        $products = \app\models\Product::find()->where(['user_id' => 86, 'deleted' => 0, 'fixed_url' => 0])->limit(1)->all();
        foreach ($products as $product) {
            echo "****************" . PHP_EOL;
            echo $product->user->shop_type . PHP_EOL;
            echo $product->TITLE . PHP_EOL;
            echo $product->URL . PHP_EOL;
            echo "***" . PHP_EOL;
            if ($product->user->shop_type != 'idiosell') {
                echo "wrong store";
                $product->fixed_url = 10;
                $product->save();
                continue;
            }
            if (! $product->user->active) {
                echo "user non active";
                $product->delete();
                continue;
            }

            // $properUrl = $this->urlRedirectGrab($product->URL);

            $properUrl = $product->user->url . '/product-pol-' . $product->PRODUCT_ID . '-' . $product->getSlug() . '.html';

            echo "PROPER URL:" . PHP_EOL;
            echo $properUrl . PHP_EOL;

            die("SZTOP");

            if (strpos($properUrl, 'cat-pol') !== false) {
                // kategoria, czyli produkt nieaktywny
                echo "NON ACTIVE" . PHP_EOL;
                $product->deleted   = 1;
                $product->fixed_url = 20;
                $product->save();
                continue;
            }
            if (strpos($properUrl, 'product') !== false) {
                // kategoria, czyli produkt nieaktywny
                $product->URL       = $properUrl;
                $product->fixed_url = 1;

                $product->save();
            }

        }
    }

    private function urlRedirectGrab($url)
    {
        echo " -- url check --" . PHP_EOL;
        $oldUrl = $url;
        $ch     = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $html = curl_exec($ch);
        // die ("WAIT");
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $url         = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        echo $status_code . " " . $url . PHP_EOL;
        if ($status_code == 429) {
            die("TO MANY REQUESTS");
        }
        if ($status_code == 301 || $status_code == 301) {
            return $this->urlRedirectGrab($url);
        }
        if ($url == '') {
            return $oldUrl;
            $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            echo $url . PHP_EOL;
            echo $oldUrl . PHP_EOL;
            die("STOP!");
        }
        // die("!!!");
        echo "DONE" . PHP_EOL;
        return $url;
    }

    public function actionTest($method = '', $param1 = '', $param2 = '', $param3 = '')
    {
        Queue::prepareQueue(XmlFeed::SUBSCRIBERS_IMPORT);
        die("!");

        $userId = 125; // 20933
        $user   = User::findOne($userId);

        // $query = \app\models\Product::find()
        //     ->where(['user_id' => $userId, 'PRODUCT_ID'=>['19517', '19518']]);

        // $res = $query->all();
        // $products_str = "";
        // foreach ($res as $product) {
        //     if ($product->response=='-'){
        //         $aggregate_groups_as_variants=$user->config->get('aggregate_groups_as_variants');
        //         $par['aggregate_groups_as_variants']=$aggregate_groups_as_variants;
        //         $products_str .= $product->getXmlEntity($par);
        //     }else{
        //         // $products_str .= unserialize($product->response);
        //     }
        // }
        // echo $products_str;

        // die ("!!!");

        $connection = new Connection($user);
        $gate       = "https://{$user->username}/api/?gate=products/get/182/soap/wsdl&lang=pol";

        //sambaai
        //k0NI@czku?N1eDz13k!

        $context = stream_context_create([
            'ssl' => [
                // set some SSL/TLS specific options
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);
        $authKey = sha1(date('Ymd') . sha1('k0NI@czku?N1eDz13k!'));
        $auth    = [
            'userLogin'       => 'sambaai',
            'authenticateKey' => $authKey,
            // 'stream_context' => $context
        ];
        $request = new \app\modules\xml_generator\src\SoapRequest();
        $request->addParam('resultsPage', 1);
        $request->addParam('resultsLimit', 10);
        $request->setAuth($auth);
        // var_dump($request->getRequest());
        // die();
        $binding['location'] = $gate;
        $binding['trace']    = true;
        $client              = new \SoapClient($gate, $binding);
        $response            = $client->__call('get', $request->getRequest());

        die("SS");

        if (! $Token) {
            die("do chuja nulll Token");
        }
        $token  = $Token->getToken();
        $client = new IdioselClient($gate, $token);

        // $request->addParam('returnElements', '* productIndividualUrlsData');
        $response = $client->get($request->getRequest());

        // $response = $client->getNewsletterEmailShops($request->getRequest());
        foreach ($response->results as $product) {
            var_dump($product->productId) . PHP_EOL;

            echo $product->productId . PHP_EOL;
            // echo $product->productWholesalePrice.PHP_EOL;
            // if ($product->productId==168096 || $product->productId==168095|| $product->productId==168097){
            // var_dump($product).PHP_EOL;
            // var_dump($product->productVersion).PHP_EOL;
            // var_dump($product->productVersion->versionProductsIds).PHP_EOL;
            // var_dump($product->productVersion->versionParentId).PHP_EOL;
            // var_dump($product->productVersion->versionGroupNames).PHP_EOL;
            $idiosellProduct = new \app\models\IdiosellProduct($product, $user);
            $idiosellProduct->prepareFromApi();
            // die("!");
            // if (!Product::insertProduct($prodChild, $userId)){
            //     $queue->setErrorStatus('Błąd zapisu produktu');
            //     return 0;
            // }
            // }

        }
        // var_dump($request->getRequest());
        // print_r($response);
        // print_r($response->results);

        die("TEST");
    }
    public function actionCheckIntegrations()
    {

        $user_list = User::find()->where(['active' => 1])->all();
        foreach ($user_list as $user) {
            echo PHP_EOL . "************************ USER: " . $user->username . PHP_EOL;
            if ($user->shop_type == 'shoper') {
                echo "shoper";
            } else {
                echo "idiosell" . PHP_EOL;
                $xml_generator = new XmlFeed();
                $xml_generator->setType('product');
                $xml_generator->setUser($user);
                $urls             = [];
                $urls['products'] = $xml_generator->getFile(true, false);
                $xml_generator->setType('customer');
                $urls['customer'] = $xml_generator->getFile(true, false);
                $xml_generator->setType('order');
                $urls['order'] = $xml_generator->getFile(true, false);
                $xml_generator->setType('category');
                $urls['category'] = $xml_generator->getFile(true, false);

                foreach ($urls as $type => $fileName) {
                    echo "**** TYP " . $type . PHP_EOL;
                    echo "plik " . $fileName . PHP_EOL;
                    echo "Elementów w bazie: " . $user->countDatabaseElements($type) . PHP_EOL;
                    if (! is_file($fileName)) {
                        echo "BRAK PLIKU " . $fileName . PHP_EOL;
                    } else {
                        $xml     = file_get_contents($fileName);
                        $tagName = strtoupper($type);
                        if ($type == 'products') {
                            $tagName = 'PRODUCT';
                        }
                        if ($type == 'category') {
                            $tagName = 'ITEM';
                        }
                        $tag_count = substr_count($xml, "<" . $tagName . ">");
                        // $elem = new \SimpleXMLElement($xml);
                        echo "W PLIKU " . $type . ": " . $tag_count . PHP_EOL;

                    }
                }

            }

        }

        echo "checking done " . PHP_EOL;

    }

    public function actionGenerateTags($forceId = 0)
    {
        $what = (! isset($this->what) || $this->what == null) ? $this->what : null;
        $this->establishQueue(XmlFeed::TAGS, ['what' => $what, 'forceId' => $forceId]);
    }
    public function actionGenerateCountries($forceId = 0)
    {
        $type  = 'countries';
        $what  = (! isset($this->what) || $this->what == null) ? $this->what : null;
        $queue = Queue::findLastForType($type, ['forceId' => $forceId]);

        if ($queue == null) {
            echo "nothing to do for type " . $type . PHP_EOL;
            return ExitCode::OK;
        }
        $queue->setRunningStatus(); // back to pending
        echo "QUEUE ID " . $queue->id . PHP_EOL;
        $user = $queue->getCurrentUser();
        if ($user->shop_type == 'shoper') {
            $queue->setExecutedStatus();
            $queue->setCountErrors(0);
            return true;
            die("Shoper off");
        }
        echo $user->id;
        $connection = new Connection($user);

        $customersList = Customers::find()->where(['user_id' => $user->id, 'country' => ''])
            ->andWhere(['!=', 'email', ''])
            ->limit(100)->all();

        if (! $customersList) {
            $queue->setExecutedStatus();
            $queue->setCountErrors(0);
            return true;
        }

        $gate   = 'http://' . $user->username . '/api/?gate=clients/getDeliveryAddress/169/soap/wsdl&lang=pol';
        $client = new IdioselClient($gate, $connection->getToken()->getToken());
        foreach ($customersList as $customer) {
            echo "ID " . $customer->id . PHP_EOL;
            // var_dump($customer);
            $queue->page++;
            echo $customer->email . PHP_EOL;
            $request = new SoapRequest();
            $request->addParam('clientLogin', $customer->email);
            $response = $client->getDeliveryAddress($request->getRequest());
            // echo count($response->clientDeliveryAddressesResults);
            $lastIndex = count($response->clientDeliveryAddressesResults) - 1;
            if ($lastIndex < 0) {
                echo "no data " . PHP_EOL;
                $customer->country = 'no data';
                $customer->save();
                var_dump($customer->getErrors());
                continue;
            }
            var_dump($response->clientDeliveryAddressesResults[$lastIndex]->clientDeliveryAddressCountry);
            $customer->country = $response->clientDeliveryAddressesResults[$lastIndex]->clientDeliveryAddressCountry;
            $customer->save();
            var_dump($customer->getErrors());
        }
        $queue->setPendingStatus();
    }

    public function actionTomekOrders()
    {
        $time = microtime(true);

        $queue         = Queue::find()->where(['integration_type' => 'order', 'current_integrate_user' => 19])->one();
        $user          = $queue->getCurrentUser();
        $xml_generator = new XmlFeed();
        $xml_generator->setType('order');
        $xml_generator->setQueue($queue);
        $xml_generator->setUser($user);
        $connection = new Connection($user);

        if ($connection->getToken() == null) {
            echo "token eror " . PHP_EOL;
            // $queue->setErrorStatus();
            return ExitCode::UNSPECIFIED_ERROR;
        }

        try {

        } catch (InvalidArgumentException $e) {
            // $queue->setErrorStatus();
            echo "token eror 2" . PHP_EOL;
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $feed_object = new OrderFeed();
        $feed_object
        // ->setType('order')
        ->setUser($queue->getCurrentUser())
            ->setToken($connection->getToken()->getToken())
        // ->setQueue($this->_queue)
        // ->generate($what);
            ->checkOrders();
        $time_elapsed_secs = microtime(true) - $time;
        die("!! " . $time_elapsed_secs);
        return $this->establishQueue(XmlFeed::ORDER);
    }

    public function actionGenerateProducts($forceId = 0, $forcePage = null)
    {
        return $this->establishQueue(XmlFeed::PRODUCT, ['shop_type' => 'idiosell', 'forceId' => $forceId, 'forcePage' => $forcePage]);
    }
    public function actionGenerateCategories($forceId = 0)
    {
        return $this->establishQueue(XmlFeed::CATEGORY, ['forceId' => $forceId]);
    }

    public function actionGenerateOrders($forceId = 0)
    {
        return $this->establishQueue(XmlFeed::ORDER, ['shop_type' => 'idiosell', 'forceId' => $forceId]);
    }


    public function actionOrdersObjects()
    {
        return $this->establishQueue(XmlFeed::ORDER, ['what' => 'objects']);
    }

    // public function actionCustomersObjects()
    // {
    //     return $this->establishQueue(XmlFeed::CUSTOMER, ['what' => 'objects']);
    // }

    public function actionGenerateOrdersParalel($offset = 2)
    {
        return $this->establishQueue(XmlFeed::ORDER, ['pararel_processing' => 1, 'offset'=>$offset]);
    }
    public function actionGenerateProductsParalel($offset = 2)
    {
        return $this->establishQueue(XmlFeed::PRODUCT, ['pararel_processing' => 1, 'offset'=>$offset]); // added
    }
    public function actionGenerateCustomersParalel($offset = 2)
    {
        return $this->establishQueue(XmlFeed::CUSTOMER, ['pararel_processing' => 1, 'offset'=>$offset]); // added
    }
    public function actionGenerateSubscribersParalel($offset = 2)
    {
        return $this->establishQueue('subscribers', ['pararel_processing' => 1, 'offset'=>$offset]); // added
    }
    public function actionGeneratePhonesubscribersParalel($offset = 2)
    {
        return $this->establishQueue('phonesubscribers', ['pararel_processing' => 1, 'offset'=>$offset]); // added
    }

    public function actionGenerateSubscribers($forceId = 0)
    {
        return $this->establishQueue('subscribers', ['forceId' => $forceId]); // added
    }
    public function actionGeneratePhonesubscribers($forceId = 0)
    {
        return $this->establishQueue('phonesubscribers', ['forceId' => $forceId]); // added
    }
    public function actionGenerateCustomers($forceId = 0)
    {
        return $this->establishQueue(XmlFeed::CUSTOMER, ['forceId' => $forceId]); // added
    }
    public function actionGenerateSubscribersimport($forceId = 0)
    {
        return $this->establishQueue(XmlFeed::SUBSCRIBERS_IMPORT, ['forceId' => $forceId]); // added
    }

    public function actionGenerateCustomersPartial($forceId = 0)
    {
        $userId        = 29;
        $user          = User::findOne($userId);
        $connection    = new Connection($user);
        $xml_generator = new CustomersPartialFeed();
        if ($connection->getToken() == null) {
            echo "ERR1 - no token";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $xml_generator->setToken($connection->getToken()->getToken());

        $xml_generator->setUser($user);
        $what = isset($parameters['objects_done']) ? null : 'objects';
        $xml_generator->generate($what);
        die("!");
    }

    public function actionGenerateMagazines()
    {
        $magazine = new Magazine();
        $magazine->getMagazines();
    }

    public function actionResetIntegration()
    {
        Queue::resetLongRunning();
        Queue::resetAllDone();
        // Queue::resetAllException();
    }

    public function actionResetExceptions()
    {
        die("DISABLED");
        // Queue::resetAllException();
    }

    private function determineQueue(string $type, array $config = [])
    {
        if ($config['forceId'] != 0) {
            $queue = Queue::findOne($config['forceId']);
            return $queue;
        }
        if (isset($config['pararel_processing']) && $config['pararel_processing']) {

            $queue = Queue::findPararelForType($type,$config['offset']); // 2 - offset
            return $queue;
        }


        if (isset($config['shop_type'])) {
            $queue = Queue::findLastForTypeAndShop($type, $config['shop_type']);
        } else {
            $queue = Queue::findLastForType($type);
        }

        return $queue;
    }
    private function establishQueue(string $type, array $config = [])
    {

        if (! isset($config['forceId'])) {
            $config['forceId'] = 0;
        }


        $queue = $this->determineQueue($type, $config);


        if ($queue == null) {
            echo "nothing to do for type " . $type . PHP_EOL;
            return ExitCode::OK;
        }

        Integrator::shoperLog('', $queue->id);
        Integrator::shoperLog('1.1 Establish Queue - ID: ' . $queue->id, $queue->id);

        echo '- - - Establish Queue - ID: ' . $queue->id . PHP_EOL;

        $user        = $queue->getCurrentUser();
        $parameters  = $queue->additionalParameters;
        $filePrepare = isset($parameters['objects_done']) ? true : false;

        // if ($queue->integrated == Queue::RUNNING && ($user->shop_type != 'shoper' || $filePrepare)) { // prevent double run
        if ($queue->integrated == Queue::RUNNING && $config['forceId'] == 0) {
            // prevent double run
            echo " job still in progress " . PHP_EOL;
            echo "from " . $queue->executed_at . PHP_EOL;
            $date          = new \DateTime($queue->executed_at);
            $date2         = new \DateTime(date('Y-m-d H:i:s'));
            $diffInSeconds = $date2->getTimestamp() - $date->getTimestamp();
            echo "in seconds: " . $diffInSeconds . PHP_EOL;
            if ($diffInSeconds > 3600) {
                echo 'over hour';
                $queue->setPendingStatus();
                return ExitCode::OK;
            }
            return ExitCode::OK;
        }

        if (! $queue->checkQueueConstraints()) {
            echo " job should not run " . PHP_EOL;
            $queue->setErrorStatus('job disabled');
            return ExitCode::OK;
        }

        // die ("STOP FOR NOW");
        $queue->setRunningStatus();

        if (! $user) {
            $queue->delete();
            return ExitCode::ERR;
        }

        $xml_generator = new XmlFeed();

        $xml_generator->setType($type);
        if (isset($config['forcePage'])) {
            $queue->page = $config['forcePage'];
        }
        $xml_generator->setQueue($queue);
        $xml_generator->setUser($user);

        try {

            $parameters = $queue->additionalParameters;

            $what = isset($parameters['objects_done']) ? null : 'objects';
            echo "RUN with what " . $what . PHP_EOL;
            $generated = $xml_generator->generate($what);

            if (! $generated) {
                var_dump($generated);
                $queue->setErrorStatus();
                throw new Exception('Cannot generate ' . $type . ' feed. Cannot save file');
            }
            if (isset($config['forcePage'])) {
                $queue->setErrorStatus();
                echo "page forced ";
                die();
            }
            // if($xml_generator->isFinished()) {
            if ($generated === 10) {
                // czyli skończone
                // die ("SET EXECUTED");
                $queue->setExecutedStatus();
                $queue->setCountErrors(0);
                return true;
                /*

					                $xml_generator->generate();
					                // if($type == XmlFeed::PRODUCT || $type == XmlFeed::CATEGORY) {
					                //     $queue->setExecutedStatus();
					                // }
					                file_put_contents($xml_generator->getFile(true, true), '');
					                echo "finished ".$xml_generator->getFile(true, true);
					                $queue->setExecutedStatus();
				*/
            }

            $queue->setPendingStatus(); // back to pending
            $queue->setCountErrors(0);

            return ExitCode::OK;
        } catch (Exception $e) {
            echo "ERROR::" . PHP_EOL;
            echo $e->getMessage();
            // $queue->setErrorStatus($e->getMessage());

            $queue->raiseCountErrors();
            if ($queue->getCountErrors() < 30) {
                $queue->setPendingStatus();
            } else {
                $queue->setErrorStatus($e->getMessage());
            }

            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
