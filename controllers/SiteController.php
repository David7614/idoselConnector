<?php
namespace app\controllers;

use app\services\SettingsService;
use app\models\IntegrationData;
use app\models\User;
use app\modules\api\src\Connection;
use app\modules\IAI\Application\Config;
use app\modules\idosellv3\models\ApiClient;
use app\modules\xml_generator\src\XmlFeed;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;

class SiteController extends Controller
{
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
                        'roles'   => ['@'],
                    ],
                ],
            ],
            'verbs'  => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'index' => ['get', 'post'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        if ($action->id == 'panel') {
            $this->enableCsrfValidation = false;
        }
        if ($action->id == 'save-customer-feed') {
            $this->enableCsrfValidation = false;
        }
        if ($action->id == 'save-product-feed') {
            $this->enableCsrfValidation = false;
        }
        // $this->enableCsrfValidation = false;
        return parent::beforeAction($action);
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

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $user = User::findIdentity(Yii::$app->user->id);

        if ($user->user_type=='admin'){
            return $this->redirect(Url::toRoute(['/admin'] ));
        }

        if (Yii::$app->request->isPost) {
            if ($postData = Yii::$app->request->post()) {
                $apiKey   = $postData['api3_key'];
                $username = $postData['username'];
                // test połączenia
                $client = new ApiClient($username, $apiKey);
                // $res    = $client->sendRequest('/api/admin/v3/system/config');
                $res=$client->testApiCredentials();
                if ($res) {
                    $user->setUserDataValue('api3_key', $apiKey);
                    $user->username = $username;
                    $user->save();
                    $user = User::findIdentity(Yii::$app->user->id);
                    // return $this->redirect(Url::toRoute(['/admin/dashboard'] + Yii::$app->request->get()));
                }
            }
            // die();
        }

        $res = $user->checkApiReady();
        // var_dump($res);
        // die("!!!");
        if ($res) {
            return $this->redirect(Url::toRoute(['/site/panel'] + Yii::$app->request->get()));
        }

        return $this->render('index', [
            'user' => $user,
        ]);
    }

    public function actionPanel()
    {
        $get=Yii::$app->request->get();
        if (Yii::$app->user->getIdentity() === null && isset($get['token']) && isset($get['userid'])) {
            $token = $get['token'];
            $userid=$get['userid'];
            // echo $token;
            // var_dump($userid);
            // die (":TEST");
            $user = User::findIdentityByAccessToken($token);
            if ($user->id == $userid){
                Yii::$app->user->login($user, 0);
            }
        }
        if (Yii::$app->user->getIdentity() === null) {
            return $this->redirect(Url::toRoute(['authorization/login']));
        }


        $user = User::findIdentity(Yii::$app->user->id);

        $client = new ApiClient($user->username, $user->getApiKey());
        $res = $client->sendRequest('/api/admin/v3/system/config');

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
            $customer_default_approvals_shop_id=$smartpoint = Yii::$app->request->post('Settings')['customer_default_approvals_shop_id'];
            // var_dump($customer_default_approvals_shop_id);
            // die();
            $user->config->set('customer_default_approvals_shop_id', $customer_default_approvals_shop_id);

            Yii::$app->session->addFlash('success', 'Ustawienia głowne zapisane');

            $customer_set_shop_id = Yii::$app->request->post('customer_set_shop_id');

            if ($customer_set_shop_id) {
                $user->config->set('customer_set_shop_id', $customer_set_shop_id);
            } else {
                $user->config->set('customer_set_shop_id', 0);
            }

            $settingsService = new SettingsService();
            $settingsService->saveShopUrl($customer_set_shop_id, $user, $res['shops']);

            return $this->redirect(Url::toRoute(['site/panel']));
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
            // echo "Elementów w bazie: ".$user->countDatabaseElements($type).PHP_EOL;
            $filesInfo[$type]           = [];
            $filesInfo[$type]['status'] = 'gotowy';
            if (! is_file($fileName)) {
                $filesInfo[$type]['status']   = 'Nie gotowy';
                $filesInfo[$type]['elements'] = 0;
                // echo "BRAK PLIKU ".$fileName.PHP_EOL;
            } else {
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


        return $this->render('panel', [
            'urls'      => $urls,
            'user'      => $user,
            'languages' => $res['languages'],
            'shops'     => $res['shops'],
            'stocks'    => $res['stocks'],
            'filesInfo' => $filesInfo,
        ]);
    }

    public function actionSaveProductFeed()
    {
        if (Yii::$app->request->isPost) {
            $settingsService = new SettingsService();
            $settingsService->saveProductFeed();
        }

        return $this->redirect(Url::toRoute(['site/panel']));
    }

    public function actionSaveCustomerFeed()
    {
        if (Yii::$app->request->isPost) {
            $settingsService = new SettingsService();
            $settingsService->saveCustomerFeed();
        }

        return $this->redirect(Url::toRoute(['site/panel']));
    }

    public function actionSaveOrderFeed()
    {
        if (Yii::$app->request->isPost) {
            $settingsService = new SettingsService();
            $settingsService->saveOrderFeed();
        }

        return $this->redirect(Url::toRoute(['site/panel']));
    }

    protected function saveSettings($settings_array, $settings_post)
    {
        $user = User::findIdentity(Yii::$app->user->id);
        $ok   = true;

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
            if (! $user->config->set($value, '')) {
                $ok       = false;
                $errors[] = "Dodawanie konfiguracji '{$value}' nie powiodło się";
                continue;
            }
        }

        return [$ok, $errors];
    }
}
