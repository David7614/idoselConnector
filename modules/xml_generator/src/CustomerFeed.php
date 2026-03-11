<?php
namespace app\modules\xml_generator\src;

use app\models\Customers;
use app\models\IntegrationData;
use app\models\Queue;
use app\modules\idosellv3\models\ApiClient;
use phpDocumentor\Reflection\File;

class CustomerFeed extends XmlFeed
{

    private $_client;
    private $request_parameters = [];
    private $apiMethod          = '/api/admin/v5/clients/clients';
    const API_RESULT_COUNT      = 100;
    const API_PAGE_INCREMENT    = 5;
    const XML_PAGE_SIZE         = 10000; // 50000

    /**
     * @return bool
     *
     * @throws \Exception
     */
    public function generate($what = null): int
    {
        if (! $this->_user->getApiKey()) {
            throw new \Exception('No API key configured');
        }

        $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());
        $temp          = $this->getFile(true, true);
        $file          = $this->getFile(true, false);

        if ($what == 'objects') {
            echo "creating objects" . PHP_EOL;
            return $this->createCustomerObjects();

        }

        echo "creating file" . PHP_EOL;
        if (! $this->isFinished()) {
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

    private function checkQueueConstraints()
    { // todo
        $this->checkCustomersDateFrom();
        if ($this->_queue->max_page > 0) {
            return false; // no need every time
        }
        $request                 = $this->request_parameters;
        $request['resultsLimit'] = 1;
        var_dump($request);
        $response                = $this->_client->get($this->apiMethod, $request);

        if (empty($response) || !is_array($response)) {
            throw new \Exception('Gateway did not respond (checkQueueConstraints)');
        }

        if (! $response['resultsNumberAll']) {
            echo "no results" . PHP_EOL;
            return false;
        }

        $maxPage = ceil($response['resultsNumberAll'] / self::API_RESULT_COUNT);
        if ($this->_queue->max_page < $maxPage) {
            $this->_queue->max_page = $maxPage;
            $this->_queue->save();
        }

        return true;
    }

    public function checkCustomersDateFrom()
    {
        return true;
        // $dateFrom = $this->_user->getConfig()->getOrdersDateFrom();
        // if (! $dateFrom) {
        //     return true;
        // }
        // $dateFrom = date('Y-m-d', strtotime($dateFrom . ' -10 day'));
        // echo $dateFrom . PHP_EOL;
        // $check = Customers::find()->where(['user_id' => $this->_user->id])->andWhere(['<', 'registration', $dateFrom])->limit(1)->one();
        // if ($check) {
        //     var_dump($check);
        //     Customers::deleteAll(['and',
        //         ['user_id' => $this->_user->id],
        //         ['<', 'registration', $dateFrom],
        //     ]);
        //     // Orders::deleteAll(['user_id'=>$this->_user->id]);
        //     // IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $this->_user->id);
        //     IntegrationData::setData('last_customer_integration_date', $this->_user->getConfig()->getOrdersDateFrom(), $this->_user->id);
        //     $this->_queue->page       = 0;
        //     $this->_queue->max_page   = 0;
        //     $this->_queue->integrated = 0;
        //     $this->_queue->save();
        //     echo "DELETED customers, RECOMMENCING" . PHP_EOL;
        //     throw new \Exception('Customers date range reset — recommencing from ' . $dateFrom);
        // }

        // die("!!");
        // getOrdersDateFrom
    }

    private function createCustomerObjects()
    {

        if ($this->_user->getIncrementalFeedFlag()) { // incremental
            if ($this->_queue->page == 0) {
                Customers::deleteAll(['user_id' => $this->_user->id]); // delete all obsolete entries
            }
            $date2weeksago = date('Y-m-d', strtotime('-2 weeks'));
            // echo $date2weeksago;
            // die();
            IntegrationData::setLastCustomerIntegrationDate($date2weeksago, $this->_user->id);
            // die ("!i");
        }

        if (IntegrationData::getData('last_customer_integration_date', $this->_user->id)) {
            $this->request_parameters['clientsLastModificationDate']['clientsLastModificationDateBegin'] = IntegrationData::getLastCustomerIntegrationDate($this->_user->id);
            $this->request_parameters['clientsLastModificationDate']['clientsLastModificationDateEnd']   = date('Y-m-d');

        }
        // $dateFrom = $this->_user->getConfig()->getOrdersDateFrom();
        // if ($dateFrom) {
        //     $this->request_parameters['clientRegistrationDate']['clientRegistrationDateBegin'] = $dateFrom;
        //     $this->request_parameters['clientRegistrationDate']['clientRegistrationDateEnd']   = date('Y-m-d');
        // }
        // var_dump($this->request_parameters);
        // var_dump(IntegrationData::getLastCustomerIntegrationDate($this->_user->id));
        // die();

        $this->checkQueueConstraints();
        $request                                  = $this->request_parameters;
        $this->request_parameters['resultsLimit'] = self::API_RESULT_COUNT;

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
            $request                = $this->request_parameters;
            $request['resultsPage'] = $this->_queue->page;
            var_dump($request);
            // die("!!!");
            $response               = $this->_client->get($this->apiMethod, $request);

            if (isset($response['errors']) && ! empty($response['errors']['faultString'])) {
                throw new \Exception('API fault: ' . $response['errors']['faultString']);
            }

            if (! $approvalsShopId = $this->_user->config->get('customer_default_approvals_shop_id')) {
                $approvalsShopId     = 1;
                $approvalsShopActive = false;
            } else {
                $approvalsShopActive = true;
            }

            foreach ($response['results'] as $customer) {
                // if ($customer['isUnregistered'] == 'y') {
                //     echo "customer not registered";
                //     continue;
                // }
                $customer_item = $this->prepareCustomerData($customer, $approvalsShopId);

                if ($customer_item == null) {
                    continue;
                }
                // die ("!!!!");

                if ($customerObject = Customers::getCustomer($customer_item, $this->_queue->getCurrentUser()->id)) {
                    echo "CUSTOMER UPDATE " . PHP_EOL;
                    $customerObject = $customerObject->updateCustomer($customer_item);
                } else {
                    echo "CUSTOMER INSERT " . PHP_EOL;
                    $customerObject = Customers::addCustomer($customer_item, $this->_queue->getCurrentUser()->id, $this->_queue->page);
                }
                if ($approvalsShopActive && ! $customerObject->crm_verified) {
                    $crmData = $this->_client->get('/api/admin/v4/clients/crm', ['clientLogin' => $customerObject->login]);
                    if (isset($crmData['errors'])) {
                        echo "CRM ERROR " . $crmData['errors']['faultString'] . PHP_EOL;
                        continue;

                    }
                    foreach ($crmData['clientsResults'][0]['clientActiveInShops'] ?? [] as $shop) {
                        if ($shop['shopId'] == $approvalsShopId) {
                            $customerObject->shop_id = $shop['shopId'];
                        }

                    }
                    $customerObject->crm_verified = true;
                    $customerObject->save();
                    echo "CRM VERIFIED " . $customerObject->login . PHP_EOL;

                }

            }

            $this->_queue->increasePage();

            return 1;

        } catch (\Exception $e) {
            echo 'Error while executing API: ' . $e->getMessage() . PHP_EOL;
            throw $e;
        }
    }

