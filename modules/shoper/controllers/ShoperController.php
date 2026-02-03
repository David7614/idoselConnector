<?php

namespace app\modules\shoper\controllers;

use yii;
use yii\web\Controller;
use app\models\User;
use app\modules\shoper\models\ShoperShops;
use app\models\RegisterForm;
use app\models\IntegrationData;
// use yii\filters\AccessControl;
// use yii\filters\VerbFilter;

class ShoperController extends Controller{
	protected $referer;
	protected $user;
	const SHOP_TYPE='shoper';

	// public function behaviors()
 //    {
 //        return [
 //            'access' => [
 //                'class' => AccessControl::className(),
 //                'only' => ['index'],
 //                'rules' => [
 //                    [
 //                        'actions' => ['index'],
 //                        'allow' => true,
 //                        'roles' => ['@'],
 //                    ],
 //                ],
 //            ],
 //            'verbs' => [
 //                'class' => VerbFilter::className(),
 //                'actions' => [
 //                    'index' => ['get', 'post'],
 //                ],
 //            ],
 //        ];
 //    }

	public function beforeAction($action)
	{
		// if (isset($_GET['test'])){
  //           $_POST = [
  //               'action' => 'install',
  //               'application_code' => '9fef6a4ef92852982ae4f00b8e99a132',
  //               'application_version' => '2',
  //               'auth_code' => 'f146851b4879fcf0d1a3d08b925c307d',
  //               'shop' => 'e5c4d9f570a8da4a3cb8dccbd70ab2cf5ffc8382',
  //               'shop_url' => 'https://devshop-545554.shoparena.pl',
  //               'timestamp' => '2022-04-05 11:21:42',
  //               'hash' => 'cbeec490eb23598fa74c791ad821eea9facc6681cd8f11af936dea19c1cecb5414ace8beb787b4ed7a421fe138db718d869c27191f3ef50f5dc0183a4808c801'
  //           ];
  //       }
		if (isset($_GET['shop']) ){
			
		    $shoperShopModel=ShoperShops::findOne(['shop'=>$_GET['shop']]);	    
		    if ($shoperShopModel){ // aplikacja zainstalowana
			    $domain = preg_match('/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/', $shoperShopModel->url, $matches);
			    $this->referer=$matches[0];
			    $this->user = User::findByUsername($matches[0]);
			    if (!$this->user){
			    	$shoperShopModel=ShoperShops::findOne(['shop'=>$_GET['shop']]);
			    	$model = new RegisterForm();
			        $model->username=$this->referer;
			        $model->email=$_GET['shop'].'@shoper.pl';
			        $model->password='cokolwieknieistotne';
			        $model->shop_type=self::SHOP_TYPE;
			        $model->active=1;
			        if ($model->register()){
			        	$model->login();
			        	IntegrationData::setIsNew('ORDER', true, Yii::$app->user->id);
		                IntegrationData::setIsNew('CUSTOMER', true, Yii::$app->user->id);
			        }
			        $this->user = User::findByUsername($matches[0]);
			    }
		    	// Yii::$app->user->login($user, 0);
		    }
		}else{
			if ($action->id != 'billing'){
				die ("bad request");
			}
		}

	    if ($action->id == 'billing' || $action->id == 'index') {
	        $this->enableCsrfValidation = false;
	    }

	    if (!parent::beforeAction($action)) {
	        return false;
	    }

	    // other custom code here

	    return true; // or false to not run the action
	}
}