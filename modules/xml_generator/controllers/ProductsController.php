<?php
namespace app\modules\xml_generator\controllers;

use app\models\User;
use app\modules\xml_generator\src\XmlFeed;
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

        try {
            $products = new XmlFeed();
            $products->setType(XmlFeed::PRODUCT);
            $products->setUser($_user);
            $products_file_path = $products->getFile(true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        header('Content-type: application/xml; charset=utf-8');
        // echo $products_file;
        $filename='products.xml';
        header('Content-type: application/xml; charset=utf-8');
        // echo $products_file_path;
        header("Content-Length: ".filesize(trim($products_file_path)));
                header("Content-Disposition: attachment; filename=\"$filename\"");
                // Force the download           
                header("Content-Transfer-Encoding: binary");            
                @readfile($products_file_path);    
        die;
    }
}