    private function getLanguage($customer)
    {
        if ($this->_user->config->get('customer_language') !== '1') {
            return null;
        }

        if (!$customer['clientPreferences'] || !$customer['clientPreferences']['langId']) {
            return null;
        }

        return $customer['clientPreferences']['langId'];
    }

    private function getParameterCountry($customer)
    {
        if ($this->_user->config->get('customer_country') !== '1') {
            return null;
        }

        if (!$customer['clientBillingAddress'] || !$customer['clientBillingAddress']['clientCountryId']) {
            return null;
        }

        return htmlspecialchars(strtolower($customer['clientBillingAddress']['clientCountryId']));
    }

    public function getParameters($customer)
    {
        $parameters = [];

        try {
            $language = $this->getLanguage($customer);

            if ($language) {
                $parameters[] = [
                    'NAME' => 'Język klienta',
                    'VALUE' => $language,
                ];
            }

            $country = $this->getParameterCountry($customer);

            if ($country) {
                $parameters[] = [
                    'NAME' => 'Kraj pochodzenia klienta',
                    'VALUE' => $country,
                ];
            }

            return $parameters;
        } catch (\Exception $e) {
            echo "PARAMETERS ERROR" . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            return $parameters;
        }
    }

    private function prepareCustomerData($customer, $approvalsShopId)
    {
        var_dump($customer);
        echo "processing " . $customer['clientId'] . PHP_EOL;
        var_dump($customer['clientLogin']);
        var_dump($customer['clientEmail']);

        $customer_item = [];

        $customer_item['is_wholesaler'] = 0;
        if ($customer['clientPreferences']['clientIsWholesaler'] == 'yes') {
            $customer_item['is_wholesaler'] = 1;
        }

        $customer_item['customer_id'] = htmlspecialchars($customer['clientId']);
        $customer_item['email']       = htmlspecialchars($customer['clientEmail']);
        $customer_item['login']       = htmlspecialchars($customer['clientLogin']);
        // die("!!");
        if (isset($customer['clientRegistrationDate'])) {
            $customer_item['registration']           = htmlspecialchars($customer['clientRegistrationDate']);
        }
        else {
            $customer_item['registration']           = '1991-01-01 00:00';
        }
        if (isset($customer['clientsLastModificationDate'])) {
            $customer_item['last_modification_date'] = $customer['clientsLastModificationDate'];
        } else {
            $customer_item['last_modification_date'] = '1991-01-01 00:00';
        }

        $customer_item['first_name']           = htmlspecialchars($customer['clientBillingAddress']['clientFirstName']);
        $customer_item['lastname']             = htmlspecialchars($customer['clientBillingAddress']['clientLastName']);
        $customer_item['zip_code']             = htmlspecialchars($customer['clientBillingAddress']['clientZipCode']);
        $customer_item['phone']                = str_replace(' ', '', $customer['clientBillingAddress']['clientPhone1']);
        $customer_item['newsletter_frequency'] = 'never';
        $customer_item['sms_frequency']        = 'never';
        $customer_item['nlf_time']             = '0000-00-00 00:00:00';
        $customer_item['data_permission']      = 'full'; // zawsze full bo to zgoda na dostęp do danych - ustalone z Adamem

        $customer_item['tags'] = serialize([]);

        $email_approval = $customer['newsletterEmailApprovalsData'][0]['newsletterEmailApprovalDate'] ?? '0000-00-00 00:00:00';
        $sms_approval   = $customer['newsletterSmsApprovalsData'][0]['newsletterSmsApprovalDate'] ?? '0000-00-00 00:00:00';

        foreach ($customer['newsletterEmailApprovalsData'] as $itm) {
            if ($itm['shopId'] == $approvalsShopId) {
                $email_approval = $itm['newsletterEmailApprovalDate'];
            }
        }
        foreach ($customer['newsletterSmsApprovalsData'] as $itm) {
            if ($itm['shopId'] == $approvalsShopId) {
                $sms_approval = $itm['newsletterSmsApprovalDate'];
            }
        }

        // skip if there is no consent to process the newsletter or SMS
        if ($email_approval == '0000-00-00 00:00:00' && $sms_approval == '0000-00-00 00:00:00') {
            echo " ----- no approval ";
            return null;
        }

        if ($email_approval !== '0000-00-00 00:00:00') {
            $customer_item['newsletter_frequency'] = 'every day';
            $customer_item['nlf_time']             = $email_approval;
            // $customer_item['data_permission']      = 'full';
        }

        if ($sms_approval !== '0000-00-00 00:00:00') {
            $customer_item['sms_frequency'] = 'every day';
            if ($customer_item['newsletter_frequency'] == 'never') {
                 $customer_item['nlf_time'] = $sms_approval;
            }
        }
        // var_dump($customer_item);

        $customer_item['parameters'] = serialize($this->getParameters($customer));

        return $customer_item;
    }

