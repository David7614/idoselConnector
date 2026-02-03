<?php
namespace app\extensions;

use yii;
use yii\base\Application;
use yii\base\BootstrapInterface;

class ModuleBootstrap implements BootstrapInterface
{
    /**
     * @param Application $app
     */
    public function bootstrap($app)
    {
        $moduleList = $app->getModules();
        foreach($moduleList as $key => $module) {
            if(is_array($module) && strpos($module['class'], 'app\modules') === 0) {
                $fileConfigPath = Yii::$app->basePath.'/modules/'.$key.'/config/_routes.php';
                if(file_exists($fileConfigPath)) {
                    $app->getUrlManager()->addRules(require($fileConfigPath));
                }
            }
        }
    }
}