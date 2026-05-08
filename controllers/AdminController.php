<?php
namespace app\controllers;

use app\services\QueueRunnerService;
use app\services\SettingsService;
use app\models\IntegrationData;
use app\models\User;
use app\models\AppConfig;
use app\models\Queue;
use app\models\QueueExecutionLog;
use app\modules\IAI\Application\Config;
use app\modules\idosellv3\models\ApiClient;
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
                'only'  => ['index', 'view', 'run-queue-output', 'restart-queue-output', 'update', 'queues', 'queues-sections', 'save-queues-autorefresh', 'save-queues-collapsed', 'refresh-feed-counts', 'prepare-queue-output', 'app-settings', 'admins', 'hard-reset-type', 'view-recent-started'],
                'rules' => [
                    [
                        'actions' => ['index', 'view', 'run-queue-output', 'restart-queue-output', 'update', 'queues', 'queues-sections', 'save-queues-autorefresh', 'save-queues-collapsed', 'refresh-feed-counts', 'prepare-queue-output', 'app-settings', 'admins', 'hard-reset-type', 'view-recent-started'],
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
        $configFromApi=$user->getConfigurationVariables();

        if (! $configFromApi) {
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
            $settingsService->saveShopUrl($customerShopId, $user, $configFromApi['shops']);

            return $this->redirect(Url::toRoute(['admin/dashboard', 'id' => $user->id]));
        }

        // key => [storageType, xml tag]
        $feedMeta = [
            'products' => ['product',  'PRODUCT'],
            'customer' => ['customer', 'CUSTOMER'],
            'order'    => ['order',    'ORDER'],
            'category' => ['category', 'ITEM'],
        ];

        $xml_generator = new XmlFeed();
        $xml_generator->setType('product');
        $xml_generator->setUser($user);
        $localPaths             = [];
        $localPaths['products'] = $xml_generator->getFile(true, false);
        $xml_generator->setType('customer');
        $localPaths['customer'] = $xml_generator->getFile(true, false);
        $xml_generator->setType('order');
        $localPaths['order']    = $xml_generator->getFile(true, false);
        $xml_generator->setType('category');
        $localPaths['category'] = $xml_generator->getFile(true, false);

        $filesInfo  = [];
        $useStorage = \app\services\FeedStorageService::isConfigured();
        $storage    = $useStorage ? \app\services\FeedStorageService::create() : null;

        foreach ($feedMeta as $key => [$storageType, $tag]) {
            $filesInfo[$key] = ['status' => 'gotowy', 'elements' => 0];

            if ($useStorage) {
                $storageKey = $storageType . '/' . $user->uuid . '/' . $storageType . '.xml';
                if (!$storage->exists($storageKey)) {
                    $filesInfo[$key]['status'] = 'Nie gotowy';
                } else {
                    $filesInfo[$key]['elements'] = $storage->countOccurrences($storageKey, '<' . $tag . '>');
                }
            } else {
                $fileName = $localPaths[$key];
                if (!is_file($fileName)) {
                    $filesInfo[$key]['status'] = 'Nie gotowy';
                } else {
                    $needle  = '<' . $tag . '>';
                    $overlap = strlen($needle) - 1;
                    $count   = 0;
                    $tail    = '';
                    $fh      = fopen($fileName, 'rb');
                    while (!feof($fh)) {
                        $chunk  = $tail . fread($fh, 524288);
                        $count += substr_count($chunk, $needle);
                        $tail   = $overlap > 0 ? substr($chunk, -$overlap) : '';
                    }
                    fclose($fh);
                    $filesInfo[$key]['elements'] = $count;
                }
            }
        }

        $urls               = [];
        $urls['products']   = Url::home(true) . 'xml/' . $user->uuid . '/products.xml';
        $urls['customers']  = Url::home(true) . 'xml/' . $user->uuid . '/customers.xml';
        $urls['orders']     = Url::home(true) . 'xml/' . $user->uuid . '/orders.xml';
        $urls['categories'] = Url::home(true) . 'xml/' . $user->uuid . '/categories.xml';

        return $this->render('update', [
            'user'      => $user,
            'languages' => $configFromApi['languages'],
            'shops'     => $configFromApi['shops'],
            'stocks'    => $configFromApi['stocks'],
            'urls'      => $urls,
            'filesInfo' => $filesInfo,
        ]);
    }
    public function actionPrepareQueueOutput()
    {
        $this->layout = false;

        set_time_limit(0);
        ignore_user_abort(true);

        while (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/html; charset=utf-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-cache');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo '<title>Przygotowanie kolejek</title>';
        echo '<style>
            body { background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:13px; padding:20px; margin:0; }
            h2   { color:#9cdcfe; margin-bottom:4px; }
            p    { color:#888; margin:0 0 16px; }
            pre  { margin:0; white-space:pre-wrap; word-break:break-all; }
            .ok  { color:#4ec9b0; }
            #status { position:fixed; bottom:0; left:0; right:0; background:#252526;
                      border-top:1px solid #333; padding:8px 20px; font-size:12px; color:#888; }
        </style></head><body>';
        echo '<h2>Przygotowanie kolejek</h2>';
        echo '<p>Planowanie zadań dla wszystkich aktywnych użytkowników&hellip;</p>';
        echo '<pre>';
        flush();

        ob_start(function (string $chunk): string {
            return '<span>' . htmlspecialchars($chunk, ENT_QUOTES) . '</span>';
        }, 1);

        $types = [
            \app\modules\xml_generator\src\XmlFeed::CUSTOMER,
            \app\modules\xml_generator\src\XmlFeed::PRODUCT,
            \app\modules\xml_generator\src\XmlFeed::CATEGORY,
            \app\modules\xml_generator\src\XmlFeed::ORDER,
            \app\modules\xml_generator\src\XmlFeed::TAGS,
            'countries',
            'subscribers',
            'phonesubscribers',
        ];

        foreach ($types as $type) {
            echo "=== " . strtoupper($type) . " ===" . PHP_EOL;
            Queue::prepareQueue($type);
            echo PHP_EOL;
        }

        Queue::cleanupOldQueues();

        ob_end_flush();
        flush();

        echo '</pre>';
        echo '<div id="status"><span class="ok">✔ Zakończono</span> &nbsp;|&nbsp; '
           . '<a href="javascript:window.close()" style="color:#569cd6;">Zamknij okno</a>'
           . ' &nbsp;|&nbsp; <a href="' . Url::to(['admin/queues']) . '" style="color:#569cd6;" target="_parent">Wróć do monitora</a>'
           . '</div>';
        echo '</body></html>';
        Yii::$app->end();
    }

    public function actionAdmins()
    {
        $error   = null;
        $success = null;

        $auth      = Yii::$app->authManager;
        $adminRole = $auth->getRole('admin');

        if (Yii::$app->request->isPost) {
            $action = Yii::$app->request->post('action');
            $userId = (int) Yii::$app->request->post('user_id');

            if ($action === 'add') {
                $username = trim(Yii::$app->request->post('username', ''));
                $email    = trim(Yii::$app->request->post('email', ''));
                $password = Yii::$app->request->post('password', '');

                if (!$username || !$email || !$password) {
                    $error = 'Wypełnij wszystkie pola.';
                } elseif (User::findOne(['username' => $username])) {
                    $error = 'Użytkownik o tej nazwie już istnieje.';
                } else {
                    $admin                = new User();
                    $admin->id            = (int)(User::find()->max('id')) + 1;
                    $admin->username      = $username;
                    $admin->email         = $email;
                    $admin->password      = password_hash($password, PASSWORD_BCRYPT);
                    $admin->user_type     = 'admin';
                    $admin->client_id     = sha1($username . $email);
                    $admin->client_secret = md5(hash('sha256', $admin->client_id . $username));
                    $admin->active        = 1;
                    if ($admin->save(false)) {
                        if (!$auth->getAssignment('admin', $admin->id)) {
                            $auth->assign($adminRole, $admin->id);
                        }
                        $success = 'Administrator ' . $username . ' został dodany.';
                    } else {
                        $error = 'Błąd zapisu: ' . implode(', ', $admin->getFirstErrors());
                    }
                }

            } elseif ($action === 'password') {
                $password = Yii::$app->request->post('password', '');
                $admin    = User::findOne(['id' => $userId, 'user_type' => 'admin']);
                if (!$admin) {
                    $error = 'Nie znaleziono administratora.';
                } elseif (strlen($password) < 6) {
                    $error = 'Hasło musi mieć co najmniej 6 znaków.';
                } else {
                    $admin->password = password_hash($password, PASSWORD_BCRYPT);
                    $admin->save(false);
                    $success = 'Hasło dla ' . $admin->username . ' zostało zmienione.';
                }

            } elseif ($action === 'remove') {
                $admin = User::findOne(['id' => $userId, 'user_type' => 'admin']);
                if (!$admin) {
                    $error = 'Nie znaleziono administratora.';
                } elseif ($admin->id === Yii::$app->user->id) {
                    $error = 'Nie możesz usunąć własnego konta.';
                } else {
                    $name = $admin->username;
                    $auth->revoke($adminRole, $admin->id);
                    $admin->delete();
                    $success = 'Administrator ' . $name . ' został usunięty.';
                }
            }
        }

        $admins = User::find()->where(['user_type' => 'admin'])->orderBy('username')->all();

        return $this->render('admins', [
            'admins'  => $admins,
            'error'   => $error,
            'success' => $success,
        ]);
    }

    public function actionAppSettings()
    {
        $saved = false;

        $feedConstMap = [
            'order'             => AppConfig::STOP_FEED_ORDER,
            'product'           => AppConfig::STOP_FEED_PRODUCT,
            'customer'          => AppConfig::STOP_FEED_CUSTOMER,
            'category'          => AppConfig::STOP_FEED_CATEGORY,
            'subscribers'       => AppConfig::STOP_FEED_SUBSCRIBERS,
            'phonesubscribers'  => AppConfig::STOP_FEED_PHONESUBSCRIBERS,
            'subscribersimport' => AppConfig::STOP_FEED_SUBSCRIBERSIMPORT,
            'customerspartial'  => AppConfig::STOP_FEED_CUSTOMERSPARTIAL,
        ];

        if (Yii::$app->request->isPost) {
            $forceIncremental = (int) Yii::$app->request->post('force_all_incremental', 0);
            AppConfig::setValue(AppConfig::FORCE_ALL_INCREMENTAL, $forceIncremental);
            $displayDebug = (int) Yii::$app->request->post('display_debug', 0);
            AppConfig::setValue(AppConfig::DISPLAY_DEBUG, $displayDebug);
            $yearsBack = max(1, (int) Yii::$app->request->post('default_orders_years_back', 10));
            AppConfig::setValue(AppConfig::DEFAULT_ORDERS_YEARS_BACK, $yearsBack);

            $stopFeed = Yii::$app->request->post('stop_feed', []);
            foreach ($feedConstMap as $type => $const) {
                AppConfig::setValue($const, isset($stopFeed[$type]) && $stopFeed[$type] == 1 ? 1 : 0);
            }

            $saved = true;
        }

        $stoppedFeeds = [];
        foreach ($feedConstMap as $type => $const) {
            $stoppedFeeds[$type] = (int) AppConfig::getValue($const);
        }

        return $this->render('app-settings', [
            'forceAllIncremental'    => (int) AppConfig::getValue(AppConfig::FORCE_ALL_INCREMENTAL),
            'displayDebug'           => (int) AppConfig::getValue(AppConfig::DISPLAY_DEBUG),
            'defaultOrdersYearsBack' => (int) (AppConfig::getValue(AppConfig::DEFAULT_ORDERS_YEARS_BACK) ?? 10),
            'stoppedFeeds'           => $stoppedFeeds,
            'saved'                  => $saved,
        ]);
    }

    public function actionQueues()
    {
        $admin             = User::findIdentity(Yii::$app->user->id);
        $saved             = $admin ? $admin->getUserDataValue('admin_queues_autorefresh') : null;
        $initialStates     = $saved ? json_decode($saved, true) : null;
        $savedCollapsed    = $admin ? $admin->getUserDataValue('admin_queues_collapsed') : null;
        $collapsedSections = $savedCollapsed ? json_decode($savedCollapsed, true) : null;

        return $this->render('queues', [
            'initialStates'     => $initialStates,
            'collapsedSections' => $collapsedSections,
        ]);
    }

    public function actionSaveQueuesCollapsed()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $raw     = Yii::$app->request->post('collapsed');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false];
        }

        $valid  = ['health', 'running', 'recent_hour', 'recent_started', 'errors', 'disabled', 'overdue', 'users'];
        $toSave = [];
        foreach ($valid as $s) {
            $toSave[$s] = isset($decoded[$s]) ? (bool)$decoded[$s] : false;
        }

        $admin = User::findIdentity(Yii::$app->user->id);
        if (!$admin) {
            return ['ok' => false];
        }

        $admin->setUserDataValue('admin_queues_collapsed', json_encode($toSave));

        return ['ok' => true];
    }

    public function actionSaveQueuesAutorefresh()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $raw     = Yii::$app->request->post('states');
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'invalid'];
        }

        $valid   = ['health', 'running', 'recent_hour', 'recent_started', 'errors', 'disabled', 'overdue', 'users'];
        $toSave  = [];
        foreach ($valid as $s) {
            $toSave[$s] = isset($decoded[$s]) ? (bool)$decoded[$s] : true;
        }

        $admin = User::findIdentity(Yii::$app->user->id);
        if (!$admin) {
            return ['ok' => false, 'error' => 'no user'];
        }

        $admin->setUserDataValue('admin_queues_autorefresh', json_encode($toSave));

        return ['ok' => true];
    }

    public function actionQueuesSections()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $raw       = Yii::$app->request->get('sections', 'all');
        $validAll  = ['health', 'running', 'recent_hour', 'recent_started', 'errors', 'disabled', 'overdue', 'users'];
        $requested = $raw === 'all'
            ? $validAll
            : array_intersect(array_map('trim', explode(',', $raw)), $validAll);

        if (!$requested) {
            return ['sections' => []];
        }

        $now = date('Y-m-d H:i:s');

        $running = Queue::find()
            ->where(['integrated' => Queue::RUNNING])
            ->orderBy(['executed_at' => SORT_ASC])
            ->all();

        $overdue = Queue::find()
            ->where(['integrated' => Queue::PENDING])
            ->andWhere(['<', 'next_integration_date', $now])
            ->orderBy(['next_integration_date' => SORT_ASC])
            ->all();

        $errors = Queue::find()
            ->where(['integrated' => Queue::ERROR])
            ->orderBy(['finished_at' => SORT_DESC])
            ->all();

        $disabled = Queue::find()
            ->where(['integrated' => Queue::DISABLED])
            ->orderBy(['finished_at' => SORT_DESC])
            ->all();

        $recentDone = Queue::find()
            ->where(['integrated' => Queue::EXECUTED])
            ->andWhere(['>=', 'finished_at', date('Y-m-d H:i:s', strtotime('-24 hours'))])
            ->orderBy(['finished_at' => SORT_DESC])
            ->all();

        $recentStarted = Queue::find()
            ->andWhere(['>=', 'executed_at', date('Y-m-d H:i:s', strtotime('-20 minutes'))])
            ->orderBy(['executed_at' => SORT_DESC])
            ->all();

        $users = User::find()
            ->where(['active' => 1])
            ->indexBy('id')
            ->all();

        $typeLabel = [
            'product'          => 'Produkty',
            'order'            => 'Zamówienia',
            'customer'         => 'Klienci',
            'category'         => 'Kategorie',
            'subscribers'      => 'Subskrybenci',
            'phone_subscriber' => 'SMS sub.',
            'phonesubscribers' => 'SMS sub.',
            'tag'              => 'Tagi',
            'countries'        => 'Kraje',
        ];

        $execLogs     = in_array('health', $requested) ? QueueExecutionLog::getRecentStats(50)  : [];
        $execSummary  = in_array('health', $requested) ? QueueExecutionLog::getSummaryByType()    : [];

        $shared = [
            'typeLabel'     => $typeLabel,
            'users'         => $users,
            'now'           => $now,
            'running'       => $running,
            'overdue'       => $overdue,
            'errors'        => $errors,
            'disabled'      => $disabled,
            'recentDone'    => $recentDone,
            'recentStarted' => $recentStarted,
            'execLogs'      => $execLogs,
            'execSummary'   => $execSummary,
        ];

        $result = [];
        foreach ($requested as $section) {
            $result[$section] = $this->renderPartial('_queues_content', array_merge($shared, ['section' => $section]));
        }

        return ['sections' => $result];
    }

    public function actionUpdate($id)
    {
        $user         = User::findOne($id);
        $checkResults = null;
        $savedOk      = false;

        if (Yii::$app->request->isPost) {
            $action = Yii::$app->request->post('_action');

            if ($action === 'check') {
                $apiKey = $user->getUserDataValue('api3_key');

                if (!$apiKey) {
                    $checkResults = ['error' => 'Brak klucza API — nie można wykonać testu.'];
                } else {
                    $client    = new ApiClient($user->username, $apiKey);
                    $endpoints = [
                        'System' => ['method' => 'GET',  'path' => '/api/admin/v3/system/config'],
                        'CRM'    => ['method' => 'GET',  'path' => '/api/admin/v4/clients/clients'],
                        'OMS'    => ['method' => 'POST', 'path' => '/api/admin/v4/orders/orders/get'],
                        'PIM'    => ['method' => 'POST', 'path' => '/api/admin/v4/products/products/get'],
                    ];
                    $checkResults = [];
                    foreach ($endpoints as $label => $ep) {
                        $res = ($ep['method'] === 'POST')
                            ? $client->post($ep['path'], [])
                            : $client->sendRequest($ep['path']);
                        $checkResults[] = ['label' => $label, 'path' => $ep['path'], 'ok' => $res !== false];
                    }
                }
            } else {
                $active   = (int) Yii::$app->request->post('active', $user->active);
                $shopType = Yii::$app->request->post('shop_type', $user->shop_type);
                $apiKey   = Yii::$app->request->post('api3_key');

                $user->active    = $active;
                $user->shop_type = $shopType;
                $user->save();

                if ($apiKey) {
                    $user->setUserDataValue('api3_key', $apiKey);
                }

                $savedOk = true;
                return $this->redirect(['admin/update', 'id' => $id]);
            }
        }

        return $this->render('settings', [
            'user'         => $user,
            'checkResults' => $checkResults,
            'savedOk'      => $savedOk,
        ]);
    }

    public function actionResetQueue($queueId)
    {
        $queue = Queue::findOne((int)$queueId);

        if ($queue) {
            $queue->setPendingStatus();
        }

        return $this->redirect(['admin/queues']);
    }

    public function actionRestartQueueOutput($queueId)
    {
        $queue = Queue::findOne((int)$queueId);

        if ($queue && $queue->integrated === Queue::RUNNING) {
            $queue->setPendingStatus();
        }

        return $this->redirect(['admin/run-queue-output', 'queueId' => $queueId]);
    }

    public function actionRunQueueOutput($queueId)
    {
        $queue = Queue::findOne((int)$queueId);

        if (!$queue) {
            die("Kolejka #$queueId nie istnieje.");
        }

        $this->layout = false;

        set_time_limit(0);
        ignore_user_abort(true);

        // Wyłącz wszystkie bufory wyjścia
        while (ob_get_level()) {
            ob_end_clean();
        }

        $type     = $queue->integration_type;
        $userId   = $queue->current_integrate_user;
        $user     = $queue->getCurrentUser();
        $username = $user ? $user->username : "user #$userId";

        header('Content-Type: text/html; charset=utf-8');
        header('X-Accel-Buffering: no');
        header('Cache-Control: no-cache');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo '<title>Kolejka #' . (int)$queueId . ' – ' . htmlspecialchars($type) . '</title>';
        echo '<style>
            body { background:#1e1e1e; color:#d4d4d4; font-family:monospace; font-size:13px; padding:20px; margin:0; }
            h2   { color:#9cdcfe; margin-bottom:4px; }
            p    { color:#888; margin:0 0 16px; }
            pre  { margin:0; white-space:pre-wrap; word-break:break-all; }
            .ok  { color:#4ec9b0; }
            .err { color:#f44747; }
            .dim { color:#666; }
            #output { border-top:1px solid #333; padding-top:12px; margin-top:8px; }
            #status { position:fixed; bottom:0; left:0; right:0; background:#252526;
                      border-top:1px solid #333; padding:8px 20px; font-size:12px; color:#888; }
        </style></head><body>';
        echo '<h2>Kolejka #' . (int)$queueId . ' &mdash; ' . htmlspecialchars($type) . '</h2>';
        echo '<p>Użytkownik: <strong style="color:#ce9178">' . htmlspecialchars($username) . '</strong>'
           . ' &nbsp;|&nbsp; Strona: ' . (int)$queue->page . '/' . (int)$queue->max_page . '</p>';
        echo '<div id="output"><pre>';
        flush();

        // Przechwytuj echo z serwisu przez output buffering + flush
        ob_start(function (string $chunk): string {
            return '<span>' . htmlspecialchars($chunk, ENT_QUOTES) . '</span>';
        }, 1);

        (new QueueRunnerService())->runById((int)$queueId);

        ob_end_flush();
        flush();

        echo '</pre></div>';
        echo '<div id="status"><span class="ok">✔ Zakończono</span> &nbsp;|&nbsp; '
           . '<a href="javascript:window.close()" style="color:#569cd6;">Zamknij okno</a>'
           . ' &nbsp;|&nbsp; <a href="' . Url::to(['admin/view', 'id' => $userId]) . '" style="color:#569cd6;" target="_parent">Wróć do kolejki</a>'
           . '</div>';
        echo '</body></html>';
        Yii::$app->response->isSent = true;
        Yii::$app->end();
    }

    public function actionView($id)
    {
        $user = User::findOne($id);

        $filterType   = Yii::$app->request->get('type');
        $filterStatus = Yii::$app->request->get('status');

        // Wszystkie wpisy do podsumowania (bez limitu filtrów)
        $allItems = Queue::find()
            ->where(['current_integrate_user' => $id])
            ->all();

        // Zapytanie z filtrami do tabeli
        $query = Queue::find()
            ->where(['current_integrate_user' => $id])
            ->orderBy(['next_integration_date' => SORT_DESC])
            ->limit(200);

        if ($filterType) {
            $query->andWhere(['integration_type' => $filterType]);
        }

        if ($filterStatus !== null && $filterStatus !== '') {
            if ($filterStatus === 'overdue') {
                $query->andWhere(['integrated' => Queue::PENDING])
                      ->andWhere(['<', 'next_integration_date', date('Y-m-d H:i:s')]);
            } else {
                $statusMap = [
                    'pending'  => Queue::PENDING,
                    'running'  => Queue::RUNNING,
                    'executed' => Queue::EXECUTED,
                    'missed'   => Queue::MISSED,
                    'disabled' => Queue::DISABLED,
                    'error'    => Queue::ERROR,
                ];
                if (isset($statusMap[$filterStatus])) {
                    $query->andWhere(['integrated' => $statusMap[$filterStatus]]);
                    if ($filterStatus === 'pending') {
                        $query->andWhere(['>=', 'next_integration_date', date('Y-m-d H:i:s')]);
                    }
                }
            }
        }

        $hardResetDates = [];
        foreach (['order', 'customer', 'product', 'category', 'subscribers', 'phonesubscribers', 'tags', 'countries'] as $t) {
            $hardResetDates[$t] = $user->getUserDataValue('hard_reset_' . $t);
        }

        return $this->render('view', [
            'user'           => $user,
            'allItems'       => $allItems,
            'queueItems'     => $query->all(),
            'filterType'     => $filterType,
            'filterStatus'   => $filterStatus,
            'hardResetDates' => $hardResetDates,
        ]);
    }

    public function actionRefreshFeedCounts()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $userId = (int) Yii::$app->request->post('userId');
        $user   = User::findOne($userId);
        if (!$user) {
            return ['ok' => false, 'error' => 'not found'];
        }

        $tagMap  = ['product' => 'PRODUCT', 'order' => 'ORDER', 'customer' => 'CUSTOMER', 'category' => 'ITEM'];
        $counts  = ['ts' => time()];
        $storage = \app\services\FeedStorageService::isConfigured()
            ? \app\services\FeedStorageService::create()
            : null;

        foreach ($tagMap as $type => $tag) {
            if ($storage) {
                $key = $type . '/' . $user->uuid . '/' . $type . '.xml';
                if ($storage->exists($key)) {
                    $counts[$type] = substr_count($storage->get($key), '<' . $tag . '>');
                } else {
                    $counts[$type] = null;
                }
            } else {
                $base = \app\modules\xml_generator\src\XmlFeed::getFeedsBasePath();
                $file = $base . '/' . $type . '/' . $user->uuid . '/' . $type . '.xml';
                $counts[$type] = file_exists($file)
                    ? substr_count(file_get_contents($file), '<' . $tag . '>')
                    : null;
            }
        }

        $user->setUserDataValue('feed_counts', json_encode($counts));

        return ['ok' => true, 'userId' => $userId, 'counts' => $counts];
    }

    public function actionViewRecentStarted($userId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $items = Queue::find()
            ->where(['current_integrate_user' => (int)$userId])
            ->andWhere(['is not', 'executed_at', null])
            ->orderBy(['executed_at' => SORT_DESC])
            ->limit(20)
            ->all();

        return [
            'html' => $this->renderPartial('_view_recent_started', [
                'items'  => $items,
                'userId' => (int)$userId,
            ]),
        ];
    }

    public function actionHardResetType()
    {
        $userId = (int) Yii::$app->request->post('userId');
        $type   = Yii::$app->request->post('type');

        $user = User::findOne($userId);
        if (!$user || !$type) {
            Yii::$app->session->addFlash('error', 'Nieprawidłowe parametry resetu.');
            return $this->redirect(['admin/view', 'id' => $userId]);
        }

        // 1. Usuń integration_data powiązane z tym typem
        $integrationDataKeys = [
            'order'            => ['last_orders_integration_date', 'IS_NEW_ORDER', 'INITIAL_ORDERS_DONE'],
            'customer'         => ['last_customer_integration_date', 'IS_NEW_CUSTOMER', 'INITIAL_CUSTOMERS_DONE'],
            'product'          => ['last_products_integration_date', 'INITIAL_PRODUCTS_DONE'],
            'category'         => [],
            'subscribers'      => ['LAST_SUBSCRIBER_INTEGRATION_DATE', 'INITIAL_SUBSCRIBERS_DONE'],
            'phonesubscribers' => ['LAST_PHONESUBSCRIBER_INTEGRATION_DATE', 'INITIAL_PHONESUBSCRIBERS_DONE'],
            'tags'             => [],
            'countries'        => [],
        ];

        foreach ($integrationDataKeys[$type] ?? [] as $key) {
            IntegrationData::deleteAll(['task' => $key, 'customer_id' => $userId]);
        }

        // 2. Nowa kolejka danych z datą z przeszłości (wczoraj)
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 day'));

        $dataQueue                          = new Queue();
        $dataQueue->current_integrate_user  = $userId;
        $dataQueue->integration_type        = $type;
        $dataQueue->integrated              = Queue::PENDING;
        $dataQueue->next_integration_date   = $pastDate;
        $dataQueue->page                    = 0;
        $dataQueue->max_page                = 0;
        $dataQueue->parameters              = '';
        $dataQueue->save(false);

        // 3. Nowa kolejka XML 10 minut po kolejce danych
        $pastDateXml = date('Y-m-d H:i:s', strtotime($pastDate . ' + 10 minutes'));

        $xmlQueue                         = new Queue();
        $xmlQueue->current_integrate_user = $userId;
        $xmlQueue->integration_type       = $type;
        $xmlQueue->integrated             = Queue::PENDING;
        $xmlQueue->next_integration_date  = $pastDateXml;
        $xmlQueue->page                   = 0;
        $xmlQueue->max_page               = 0;
        $xmlQueue->setAdditionalParameters(['objects_done' => 1]);
        $xmlQueue->save(false);

        $user->setUserDataValue('hard_reset_' . $type, date('Y-m-d H:i:s'));

        Yii::$app->session->addFlash('success', 'Twardy reset kolejki „' . $type . '" wykonany.');
        return $this->redirect(['admin/view', 'id' => $userId]);
    }

    public function actionIndex()
    {
        $user = User::findIdentity(Yii::$app->user->id);

        // Load cached feed counts for all users — single DB query
        $rows      = \app\models\UserData::find()->where(['name' => 'feed_counts'])->all();
        $countsMap = [];
        foreach ($rows as $row) {
            $countsMap[$row->user_id] = json_decode($row->value, true);
        }

        return $this->render('index', [
            'user'      => $user,
            'countsMap' => $countsMap,
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
