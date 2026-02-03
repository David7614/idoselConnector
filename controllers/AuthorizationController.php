<?php
namespace app\controllers;

use app\models\IntegrationData;
use app\models\LoginForm;
use app\models\RegisterForm;
use app\models\User;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\Controller;
use yii\web\Response;

class AuthorizationController extends Controller
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

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->redirect(Url::toRoute(['site/index']));
        }

        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionRegister()
    {
        if (! Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new RegisterForm();
        try {
            if ($model->load(Yii::$app->request->post()) && $model->register()) {
                $model->login();

                IntegrationData::setIsNew('ORDER', true, Yii::$app->user->id);
                IntegrationData::setIsNew('CUSTOMER', true, Yii::$app->user->id);
                return $this->redirect(Url::toRoute(['site/index']));
            }
        } catch (\Exception $e) {
            Yii::$app->session->addFlash('error', $e->getMessage());
            return $this->redirect(Url::toRoute(['authorization/register']));
        }

        return $this->render('register', [
            'model' => $model,
        ]);
    }
    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

}
