<?php
namespace app\commands;

use app\models\Queue;
use app\modules\api\src\Connection;
use app\modules\shoper\models\Integrator;
use app\modules\xml_generator\src\IdioselClient;
use app\modules\xml_generator\src\Magazine;
use app\modules\xml_generator\src\SoapRequest;
use app\modules\xml_generator\src\XmlFeed;
use app\modules\xml_generator\src\OrderFeed;
use Exception;
use InvalidArgumentException;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\models\User;
use SoapClient;
use app\models\Customers;
use app\modules\shoper\models\ShoperShops;

class ManualXmlController extends Controller
{
    public function actionTest(){
        die ("TESCIK");
    }
    public function actionIndex(){
        die ("manual xml index");
    }
}
