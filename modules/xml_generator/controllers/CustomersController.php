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

class CustomersController extends Controller
{
    public function actionGenerate($uuid)
    {
        if(($_user = User::findByUUID($uuid)) === null)
        {
            return 'User not found';
        }
        if ($_user->shop_type=='shoper'){
            $integrator=\app\modules\shoper\models\Integrator::findOne(['shop_url'=>'https://'.$_user->username]);
            if (is_file($integrator->getCustomersFile())){
                $products_file=file_get_contents($integrator->getCustomersFile());
                
                header('Content-type: application/xml; charset=utf-8');
                echo $products_file;
                die;
            }
            return 'Not ready yet';
        }
        try {
            $customers = new XmlFeed();
            $customers->setType(XmlFeed::CUSTOMER);
            $customers->setUser($_user);
            $customers_file_path = $customers->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        header('Content-type: application/xml; charset=utf-8');
        header('Content-type: application/xml; charset=utf-8');
        // echo $products_file;
        $filename='customers.xml';
        header('Content-type: application/xml; charset=utf-8');
        // echo $customers_file_path;
        header("Content-Length: ".filesize(trim($customers_file_path)));
                header("Content-Disposition: attachment; filename=\"$filename\"");
                // Force the download           
                header("Content-Transfer-Encoding: binary");            
                @readfile($customers_file_path);    
        die;
    }
}