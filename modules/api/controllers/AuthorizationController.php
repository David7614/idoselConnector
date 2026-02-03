<?php
namespace app\modules\api\controllers;

use app\exceptions\InvalidGrandTypeException;
use app\models\User;
use app\modules\api\src\ApplicationState;
use app\modules\api\src\KeyStorage;
use app\modules\IAI\Application\Config;
use app\modules\IAI\Authorization\Oauth2Client;
use app\modules\IAI\Authorization\OpenIdClient;
use yii\filters\AccessControl;
use yii\helpers\Json;

class AuthorizationController extends RestController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        return $behaviors + [
            'apiauth' => [
                'class' => \app\modules\api\behaviors\Apiauth::className(),
                'exclude' => ['authorize', 'access-token', 'public-key'],
                'callback' => [],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => [
                            'authorize',
                            'access-token',
                            'public-key'
                        ],
                        'allow' => true,
                        'roles' => ['?']
                    ],
                    [
                        'actions' => [],
                        'allow' => true,
                        'roles' => ['@']
                    ],
                    [
                        'actions' => [],
                        'allow' => true,
                        'roles' => ['*']
                    ]
                ]
            ],
                'verbs' => [
                    'class' => \app\modules\api\behaviors\VerbCheck::className(),
                    'actions' => [
                        'access-token' => ['GET', 'POST'],
                        'authorize' => ['GET', 'POST'],
                        'public-key' => ['GET', 'POST'],
                    ]
                ]
            ];
    }

    public function actionAuthorize()
    {
        file_put_contents(__DIR__.'/test.txt', file_get_contents('php://input'));
        var_dump(file_get_contents('php://input'));

        // try {
        //     $base = str_replace(['Bearer ', 'Basic '], '', $this->headers['Authorization']);
        //     $authorization = explode(':', base64_decode($base));

        //     if (($user = User::findByClientID($authorization[0])) === null) return \Yii::$app->api->sendFailedResponse('Client ID is incorrect ' . $authorization[0]);

        //     if (User::validateSecretKey($authorization[0], $authorization[1])) {

        //         try {
        //             $api = \Yii::$app->api;
        //             $grant_type = $api->checkGrantType(\Yii::$app->request->post('grant_type'));

        //             switch ($grant_type) {
        //                 case 'client_credentials':
        //                     $authorization_code = $api->createAuthorizationCode($user->id);
        //                     return Json::encode($api->createAccesstoken($authorization_code->code), JSON_PRETTY_PRINT);
        //                     break;
        //                 case 'refresh_token':
        //                     return Json::encode($api->refreshAccesstoken(\Yii::$app->request->post('refresh_token')), JSON_PRETTY_PRINT);
        //                     break;
        //                 default:
        //                     break;
        //             }
        //         } catch (\Exception $e) {
        //             \Yii::error($e->getMessage(), 'api');
        //             return \Yii::$app->api->sendFailedResponse($e->getMessage());
        //         }
        //     }

        //     return \Yii::$app->api->sendFailedResponse('Client credentials are invalid');
        // } catch (\InvalidArgumentException $e) {
        //     return \Yii::$app->api->sendFailedResponse("{$e->getMessage()} on line {$e->getLine()}");
        // }
    }

    public function actionAccessToken()
    {

    }

    public function actionPublicKey()
    {

    }
}