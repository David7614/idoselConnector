<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\api\src\ApplicationState;
use app\modules\api\src\Connection;
use app\modules\api\src\KeyStorage;
use app\modules\IAI\Application\Config;
use app\modules\IAI\Authorization\Oauth2Client;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
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
        if (FeedStorageService::isConfigured()) {
            try {
                $storage = FeedStorageService::create();
                $key     = 'customer/' . $_user->uuid . '/customer.xml';

                if (!$storage->exists($key)) {
                    header('Content-type: application/xml; charset=utf-8');
                    echo '<?xml version="1.0"?><INFO><NOTICE>Feed is generating. Please try later.</NOTICE></INFO>';
                    die;
                }

                $content = $storage->get($key);
                header('Content-type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename="customers.xml"');
                header('Content-Length: ' . strlen($content));
                echo $content;
                die;
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }

        try {
            $customers = new XmlFeed();
            $customers->setType(XmlFeed::CUSTOMER);
            $customers->setUser($_user);
            $customers_file_path = $customers->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $filename = 'customers.xml';
        header('Content-type: application/xml; charset=utf-8');
        header("Content-Length: " . filesize(trim($customers_file_path)));
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Transfer-Encoding: binary");
        @readfile($customers_file_path);
        die;
    }
}