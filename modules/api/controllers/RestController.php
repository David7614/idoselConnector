<?php
namespace app\modules\api\controllers;

use yii\web\Controller;

class RestController extends Controller
{
    public $request;
    public $request_post;
    public $enableCsrfValidation = false;
    public $headers;

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::className(),
            'cors' => [
                'Origin' => ['*'],
                'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => null,
                'Access-Control-Max-Age' => 86400,
                'Access-Control-Expose-Headers' => []
            ],
        ];
        return $behaviors;
    }

    /**
     * {@inheritDoc}
     */
    public function init()
    {
        $content = file_get_contents('php://input');
        $this->request = json_decode($content, true);
        $this->headers = \Yii::$app->request->getHeaders();
    }
}