<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\api\src\ApplicationState;
use app\modules\api\src\Connection;
use app\modules\api\src\KeyStorage;
use app\modules\IAI\Application\Config;
use app\modules\IAI\Authorization\Oauth2Client;
use app\modules\xml_generator\src\XmlFeed;
use SoapClient;
use yii\web\Controller;
use Yii;
use yii\web\Response;
use yii\web\XmlResponseFormatter;

class OrdersController extends Controller
{
    public function actionGenerate($uuid)
    {

        if(($_user = User::findByUUID($uuid)) === null)
        {
            return "User not found";
        }
        if ($_user->shop_type=='shoper'){
            // echo $uuid."<br>";
            // echo $_user->url."<br>";
            // echo $_user->username."<br>";
            // die();
            $integrator=\app\modules\shoper\models\Integrator::findOne(['shop_url'=>'https://'.$_user->username]);
            if (is_file($integrator->getOrdersFile())){
                $products_file=file_get_contents($integrator->getOrdersFile());
                
                header('Content-type: application/xml; charset=utf-8');
                echo $products_file;
                die;
            }
            return 'Not ready yet';
        }
        try {
            $orders = new XmlFeed();
            $orders->setType(XmlFeed::ORDER);
            $orders->setUser($_user);
            $orders_file_path = $orders->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        $filename='orders.xml';
        header('Content-type: application/xml; charset=utf-8');
        // echo $orders_file_path;
        header("Content-Length: ".filesize(trim($orders_file_path)));
                header("Content-Disposition: attachment; filename=\"$filename\"");
                // Force the download           
                header("Content-Transfer-Encoding: binary");            
                @readfile($orders_file_path);       
        die;
    }
}