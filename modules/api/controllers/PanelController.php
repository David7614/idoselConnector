<?php
namespace app\modules\api\controllers;

use app\modules\api\behaviors\Apiauth;
use app\modules\api\behaviors\VerbCheck;
use yii\filters\AccessControl;

class PanelController extends RestController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        return $behaviors + [
                'apiauth' => [
                    'class' => Apiauth::className(),
                    'exclude' => [],
                    'callback' => [],
                ],
                'access' => [
                    'class' => AccessControl::className(),
                    'rules' => [
                        [
                            'actions' => [],
                            'allow' => true,
                            'roles' => ['?']
                        ],
                        [
                            'actions' => [
                                'index'
                            ],
                            'allow' => true,
                            'roles' => ['*']
                        ]
                    ]
                ],
                'verbs' => [
                    'class' => VerbCheck::className(),
                    'actions' => [
                        'index' => ['GET', 'POST', 'DELETE', 'PUT'],
                    ]
                ]
            ];
    }

    public function actionIndex()
    {
        echo "Zautoryzowano";
    }
}