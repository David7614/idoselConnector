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

class CategoriesController extends Controller
{

    public function actionGenerate($uuid)
    {
        if(($_user = User::findByUUID($uuid)) === null)
        {
            return ['error' => 'User not found'];
        }

        if ($_user->shop_type=='shoper'){
            $integrator=\app\modules\shoper\models\Integrator::findOne(['shop_url'=>'https://'.$_user->username]);
            if (is_file($integrator->getCategoriesFile())){
                $products_file=file_get_contents($integrator->getCategoriesFile());
                
                header('Content-type: application/xml; charset=utf-8');
                echo $products_file;
                die;
            }
            return 'Not ready yet';
        }

        if (FeedStorageService::isConfigured()) {
            try {
                $storage = FeedStorageService::create();
                $key     = 'category/' . $_user->uuid . '/category.xml';

                if (!$storage->exists($key)) {
                    header('Content-type: application/xml; charset=utf-8');
                    echo '<?xml version="1.0"?><INFO><NOTICE>Feed is generating. Please try later.</NOTICE></INFO>';
                    die;
                }

                $content = $storage->get($key);
                header('Content-type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename="categories.xml"');
                header('Content-Length: ' . strlen($content));
                echo $content;
                die;
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }

        try {
            $customers = new XmlFeed();
            $customers->setType(XmlFeed::CATEGORY);
            $customers->setUser($_user);
            $customers_file = $customers->getFile();
        } catch (\Exception $e) {
            return $e;
        }

        header('Content-type: application/xml; charset=utf-8');
        echo $customers_file;
        die;
    }
}