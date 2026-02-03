<?php
namespace app\modules\shoper\library;

use DreamCommerce\ShopAppstoreLib\Client;
use DreamCommerce\ShopAppstoreLib\Client\OAuth;
use app\modules\shoper\models\ShoperShops;
use app\modules\shoper\models\ShoperAccessTokens;
/**
 * Class App
 * example for xml importing
 */
class App
{

    /**
     * @var null|DreamCommerce\ShopAppstoreLib\Client
     */
    protected $client = null;
    /**
     * @var string default locale
     */
    protected $locale = 'pl_PL';

    /**
     * @var array current shop metadata
     */
    public $shopData = array();

    /**
     * @var array configuration storage
     */
    public $config = array();


    public $translations;
    public $place;
    public $shop;
    public $timestamp;

    /**
     * instantiate
     * @param array $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * main application bootstrap
     * @throws Exception
     */
    public function bootstrap()
    {

        // check request hash and variables
        $this->validateRequest();

        $this->locale = basename($this->translations);

        // detect if shop is already installed
        $shopData = $this->getShopData($this->shop);
        if (!$shopData) {
            throw new \Exception('An application is not installed in this shop');
        }

        $this->shopData = $shopData;

        // refresh token
        if (strtotime($shopData['expires']) - time() < 86400) {
            $shopData = $this->refreshToken($shopData);
        }

        // instantiate SDK client
        $this->client = $this->instantiateClient($shopData);

        // fire
        // $this->dispatch();
    }

    /**
     * dispatcher
     * @throws Exception
     */
    protected function dispatch()
    {

        // check for parameter existence
        $query = empty($_GET['q']) ? 'index/index' : $_GET['q'];
        if ($query[0]=='/') {
            $query = substr($query, 1);
        }

        $query = str_replace('\\', '', $query);

        $queryData = explode('/', $query);

        $controllerName = ucfirst($queryData[0]);
        $class = '\\Controller\\'.$controllerName;

        if (!class_exists($class)) {
            throw new \Exception('Controller not found');
        }

        $params = $_GET;
        if (!empty($params['id'])) {
            $params['id'] = @json_decode($params['id']);
        }

        $actionName = strtolower($queryData[1]).'Action';
        $controller = new $class($this, $params);
        if (!method_exists($controller, $actionName)) {
            throw new \Exception('Action not found');
        }

        $controller['shopUrl'] = $this->shopData['url'];

        $result = call_user_func_array(array($controller, $actionName), array_slice($queryData, 2));

        if ($result!==false) {
            $viewName = strtolower($queryData[0]) . '/' . strtolower($queryData[1]);
            $controller->render($viewName);
        }
    }

    /**
     * instantiate client resource
     * @param $shopData
     * @return \DreamCommerce\ShopAppstoreLib\Client
     */
    public function instantiateClient($shopData)
    {
        /** @var OAuth $c */
        $c = Client::factory(Client::ADAPTER_OAUTH, array(
                'entrypoint' => $shopData['url'],
                'client_id' => $this->config['appId'],
                'client_secret' => $this->config['appSecret'])
        );
        $c->setAccessToken($shopData['access_token']);
        return $c;
    }

    /**
     * get client resource
     * @throws Exception
     * @return \DreamCommerce\ShopAppstoreLib\Client|null
     */
    public function getClient()
    {
        if ($this->client===null) {
            throw new \Exception('Client is NOT instantiated');
        }

        return $this->client;
    }

    public function setClient($client){
        $this->client=$client;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * refresh OAuth token
     * @param array $shopData
     * @return mixed
     * @throws Exception
     */
    public function refreshToken($shopData)
    {
        /** @var OAuth $c */
        $c = Client::factory(Client::ADAPTER_OAUTH, array(
                'entrypoint' => $shopData['url'],
                'client_id' => $this->config['appId'],
                'client_secret' => $this->config['appSecret'],
                'refresh_token' => $shopData['refresh_token'])
        );
        $tokens = $c->refreshTokens();
        $expirationDate = date('Y-m-d H:i:s', time() + $tokens['expires_in']);

        $accesTokenModel=$shopData->shoperAccessToken;
        $accesTokenModel->refresh_token=$tokens['refresh_token'];
        $accesTokenModel->access_token=$tokens['access_token'];
        $accesTokenModel->expires_at=$expirationDate;
        $accesTokenModel->save();

        $shopData['refresh_token'] = $tokens['refresh_token'];
        $shopData['access_token'] = $tokens['access_token'];

        return $shopData;
    }

    /**
     * checks variables and hash
     * @throws Exception
     */
    public function validateRequest()
    {
        $this->translations=isset($_GET['translations'])?$_GET['translations']:'pl_PL';
        $this->place=isset($_GET['place'])?$_GET['place']:$this->place;
        $this->shop=isset($_GET['shop'])?$_GET['shop']:$this->shop;
        $this->timestamp=isset($_GET['timestamp'])?$_GET['timestamp']:$this->timestamp;




        if (empty($this->translations)) {
            throw new \Exception('Invalid request');
        }

        $params = array(
            'place' => $this->place,
            'shop' => $this->shop,
            'timestamp' => $this->timestamp,
        );

        ksort($params);
        $parameters = array();
        foreach ($params as $k => $v) {
            $parameters[] = $k . "=" . $v;
        }
        $p = join("&", $parameters);


        $hash = hash_hmac('sha512', $p, $this->config['appstoreSecret']);

        if ($hash != $_GET['hash']) {
            throw new \Exception('Invalid request');
        }
    }

    /**
     * get installed shop info
     * @param $shop
     * @return array|bool
     */
    public function getShopData($shop)
    {   
        return ShoperShops::findOne(['shop'=>$shop]);
    }

    

    /**
     * shows more friendly exception message
     * @param Exception $ex
     */
    public function handleException(\Exception $ex)
    {
        $message = $ex->getMessage();
        require __DIR__ . '/../view/exception.php';
    }

    public static function escapeHtml($message)
    {
        return htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
    
    public static function getUrl($url)
    {
        $params = array();
        parse_str($_SERVER['QUERY_STRING'], $params);
        $params['q'] = $url;
        $query = http_build_query($params);
        return $url.'?'.$query;
    }
}
