<?php
namespace app\commands;

use app\models\Customers;
use yii\console\Controller;

class UploadTagsController extends Controller
{
    public $path;
    public $tag;

    public function options($actionID)
    {
        return ['path','tag'];
    }

    public function actionIndex()
    {
        if(!isset($this->path) || !isset($this->tag)) {
            echo "Path must be set!";
            return 1;
        }

        $csv = $this->csv_to_array(__DIR__.'/'.$this->path, ';');
        echo count($csv); 
        $i = 0;
        $a = 0;
        $y = 0;
        foreach($csv as $item) {
            $id = $item['ID'];
            if(($customer = Customers::find()->where(['customer_id' => $id])->one()) !== null) {
                if(strpos($customer->tags, $this->tag) !== false) {
               //   echo "Tag $this->tag is set for customer $customer->customer_id";
                    $i++;
		    continue;
                }
                $tags = unserialize($customer->tags);
                $tags[] = [
                    'tagName' => $this->tag,
                    'tagId' => 0,
                    'tagValue' => '1'
                ];

                $customer->tags = serialize($tags);
                var_dump($customer->save(false));
                $y++;
            } else {
     		$a++;
	           echo "Customer $id not found in database \n";
            }
        }
        echo "Istniejacych: $i\nDodanych: $y\nNieistniejacych: $a";

        return 0;
    }

        private function csv_to_array($filename='', $delimiter=',')
        {
            if(!file_exists($filename) || !is_readable($filename))
                return FALSE;
    
            $header = NULL;
            $data = array();
            if (($handle = fopen($filename, 'r')) !== FALSE)
            {
                while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
                {
                    if(!$header)
                        $header = $row;
                    else
                        $data[] = array_combine($header, $row);
                }
                fclose($handle);
            }
            return $data;
        }
}