    protected function getXmlPhone($customerPhone)
    {
        if ($this->_user->id == 190) {
	    $raw = $customerPhone;
	    $digits = preg_replace('/[^0-9]/', '', $raw);
	    $trimmed = ltrim($raw);
	    $hasLeadingPlus = $trimmed !== '' && $trimmed[0] === '+';
	    return $hasLeadingPlus ? '+' . $digits : $digits;
        }

        return preg_replace("/[^0-9]/", "", $customerPhone);
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
        echo "CREATING XML" . PHP_EOL;

//         $string='504 98289&';
        // $phone=preg_replace("/[^0-9]/", "", $string);
        //         echo (int) $phone;
        //         die();

        $customers                  = new \SimpleXMLElement('<CUSTOMERS/>');
        $integrationDataCurrentPage = $this->_queue->page;
        $integrationDataMaxPage     = $this->_queue->max_page;
        $page_size                  = self::XML_PAGE_SIZE;

        $customers_query = Customers::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);

        if (! $approvalsShopId = $this->_user->config->get('customer_default_approvals_shop_id')) {
            $customers_query = Customers::find()->where(['user_id' => $this->_queue->getCurrentUser()->id]);
        } else {
            $customers_query = Customers::find()->where(['user_id' => $this->_queue->getCurrentUser()->id, 'shop_id' => $approvalsShopId]);
        }

