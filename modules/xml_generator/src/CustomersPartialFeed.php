<?php

namespace app\modules\xml_generator\src;

use app\models\Customers;
use app\models\IntegrationData;
use app\models\Queue;
use phpDocumentor\Reflection\File;
use SoapClient;

class CustomersPartialFeed extends XmlFeed
{

    private $_client;
    private $_request;
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

        $gate = "https://{$this->_user->username}/api/?gate=clients/get/196/soap/wsdl&lang=pol";
        $this->_client=new IdioselClient($gate, $this->_token);
        $this->_request=new SoapRequest();

        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        $this->_request->addParam('clientsLastModificationDate', [
            'clientsLastModificationDateBegin'=> date("Y-m-d", strtotime(date('Y-m-d', '-2 weeks'))),
            'clientsLastModificationDateEnd' => date("Y-m-d", strtotime('tomorrow'))
        ]);

        die ("stop");

        if($what == 'objects') {
            echo "creating objects".PHP_EOL;
            return $this->createCustomerObjects();

        }

        echo "creating file".PHP_EOL;
        if (!$this->isFinished()) {
            $created = $this->createOrAddTempCustomerXml($temp);
        } else {
            $created = $this->createCustomerXml($file, $temp);
        }
        return $created;
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
        $request=$this->_request;
        $request->addParam('resultsLimit', 1);
        $response = $this->_client->get($request->getRequest());
        // var_dump($response);
        if (!$response->resultsNumberAll){
            echo "no results".PHP_EOL;
            return false;
        }

        $maxPage=ceil($response->resultsNumberAll/self::API_RESULT_COUNT);
        if ($this->_queue->max_page<$maxPage){
            $this->_queue->max_page=$maxPage;
            $this->_queue->save();
        }

