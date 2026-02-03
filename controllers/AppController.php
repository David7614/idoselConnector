<?php
namespace app\controllers;

use app\models\IdiosellApp;
use app\models\User;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;

class AppController extends Controller
{


    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only'  => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow'   => true,
                        'roles'   => ['@'],
                    ],
                ],
            ],
            'verbs'  => [
                'class'   => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post', 'get'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        if ($action->id == 'enable') {
            $this->enableCsrfValidation = false;
        }
        if ($action->id == 'disable') {
            $this->enableCsrfValidation = false;
        }
        if ($action->id == 'licence') {
            $this->enableCsrfValidation = false;
        }

        if ($action->id == 'login') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Enable app action.
     *
     * @return Response|string
     */
    public function actionEnable()
    {

        // weryfikacja czy mam usera odpowiadającego api url z obciętym /api/admin/
        // - jeśli nie ma to tworzymy usera i aktywujemy mu konto
        // - jeśli jest to weryfikujemy czy ma aktywne konto
        // odpowiadamy z tym signaturem jebananym

        // w tak zwanym międzyczasie trzeba poinformować idosell że aplikancja została odpalona

        $request = Yii::$app->request;
        $data = json_decode($request->getRawBody(), true);

        if (!isset($data['api_url'])){
            return $this->asJson([
                'status' => 'error',
                'sign' => IdiosellApp::getSign()
            ]);
        }

        $sign=IdiosellApp::getSign();
        if ($sign != $data['sign']){
            return $this->asJson([
                'status' => 'error',
                'sign' => IdiosellApp::getSign()
            ]);
        }

        $user=IdiosellApp::getUser($data);
        $user->user_type='idoapp';
        $user->setActive(1);
        $key=IdiosellApp::decryptApiKey($data['api_key']);
        $user->setUserDataValue('api3_key', $key);
        $user->setUserDataValue('client_id', (string) $data['client_id']);
        $user->setUserDataValue('application_id', (string) $data['application_id']);
        $user->setUserDataValue('api_url', (string) $data['api_url']);
        $user->setUserDataValue('api_license', (string) $data['api_license']);
        $user->setUserDataValue('sign', $data['sign']);
        $user->setUserDataValue('api_key', (string) $data['api_key']);
        // weryfikacja usera i dodanie mu klucza api $key

        IdiosellApp::confirmInstall($user);





        return $this->asJson([
            'status' => 'OK',
            'sign' => IdiosellApp::getSign()
        ]);
    }

    public function actionConfirm($id) // zbędne bo confirm idzie od razu po dodaniu usera i wystarcza
    {
        die ("manual confirmation disabled");
        $user=User::findOne($id);
        $response = IdiosellApp::confirmInstall($user);
        if ($response->isOk) {
            return $this->asJson(['success' => true, 'response' => $response->data]);
        } else {
            return $this->asJson(['success' => false, 'error' => $response->content]);
        }
        // echo "confirm madafaka";
    }
    public function actionDisable()
    {
        $request = Yii::$app->request;
        $data = json_decode($request->getRawBody(), true);

        if (!isset($data['api_url'])){
            return $this->asJson([
                'status' => 'error',
                'sign' => IdiosellApp::getSign()
            ]);
        }

        $sign=IdiosellApp::getSign();
        if ($sign != $data['sign']){
            return $this->asJson([
                'status' => 'error',
                'sign' => IdiosellApp::getSign()
            ]);
        }

        $user=IdiosellApp::getUser($data);
        $user->setActive(0);
    }
    public function actionLicence()
    {

    }
    public function actionLogin()
    {
        $request = Yii::$app->request;
        $data = json_decode($request->getRawBody(), true);

        if (!isset($data['api_url'])){
            return $this->asJson([
                'status' => 'error',
                'sign' => IdiosellApp::getSign()
            ]);
        }

        $sign=IdiosellApp::getSign();
        if ($sign != $data['sign']){
            return $this->asJson([
                'status' => 'error',
                'sign' => IdiosellApp::getSign()
            ]);
        }
        if(Yii::$app->user->getIdentity()) {
            return $this->asJson([
                'status' => 'OK',
                'sign' => IdiosellApp::getSign(),
                'redirect' => Url::toRoute(['site/panel'], true)
            ]);
        }

        $username=IdiosellApp::trimUrl($data['api_url']);
        $user = User::findByUsername($username);
        if ($user === null) {
            return 'User nofound';;
        }

        if ($data['client_id'] != $user->getUserDataValue('client_id')){
            return 'client id error';
        }
        if ($data['api_license'] != $user->getUserDataValue('api_license')){
            return 'licence error';
        }

        // Yii::$app->user->login($user, 0);
        // var_dump(Yii::$app->user->getIdentity());
        return $this->asJson([
            'status' => 'OK',
            'sign' => IdiosellApp::getSign(),
            'redirect' => Url::toRoute(['site/panel', 'token'=>$user->getToken(), 'userid'=>$user->id], true)
        ]);


    }



}
