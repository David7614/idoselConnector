<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
use app\services\FeedStorageService;
use yii\web\Controller;

class ProductsController extends Controller
{
    public function actionGenerate($uuid)
    {
        if(($_user = User::findByUUID($uuid)) === null)
        {
            return 'User not found';
        }

        if ($_user->shop_type=='shoper'){
            $integrator=\app\modules\shoper\models\Integrator::findOne(['shop_url'=>'https://'.$_user->username]);
            if (is_file($integrator->getProductsFile())){
                $products_file=file_get_contents($integrator->getProductsFile());
                
                header('Content-type: application/xml; charset=utf-8');
                echo $products_file;
                die;
            }
            return 'Not ready yet';
        }

        if (FeedStorageService::isConfigured()) {
            try {
                $storage = FeedStorageService::create();
                $key     = 'product/' . $_user->uuid . '/product.xml';

                if (!$storage->exists($key)) {
                    header('Content-type: application/xml; charset=utf-8');
                    echo '<?xml version="1.0"?><INFO><NOTICE>Feed is generating. Please try later.</NOTICE></INFO>';
                    die;
                }

                header('Content-type: application/xml; charset=utf-8');
                header('Content-Disposition: attachment; filename="products.xml"');
                $storage->stream($key);
                die;
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }

        try {
            $products = new XmlFeed();
            $products->setType(XmlFeed::PRODUCT);
            $products->setUser($_user);
            $products_file_path = $products->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $filename = 'products.xml';
        header('Content-type: application/xml; charset=utf-8');
        header("Content-Length: " . filesize(trim($products_file_path)));
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header("Content-Transfer-Encoding: binary");
        @readfile($products_file_path);
        die;
    }
}