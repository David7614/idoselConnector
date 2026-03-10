<?php
namespace app\modules\xml_generator\src;

use SoapClient;

use app\models\Customers;
use app\models\IntegrationData;
use app\modules\idosellv3\models\ApiClient;
use app\models\Queue;

class Tags extends XmlFeed
{
    private $package = 200; // 20000 wtf
    private $apiMethod          = '/api/admin/v4/clients/tags';
    private $request_parameters = [];
    private $_client;

    public function generate($what = null): int
    {

        if ($what===null){
            return 10;
        }

        $doTags = $this->_user->config->get('customer_tags');

        if (!$doTags){
            return 10;
        }



        $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());


        


        $customers_query = Customers::find()
            ->where(['user_id' => $this->_user->id])
            ->andWhere([
                'or',
                ['newsletter_frequency' => 'every day'],
                ['sms_frequency' => 'every day']
            ])
            ->andWhere(['>=', 'last_modification_date', new \yii\db\Expression('NOW() - INTERVAL 7 DAY')]);


        if($this->_queue->max_page == 0) {
            $all = $customers_query->count();

            $pages = ceil($all / $this->package);
            $this->_queue->setMaxPages($pages);
        }


 

        $customers = $customers_query
            ->limit($this->package)
            ->offset($this->_queue->page*$this->package)
            ->all();


        $i=0;    
        foreach($customers as $customer) {
            try{ 
                if (Queue::isDisallowedEmail($customer['email'])) { // ommit allegro etc
                    continue;
                }
                $request                 = $this->request_parameters;
                $request['clientId'] = $customer->customer_id;
                var_dump($request);
                // var_dump(http_build_query($request));
                $response = $this->_client->get($this->apiMethod, $request);

                var_dump($response);
                if(empty($response['results'])) {

                    if($customer->tags == null) {
                        $customer->tags = serialize([]);
                    }
                    

                    $customer->server_response = serialize($response);

                    if(strpos($customer->server_response, "login") !== false) {
                        $customer->error = "login_error";
                        $this->_queue->page = $this->_queue->max_page;
                        $this->_queue->integrated = 2;
                        $this->_queue->save();
                        IntegrationData::setLastIntegrationDate($customer->last_modification_date, $this->_user->id);
                        return true;
                    } else {
                        $customer->error = "";
                    }

                    $customer->save(false);
                    $i++;


                    continue;
                }

                $tags = [];
                foreach($response['results'] as $result) {
                    $tags[] = [
                        'tagName' => $result['tagName'],
                        'tagId' => $result['tagId'],
                        'tagValue' => $result['tagValue']
                    ];
                }
                $customer->server_response = null;
                $customer->error = null;
                $customer->tags = serialize($tags);
                $customer->save(false);
                // var_dump(serialize($tags));
                //     die ("TESTY");
            } catch (\Exception $e) {
                echo $e->getMessage();
            }

            $i++;
        }

        $this->_queue->increasePage();

        if($this->_queue->page >= $this->_queue->max_page) {
            IntegrationData::setLastIntegrationDate(date('Y-m-d'), $this->_user->id);
            return 10;
        }
        return true;
    }

    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return "";
    }
}
