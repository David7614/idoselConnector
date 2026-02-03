<?php

namespace app\modules\xml_generator\src;

use app\models\Customers;
use app\models\IntegrationData;
use app\models\IdoselSubscriptions;
use app\models\Queue;
use phpDocumentor\Reflection\File;
// use SoapClient;
use app\modules\idosellv3\models\ApiClient;

class PhonesubscribersFeed extends XmlFeed
{

    private $_client;
    private $request_parameters = [];
    private $apiMethod          = '/api/admin/v5/clients/newsletter/sms/search';
    // private $_request;
    const API_RESULT_COUNT=100;
    const API_PAGE_INCREMENT=5;
    const XML_PAGE_SIZE=10000; // 50000

    /**
     * @return bool
     *
     * @throws \Exception
     */
    public function generate($what = null): int
    {

        $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());


        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        if($what == 'objects') {
            echo "creating objects".PHP_EOL;

            return $this->createPhoneSubscriberObjects();

        }

        return 10;
    }

    /**
     * @param bool $get_file_path
     * @param bool $temp
     *
     * @return string
     */
    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return parent::getFile($get_file_path, $temp);
    }

    private function checkQueueConstraints(){ // todo
        
        if ($this->_queue->max_page>0){
            return false; // no need every time    
        }
        $request=$this->request_parameters;
        $request['resultsLimit'] = 1;
        $response                = $this->_client->post($this->apiMethod, $request);

        if (!$response['results_number_all']){
            echo "no results".PHP_EOL;
            return false;    
        }
        
        $maxPage=ceil($response['results_number_all']/self::API_RESULT_COUNT);
        if ($this->_queue->max_page<$maxPage){
            $this->_queue->max_page=$maxPage;
            $this->_queue->save();
        }
        
        return true; 
    }


    private function createPhoneSubscriberObjects()
    {
        echo "function createPhoneSubscriberObjects ".PHP_EOL;
        if (IntegrationData::getData('INITIAL_PHONESUBSCRIBERS_DONE', $this->_user->id)) {
                // $this->_request->addParam('date', [
                //         'from'=> IntegrationData::getDataValue('LAST_PHONESUBSCRIBER_INTEGRATION_DATE', $this->_user->id),
                //         'to' => date("Y-m-d", strtotime('tomorrow'))
                // ]);
                $this->request_parameters['params']['date']['from']=IntegrationData::getDataValue('LAST_PHONESUBSCRIBER_INTEGRATION_DATE', $this->_user->id);
                $this->request_parameters['params']['date']['to']=date("Y-m-d", strtotime('tomorrow'));
            }    

        $this->checkQueueConstraints();
        $request=$this->request_parameters;
        $this->request_parameters['params']['results_limit'] = self::API_RESULT_COUNT;
        
        try {
            //building request
            
            
            // Check if new flag for customer is set, if not, then get only new customers.
                    

            if ($this->_queue->page >= $this->_queue->max_page) {
                IntegrationData::setData('LAST_PHONESUBSCRIBER_INTEGRATION_DATE', date('Y-m-d'), $this->_user->id);
                IntegrationData::setData('INITIAL_PHONESUBSCRIBERS_DONE', 1, $this->_user->id);
                echo "finished";
                // die ("!!FIN!");
                return 10;
            }

            $request=$this->request_parameters;
            $request['params']['results_page']=$this->_queue->page;
            $response                = $this->_client->post($this->apiMethod, $request);
        //     var_dump($request);
        // var_dump($response);
        // die("!");

            if ( isset($response['errors']) && !empty($response['errors']['faultString'])) {
                var_dump($request);
                var_dump($response['errors']);
                // vaR_dump($response);
                // die("!!@");
                return false;
            }    

            if (!$approvalsShopId=$this->_user->config->get('customer_default_approvals_shop_id')){
                $approvalsShopId=1;
            }
            

            foreach ($response['clients'] as $customer) {
                IdoselSubscriptions::processSubscriptionItem($this->_queue->getCurrentUser(), $approvalsShopId, $customer, 'sms');
                continue;
            }
            // die("!");

            $this->_queue->increasePage();
            
//            } while ( $page <= $response->resultsNumberPage);
            // die;

            return true;

        } catch (\Exception $e) {
//            $viewData->errorMessage = 'Error while executing API Orders: ' . $e->getMessage();
            echo 'Error while executing API PHONE Subscribers: ' . $e->getMessage();
            echo PHP_EOL;
            die ("!!!");
            return false;
        }
    }

    /**
     * @param $temp
     * @param $file
     *
     * @return bool|\SimpleXMLElement|null
     *
     * @throws \Exception
     */
    protected function createOrAddTempCustomerXml($temp)
    {
        echo "CREATING XML".PHP_EOL; 

//         $string='504 98289&';
// $phone=preg_replace("/[^0-9]/", "", $string);
//         echo (int) $phone;
//         die();

        $customers = new \SimpleXMLElement('<CUSTOMERS/>');
        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage = $this->_queue->max_page;
        $page_size = self::XML_PAGE_SIZE;

        $customers_query = Customers::find()
            ->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        $page = $integrationDataCurrentPage;
        
        if( $integrationDataMaxPage == 0 ) {
            $customers_all = $customers_query->count();
            $pages = ceil($customers_all / $page_size);
            // $pages+=1; // to fit everything else
            $this->_queue->max_page=$pages;
            $integrationDataMaxPage=$pages;
            $this->_queue->page=$page;
            $this->_queue->save();
        }

        echo " PAGE ".$page." of ".$integrationDataMaxPage.PHP_EOL;

        $fields_to_integrate = [];
        if($this->_user->config->get('customer_feed_registration')) {
            $fields_to_integrate[] = 'customer_feed_registration';
        }
        
        if($this->_user->config->get('customer_feed_first_name')) {
            $fields_to_integrate[] = 'customer_feed_first_name';
        }
        
        if($this->_user->config->get('customer_feed_last_name')) {
            $fields_to_integrate[] = 'customer_feed_last_name';
        }
        
        if($this->_user->config->get('customer_zip_code')) {
            $fields_to_integrate[] = 'customer_zip_code';
        }
        
        if($this->_user->config->get('customer_phone')) {
            $fields_to_integrate[] = 'customer_phone';
        }

        if($this->_user->config->get('customer_feed_email')) {
            $fields_to_integrate[] = 'email';
        }
        
        $customers_db = $customers_query->limit($page_size)->offset(($page) * $page_size)->all();
        
        
        
        $i = 0;
        try {
            foreach ($customers_db as $customer) {
                // echo $customer['customer_id'].PHP_EOL;
                // if($customer->email == null) continue;

                $custChild = $customers->addChild('CUSTOMER');
                $custChild->addChild('CUSTOMER_ID', $customer['customer_id']);
                
                if(in_array('email', $fields_to_integrate)) {
                    $custChild->addChild('EMAIL', $customer['email']);
                }

                $registration = $customer['registration'];
                if ($registration == '0000-00-00 00:00:00' || $registration == null) {
                    $registration = '2000-01-01 00:00:00';
                }

                if(in_array('customer_feed_registration', $fields_to_integrate)) {
                    $custChild->addChild('REGISTRATION', $this->getCorrectSambaDate($registration));
                }

               if(in_array('customer_feed_first_name', $fields_to_integrate)) {
                    $custChild->addChild('FIRST_NAME', $customer['first_name']);
               }

               if(in_array('customer_feed_last_name', $fields_to_integrate)) {
                    $custChild->addChild('LAST_NAME', $customer['lastname']);
               }

               if(in_array('customer_zip_code', $fields_to_integrate)) {
                    $custChild->addChild('ZIP_CODE', $customer['zip_code']);
               }

               if(in_array('customer_phone', $fields_to_integrate)) {
                    $phone=preg_replace("/[^0-9]/", "", $customer['phone']);
                    $custChild->addChild('PHONE', $phone);
               }

                $custChild->addChild('NEWSLETTER_FREQUENCY', $customer['newsletter_frequency']);

                $custChild->addChild('SMS_FREQUENCY', $customer['sms_frequency']);

                if($customer['newsletter_frequency'] !== null && $customer['newsletter_frequency'] !== 'never') {
                    $custChild->addChild('DATA_PERMISSION', $customer['data_permission']);

                    $nlf_time = $customer['nlf_time'];
                    if($customer['nlf_time'] === null || $customer['nlf_time'] === '0000-00-00 00:00:00') {
                        $nlf_time = $registration;
                    }

                    $custChild->addChild('NLF_TIME', $this->getCorrectSambaDate($nlf_time));
                }

                $params = unserialize($customer['tags']);
                $paramsChild = $custChild->addChild('PARAMETERS');
                $lastName = $paramsChild->addChild('PARAMETER');
                $lastName->addChild('NAME', 'LAST_NAME');
                $lastName->addChild('VALUE', $customer['lastname']);

                $firstName = $paramsChild->addChild('PARAMETER');
                $firstName->addChild('NAME', 'FIRST_NAME');
                $firstName->addChild('VALUE', $customer['first_name']);

                if($params !== null && !empty($params)) {
                    foreach($params as $tag) {
                        $paramChild = $paramsChild->addChild('PARAMETER');
                        $paramChild->addChild('NAME', htmlspecialchars($tag['tagName'], ENT_QUOTES));
                        $paramChild->addChild('VALUE',  htmlspecialchars($tag['tagValue'], ENT_QUOTES));
             //           file_put_contents(__DIR__ . '/tags.txt', $tag['tagName'] . "\n", FILE_APPEND);
                    }
                    $i++;
                }

                $file_handle = fopen($temp, 'a+');            
                fwrite($file_handle, $custChild->asXml());
                fclose($file_handle);
            }
        }

        catch (\Exception $e) {
            echo "ERROR WITH DATA ".PHP_EOL;
//            $viewData->errorMessage = 'Error while executing API Orders: ' . $e->getMessage();
            echo $e->getMessage();
            die ("!!!");
            return false;
        
        }


        $page++;

        //echo $i . PHP_EOL;
        // IntegrationData::setData('customer_feed_generation_page', $page, $this->_user->id);
        // $this->_queue->max_page=$pages;
        $this->_queue->page=$page;
        $this->_queue->save();

        
        if($page > (int) $integrationDataMaxPage) {
            // echo $page.PHP_EOL;
            // echo $integrationDataMaxPage.PHP_EOL;
                // die ("JUZ !!!!!");
            echo "FINISHED ";
            // $this->createCustomerXml($file, $temp);

            return 1;
        }
        

        return true;
    }

    private function createCustomerXml(string $file, string $temp)
    {
        $customer = new \SimpleXMLElement('<CUSTOMERS/>');
        $customer->addChild('CUSTOMER');
        file_put_contents($file, str_replace('<CUSTOMER/>', file_get_contents($temp), $customer->asXML()));
        file_put_contents($temp, '');
        return is_file($file)?10:0;
    }
}