        return true;
    }


    private function createCustomerObjects()
    {

        if (IntegrationData::getData('INITIAL_CUSTOMERS_DONE', $this->_user->id)) {
                $this->_request->addParam('clientsLastModificationDate', [
                        'clientsLastModificationDateBegin'=> IntegrationData::getLastCustomerIntegrationDate($this->_user->id),
                        'clientsLastModificationDateEnd' => date("Y-m-d", strtotime('tomorrow'))
                ]);
            }

        $this->checkQueueConstraints();
        $request=$this->_request;
        $this->_request->addParam('resultsLimit', self::API_RESULT_COUNT);

        try {
            //building request


            // Check if new flag for customer is set, if not, then get only new customers.


            if ($this->_queue->page >= $this->_queue->max_page) {
                IntegrationData::setLastCustomerIntegrationDate(date('Y-m-d'), $this->_user->id);
                IntegrationData::setIsNew('CUSTOMER', 0, $this->_user->id);
                IntegrationData::setData('INITIAL_CUSTOMERS_DONE', 1, $this->_user->id);
                echo "finished";
                // die ("!!FIN!");
                return 10;
            }

            $request=$this->_request;
            $request->addParam('resultsPage', $this->_queue->page);
            $response = $this->_client->get($request->getRequest());

            if ($response->errors && !empty($response->errors->faultString)) {
                var_dump($request->getRequest());
                var_dump($response->errors);
                // vaR_dump($response);
                // die("!!@");
                return false;
            }

            if (!$approvalsShopId=$this->_user->config->get('customer_default_approvals_shop_id')){
                $approvalsShopId=1;
            }


            foreach ($response->results as $customer) {
                echo "processing ".$customer->clientId.PHP_EOL;
                var_dump($customer->isUnregistered);
                var_dump($customer->clientLogin);
                if ($customer->isUnregistered=='y'){
                    echo "customer not registered";
                    continue;
                }
                $customer_item = [];

                $customer_item['is_wholesaler'] = 0;
                if ($customer->clientPreferences->clientIsWholesaler == 'yes'){
                    $customer_item['is_wholesaler'] = 1;
                }

                $customer_item['customer_id'] = htmlspecialchars($customer->clientId);
                $customer_item['email'] = htmlspecialchars($customer->clientEmail);
                if(property_exists($customer, 'clientsLastModificationDate')) {
                    $customer_item['registration'] = htmlspecialchars($customer->clientsLastModificationDate);
                    $customer_item['last_modification_date'] = $customer->clientsLastModificationDate;
                } else {
                    $customer_item['registration'] = '1991-01-01 00:00';
                    $customer_item['last_modification_date'] = '1991-01-01 00:00';
                }

                $customer_item['first_name'] = htmlspecialchars($customer->clientBillingAddress->clientFirstName);
                $customer_item['lastname'] = htmlspecialchars($customer->clientBillingAddress->clientLastName);
                $customer_item['zip_code'] = htmlspecialchars($customer->clientBillingAddress->clientZipCode);
                $customer_item['phone'] = str_replace(' ', '', $customer->clientBillingAddress->clientPhone1);
                $customer_item['newsletter_frequency'] = 'never';
                $customer_item['sms_frequency'] = 'never';
                $customer_item['nlf_time'] = '0000-00-00 00:00:00';
                $customer_item['data_permission'] = 'do_not_personalize';


                $customer_item['tags'] = serialize([]);



                $email_approval = $customer->newsletterEmailApprovalsData[0]->newsletterEmailApprovalDate;
                $sms_approval = $customer->newsletterSmsApprovalsData[0]->newsletterSmsApprovalDate;

                foreach ($customer->newsletterEmailApprovalsData as $itm){
                    if ($itm->shopId==$approvalsShopId){
                        $email_approval = $itm->newsletterEmailApprovalDate;
                    }
                }
                foreach ($customer->newsletterSmsApprovalsData as $itm){
                    if ($itm->shopId==$approvalsShopId){
                        $sms_approval = $itm->newsletterSmsApprovalDate;
                    }
                }


                if($email_approval !== '0000-00-00 00:00:00') {
                    $customer_item['newsletter_frequency'] = 'every day';
                    $customer_item['nlf_time'] = $email_approval;
                    $customer_item['data_permission'] = 'full';
                }

                if($sms_approval !== '0000-00-00 00:00:00') {
                    $customer_item['sms_frequency'] = 'every day';
                }

                $customer_obj = Customers::addCustomer($customer_item, $this->_queue->getCurrentUser()->id, $this->_queue->page);
            }

            $this->_queue->increasePage();

//            } while ( $page <= $response->resultsNumberPage);
            // die;

            return true;

        } catch (\Exception $e) {
//            $viewData->errorMessage = 'Error while executing API Orders: ' . $e->getMessage();
            echo 'Error while executing API Orders: ' . $e->getMessage().PHP_EOL;
            var_dump($request->getRequest());
            echo PHP_EOL;
            $this->_queue->increasePage();
            // die ("FULL STOP!!!");
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

                if (Queue::isDisallowedEmail($customer['email'])) { // ommit allegro etc
                    continue;
                }

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
                if ($customer['is_wholesaler']){
                    $custChild->addChild('PRICE_CATEGORY', 'Wholesaler');
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

                // $custChild->addChild('PRICE_CATEGORY', $customer['is_wholesaler']?true:false);

                $params = unserialize($customer['tags']);
                $paramsChild = $custChild->addChild('PARAMETERS');
                $lastName = $paramsChild->addChild('PARAMETER');
                $lastName->addChild('NAME', 'LAST_NAME');
                $lastName->addChild('VALUE', $customer['lastname']);

                $firstName = $paramsChild->addChild('PARAMETER');
                $firstName->addChild('NAME', 'FIRST_NAME');
                $firstName->addChild('VALUE', $customer['first_name']);

                $countryParam = $paramsChild->addChild('PARAMETER');
                $countryParam->addChild('NAME', 'COUNTRY');
                $countryParam->addChild('VALUE', $customer['country']);

                if($params !== null && !empty($params)) {
                    foreach($params as $tag) {
                        $paramChild = $paramsChild->addChild('PARAMETER');
                        $paramChild->addChild('NAME', htmlspecialchars($tag['tagName'], ENT_QUOTES));
                        $paramChild->addChild('VALUE',  htmlspecialchars($tag['tagValue'], ENT_QUOTES));
             //           file_put_contents(__DIR__ . '/tags.txt', $tag['tagName'] . "\n", FILE_APPEND);
                    }
                    $i++;
                }
                if ($customer['parameters']){
                    $extraParameters=json_decode($customer['parameters']);
                    foreach ($extraParameters as $name=>$value){
                        $paramChild = $paramsChild->addChild('PARAMETER');
                        $paramChild->addChild('NAME', htmlspecialchars($name, ENT_QUOTES));
                        $paramChild->addChild('VALUE',  htmlspecialchars($value, ENT_QUOTES));
                    }
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

