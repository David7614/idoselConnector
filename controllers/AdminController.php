<?php
namespace app\controllers;

use app\services\SettingsService;
use app\models\IntegrationData;
use app\models\User;
use app\modules\IAI\Application\Config;
use app\modules\idosellv3\models\ApiClient;
use app\modules\shoper\library\App;
use app\modules\xml_generator\src\XmlFeed;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Controller;

class AdminController extends Controller
{
    public $layout = 'admin';

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only'  => ['index'],
                'rules' => [
                    [
                        'actions' => ['index'],
                        'allow'   => true,
                        'roles'   => ['admin'],
                    ],
                ],
            ],
            'verbs'  => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'index' => ['get'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error'   => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class'           => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
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

    private function getShopMarketingUrl($shopsData, $user)
    {
        $marketingShopId = $user->config->get('customer_default_approvals_shop_id');

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

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionDashboard($id)
    {
        $user = User::findOne($id);
        if (! $user->apiEnabled()) {
            return $this->redirect(Url::toRoute(['/admin/set-api-key'] + Yii::$app->request->get()));
        }
        $client = new ApiClient($user->username, $user->getApiKey());
        $res    = $client->sendRequest('/api/admin/v3/system/config');

        if (! $res) {
            return $this->redirect(Url::toRoute(['/admin/set-api-key'] + Yii::$app->request->get()));
        }

        if (Yii::$app->request->isPost) {
            $oldTrackpoint=$user->getConfig()->get('trackpoint');
            $trackpoint = Yii::$app->request->post('trackpoint');
            if ($oldTrackpoint != $trackpoint)
            {
                $user->saveTrackpoint($trackpoint);
                // die ("track changed");
            }

            $smartpoint = Yii::$app->request->post('smartpoint');
            var_dump($user->getConfig()->set('smartpoint', $smartpoint));

            $selected_language = Yii::$app->request->post('selected_language');
            var_dump($user->getConfig()->set('selected_language', $selected_language));

            $aggregate_groups_as_variants = Yii::$app->request->post('aggregate_groups_as_variants');
            var_dump($user->getConfig()->set('aggregate_groups_as_variants', $aggregate_groups_as_variants));

            $orders_date_from = Yii::$app->request->post('orders_date_from');
            $user->getConfig()->setOrdersDateFrom($orders_date_from);

            $get_quantity_from = Yii::$app->request->post('get_quantity_from');
            var_dump($user->getConfig()->set('get_quantity_from', $get_quantity_from));

            $get_menu_from = Yii::$app->request->post('get_menu_from');
            var_dump($user->getConfig()->set('get_menu_from', $get_menu_from));

            $product_feed_disable = Yii::$app->request->post('product_feed_disable');
            var_dump($user->getConfig()->set('product_feed_disable', $product_feed_disable));

            $export_type = Yii::$app->request->post('export_type');
            if ((int)$user->getConfig()->get('export_type') != $export_type) {
                if ($export_type == 0) {
                    $lastDate = date('Y-m-d', strtotime('-5 years'));

                    $dateFrom = $user->getConfig()->getOrdersDateFrom();

                    if ($dateFrom) {
                        $lastDate = date('Y-m-d', strtotime($dateFrom));
                    }

                    IntegrationData::setLastOrdersIntegrationDate($lastDate, $user->id);

                    $lastDate = date('Y-m-d', strtotime('-20 years'));

                    IntegrationData::setLastCustomerIntegrationDate($lastDate, $user->id);
                    IntegrationData::setData('LAST_SUBSCRIBER_INTEGRATION_DATE', $lastDate, $user->id);
                    IntegrationData::setData('LAST_PHONESUBSCRIBER_INTEGRATION_DATE', $lastDate, $user->id);
                    //IntegrationData::setData('last_products_integration_date', $lastDate, $user->id);
                }
                $user->getConfig()->set('export_type', $export_type);
            }

            Yii::$app->session->addFlash('success', 'Ustawienia g��owne zapisane');

            $customerShopId = Yii::$app->request->post('customer_set_shop_id');

            $user->setCustomerShoipId($customerShopId);

            $settingsService = new SettingsService();
            $settingsService->saveShopUrl($customerShopId, $user, $res['shops']);

            return $this->redirect(Url::toRoute(['admin/dashboard', 'id' => $user->id]));
        }

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
            // echo "**** TYP ".$type.PHP_EOL;
            // echo "plik ".$fileName.PHP_EOL;
            // echo "Element��w w bazie: ".$user->countDatabaseElements($type).PHP_EOL;
            $filesInfo[$type]           = [];
            $filesInfo[$type]['status'] = 'gotowy';
            if (! is_file($fileName)) {
                $filesInfo[$type]['status']   = 'Nie gotowy';
                $filesInfo[$type]['elements'] = 0;
                // echo "BRAK PLIKU ".$fileName.PHP_EOL;
            }  else {
                $xml     = file_get_contents($fileName);
                $tagName = strtoupper($type);
                if ($type == 'products') {
                    $tagName = 'PRODUCT';
                }
                if ($type == 'category') {
                    $tagName = 'ITEM';
                }
                $tag_count                    = substr_count($xml, "<" . $tagName . ">");
                $filesInfo[$type]['elements'] = $tag_count;

            }
        }

        $urls               = [];
        $urls['products']   = Url::home(true) . 'xml/' . $user->uuid . '/products.xml';
        $urls['customers']  = Url::home(true) . 'xml/' . $user->uuid . '/customers.xml';
        $urls['orders']     = Url::home(true) . 'xml/' . $user->uuid . '/orders.xml';
        $urls['categories'] = Url::home(true) . 'xml/' . $user->uuid . '/categories.xml';

        return $this->render('update', [
            'user'      => $user,
            'languages' => $res['languages'],
            'shops'     => $res['shops'],
            'stocks'    => $res['stocks'],
            'urls'      => $urls,
            'filesInfo' => $filesInfo,
        ]);
    }
    public function actionIndex()
    {
        $user = User::findIdentity(Yii::$app->user->id);

        // $connection = new Connection($user);
        // $gate='http://'.$user->username.'/api/?gate=systemconfig/get/162/soap/wsdl&lang=eng';
        // $client=new \app\modules\xml_generator\src\IdioselClient($gate, $connection->getToken()->getToken());
        // $request=new \app\modules\xml_generator\src\SoapRequest();
        // $response = $client->get($request->getRequest());

        return $this->render('index', [
            'user' => $user,
            // 'shops' => $response->shops,
            // 'languages' => $response->languages,
            // 'stocks' => isset($response->stocks)?$response->stocks:null
        ]);
    }

    public function actionSaveProductFeed($id)
    {
        $user = User::findOne($id);

        if (!$user->apiEnabled()) {
            return $this->redirect(Url::toRoute(['/admin/set-api-key'] + Yii::$app->request->get()));
        }

        if (Yii::$app->request->isPost) {
            $settingsService = new SettingsService();
            $settingsService->saveProductFeed($user);
        }

        return $this->redirect(Url::toRoute(['admin/dashboard', 'id' => $user->id]));
    }

    public function actionSaveCustomerFeed($id)
    {
        $user = User::findOne($id);

        if (!$user->apiEnabled()) {
            return $this->redirect(Url::toRoute(['/admin/set-api-key'] + Yii::$app->request->get()));
        }

        if (Yii::$app->request->isPost) {
            $settingsService = new SettingsService();
            $settingsService->saveCustomerFeed($user);
        }

        return $this->redirect(Url::toRoute(['admin/dashboard', 'id' => $user->id]));
    }

    public function actionSaveOrderFeed($id)
    {
        $user = User::findOne($id);

        if (!$user->apiEnabled()) {
            return $this->redirect(Url::toRoute(['/admin/set-api-key'] + Yii::$app->request->get()));
        }

        if (Yii::$app->request->isPost) {
            $settingsService = new SettingsService();
            $settingsService->saveOrderFeed($user);
        }

        return $this->redirect(Url::toRoute(['admin/dashboard', 'id' => $user->id]));
    }

    public function actionSetApiKey($id)
    {
        $user = User::findOne($id);
        if (Yii::$app->request->isPost) {
            if ($apiKey = Yii::$app->request->post('api3_key')) {
                // test połączenia
                $client = new ApiClient($user->username, $apiKey);
                $res=$client->testApiCredentials();

                if ($res) {
                    $user->setUserDataValue('api3_key', $apiKey);
                    return $this->redirect(Url::toRoute(['/admin/dashboard'] + Yii::$app->request->get()));
                }
            }
            // die();
        }

        return $this->render('setapikey', [
            'user' => $user,
        ]);
    }
}

/*
$auth = Yii::$app->authManager;

$admin = $auth->createRole('admin');
// $auth->save();

$auth->assign($admin, $user->id);
 */