        $page = $integrationDataCurrentPage;

        if ($integrationDataMaxPage == 0) {
            $customers_all = $customers_query->count();
            $pages         = ceil($customers_all / $page_size);
            // $pages+=1; // to fit everything else
            $this->_queue->max_page = $pages;
            $integrationDataMaxPage = $pages;
            $this->_queue->page     = $page;
            $this->_queue->save();
        }

        echo " PAGE " . $page . " of " . $integrationDataMaxPage . PHP_EOL;

        $fields_to_integrate = [];
        if ($this->_user->config->get('customer_feed_registration')) {
            $fields_to_integrate[] = 'customer_feed_registration';
        }

        if ($this->_user->config->get('customer_feed_first_name')) {
            $fields_to_integrate[] = 'customer_feed_first_name';
        }

        if ($this->_user->config->get('customer_feed_last_name')) {
            $fields_to_integrate[] = 'customer_feed_last_name';
        }

        if ($this->_user->config->get('customer_zip_code')) {
            $fields_to_integrate[] = 'customer_zip_code';
        }

        if ($this->_user->config->get('customer_phone')) {
            $fields_to_integrate[] = 'customer_phone';
        }

        if ($this->_user->config->get('customer_feed_email')) {
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

                if ($customer->isCustomerValidForXml() == false) {
                    echo $customer['customer_id']." pass customer - not valid".PHP_EOL;
                    continue;
                }


                $custChild = $customers->addChild('CUSTOMER');
                $custChild->addChild('CUSTOMER_ID', $customer['customer_id']);

                if (in_array('email', $fields_to_integrate)) {
                    $custChild->addChild('EMAIL', $customer['email']);
                }

                $registration = $customer['registration'];
                if ($registration == '0000-00-00 00:00:00' || $registration == null) {
                    $registration = '2000-01-01 00:00:00';
                }

                if (in_array('customer_feed_registration', $fields_to_integrate)) {
                    $custChild->addChild('REGISTRATION', $this->getCorrectSambaDate($registration));
                }

                if (in_array('customer_feed_first_name', $fields_to_integrate) && !empty($customer['first_name'])) {
                    $custChild->addChild('FIRST_NAME', $customer['first_name']);
                }

                if (in_array('customer_feed_last_name', $fields_to_integrate) && !empty($customer['lastname'])) {
                    $custChild->addChild('LAST_NAME', $customer['lastname']);
                }

                if (in_array('customer_zip_code', $fields_to_integrate) && !empty($customer['zip_code'])) {
                    $custChild->addChild('ZIP_CODE', $customer['zip_code']);
                }

                if (in_array('customer_phone', $fields_to_integrate) && !empty($customer['phone'])) {
                    $phone = $this->getXmlPhone($customer['phone']);
                    // $phone = preg_replace("/[^0-9]/", "", $customer['phone']);
                    $custChild->addChild('PHONE', $phone);
                }

                // if (in_array('customer_feed_first_name', $fields_to_integrate)) {
                //     $custChild->addChild('FIRST_NAME', $customer['first_name']);
                // }

                // if (in_array('customer_feed_last_name', $fields_to_integrate)) {
                //     $custChild->addChild('LAST_NAME', $customer['lastname']);
                // }

                // if (in_array('customer_zip_code', $fields_to_integrate)) {
                //     $custChild->addChild('ZIP_CODE', $customer['zip_code']);
                // }

                // if (in_array('customer_phone', $fields_to_integrate)) {
                //     $phone = preg_replace("/[^0-9]/", "", $customer['phone']);
                //     $custChild->addChild('PHONE', $phone);
                // }

                if ($customer['is_wholesaler']) {
                    $custChild->addChild('PRICE_CATEGORY', 'Wholesaler');
                }

                $custChild->addChild('NEWSLETTER_FREQUENCY', $customer['newsletter_frequency']);

                $custChild->addChild('SMS_FREQUENCY', $customer['sms_frequency']);

                $custChild->addChild('DATA_PERMISSION', $customer['data_permission']);
                if ($customer['newsletter_frequency'] !== null && $customer['newsletter_frequency'] !== 'never') {
                    $nlf_time = $customer['nlf_time'];
                    if ($customer['nlf_time'] === null || $customer['nlf_time'] === '0000-00-00 00:00:00') {
                        $nlf_time = $registration;
                    }

                    $custChild->addChild('NLF_TIME', $this->getCorrectSambaDate($nlf_time));
                }

                // $custChild->addChild('PRICE_CATEGORY', $customer['is_wholesaler']?true:false);

                // $params      = unserialize($customer['tags']);
                // $paramsChild = $custChild->addChild('PARAMETERS');
                // $lastName    = $paramsChild->addChild('PARAMETER');
                // $lastName->addChild('NAME', 'LAST_NAME');
                // $lastName->addChild('VALUE', $customer['lastname']);

                // $firstName = $paramsChild->addChild('PARAMETER');
                // $firstName->addChild('NAME', 'FIRST_NAME');
                // $firstName->addChild('VALUE', $customer['first_name']);

                // $countryParam = $paramsChild->addChild('PARAMETER');
                // $countryParam->addChild('NAME', 'COUNTRY');
                // $countryParam->addChild('VALUE', $customer['country']);

                // if ($params !== null && ! empty($params)) {
                //     foreach ($params as $tag) {
                //         $paramChild = $paramsChild->addChild('PARAMETER');
                //         $paramChild->addChild('NAME', htmlspecialchars($tag['tagName'], ENT_QUOTES));
                //         $paramChild->addChild('VALUE', htmlspecialchars($tag['tagValue'], ENT_QUOTES));
                //         //           file_put_contents(__DIR__ . '/tags.txt', $tag['tagName'] . "\n", FILE_APPEND);
                //     }
                //     $i++;
                // }
                // if ($customer['parameters']) {
                //     $extraParameters = json_decode($customer['parameters']);
                //     foreach ($extraParameters as $name => $value) {
                //         $paramChild = $paramsChild->addChild('PARAMETER');
                //         $paramChild->addChild('NAME', htmlspecialchars($name, ENT_QUOTES));
                //         $paramChild->addChild('VALUE', htmlspecialchars($value, ENT_QUOTES));
                //     }
                // }

                $this->setFeedParams($custChild, $customer);

                $file_handle = fopen($temp, 'a+');
                fwrite($file_handle, $custChild->asXml());
                fclose($file_handle);
            }
        } catch (\Exception $e) {
            echo "ERROR WITH DATA " . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            throw $e;
        }

        $page++;

        //echo $i . PHP_EOL;
        // IntegrationData::setData('customer_feed_generation_page', $page, $this->_user->id);
        // $this->_queue->max_page=$pages;
        $this->_queue->page = $page;
        $this->_queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            echo "FINISHED ";
            return 1;
        }

        return 1;
    }

    private function getFeedParams($customer)
    {
        $params = [];

        if (!empty($customer['first_name'])) {
            $params[] = ['name' => 'FIRST_NAME', 'value' => $customer['first_name']];
        }

        if (!empty($customer['lastname'])) {
            $params[] = ['name' => 'LAST_NAME', 'value' => $customer['lastname']];
        }

        if (!empty($customer['country'])) {
            $params[] = ['name' => 'COUNTRY', 'value' => $customer['country']];
        }

        $tags = unserialize($customer['tags']);

        if ($tags !== null && !empty($tags)) {
            foreach ($tags as $tag) {
                $params[] = [
                    'name' => htmlspecialchars($tag['tagName'], ENT_QUOTES),
                    'value' => htmlspecialchars($tag['tagValue'], ENT_QUOTES)
                ];
            }
        }

        if ($customer['parameters'] && !empty($customer['parameters'])) {
            $parameters = unserialize($customer['parameters']);

            if ($parameters && !empty($parameters)) {
                foreach ($parameters as $parameter) {
                    $params[] = [
                        'name' => $parameter['NAME'],
                        'value' => $parameter['VALUE'],
                    ];
                }
            }
        }

        return $params;
    }

    private function setFeedParams($xml, $customer)
    {
        $params = $this->getFeedParams($customer);

        if (empty($params)) {
            return;
        }

        $paramsChild = $xml->addChild('PARAMETERS');

        foreach ($params as $param) {
            $paramChild = $paramsChild->addChild('PARAMETER');
            $paramChild->addChild('NAME', $param['name']);
            $paramChild->addChild('VALUE', $param['value']);
        }
    }

    private function createCustomerXml(string $file, string $temp)
    {
        $customer = new \SimpleXMLElement('<CUSTOMERS/>');
        $customer->addChild('CUSTOMER');
        file_put_contents($file, str_replace('<CUSTOMER/>', file_get_contents($temp), $customer->asXML()));
        file_put_contents($temp, '');
        return is_file($file) ? 10 : 0;
    }
}
