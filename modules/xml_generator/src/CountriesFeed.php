<?php
namespace app\modules\xml_generator\src;

use app\models\Customers;
use app\modules\idosellv3\models\ApiClient;

class CountriesFeed extends XmlFeed
{
    const BATCH_SIZE = 100;

    private $_client;
    private $request_parameters = [];
    private $apiMethod          = '/api/admin/v7/clients/deliveryAddress';

    public function generate($what = null): int
    {

        if (! $this->_user->getApiKey()) {
            throw new \Exception('No API key configured');
        }
        echo "[countries] user={$this->_user->username} queue={$this->_queue->id} page={$this->_queue->page}" . PHP_EOL;
        $this->_client = new ApiClient($this->_user->username, $this->_user->getApiKey());

        $customersList = Customers::find()
            ->where(['user_id' => $this->_user->id, 'country' => ''])
            ->andWhere(['!=', 'email', ''])
            ->limit(self::BATCH_SIZE)
            ->all();

        if (! $customersList) {
            echo "[countries] #{$this->_queue->id} no customers without country — done" . PHP_EOL;
            return 10;
        }

        echo "[countries] customers to process: " . count($customersList) . PHP_EOL;

        
        $updated = 0;
        $noData  = 0;
        $clientLogins=[];
        $clientCountriesByLogin=[];
        foreach ($customersList as $customer) {
            $clientLogins[]=$customer->email;        
        }
        if (empty($clientLogins)){
            echo "no customers emails without country — done" . PHP_EOL;
            return 10; // nie ma wyników
        }
        $request = [];
        $request ['clientLogins']= implode(',',$clientLogins);
        
        $response = $this->_client->get($this->apiMethod, $request);



        foreach ($response['results'] as $responseResult){
            $lastIndex = count($responseResult['clientDeliveryAddresses']) - 1;
            if ($lastIndex < 0) {
                $clientCountriesByLogin[$responseResult['clientLogin']]='no data';
                $noData++;
                continue;
            }
            $country = $responseResult['clientDeliveryAddresses'][$lastIndex]['clientDeliveryAddressCountry'];
            $clientCountriesByLogin[$responseResult['clientLogin']]=$country;

            
        }

        foreach ($customersList as $customer) {
            // echo "[{$this->_queue->page}] ID={$customer->id} email={$customer->email}" . PHP_EOL;
            $country = $clientCountriesByLogin[$customer->email] ?? 'no data';
            $customer->country = $country;
            $customer->save();

            if ($errors = $customer->getErrors()) {
                echo "  -> save errors: " . json_encode($errors) . PHP_EOL;
            } else {
                // echo "  -> country={$country}" . PHP_EOL;
                $updated++;
            }
        }

        echo "[countries] #{$this->_queue->id} batch done — updated={$updated} no_data={$noData}" . PHP_EOL;
        if (count($customersList) < self::BATCH_SIZE) {
            echo "[countries] #{$this->_queue->id} less than batch size — done" . PHP_EOL;
            return 10;
        }

        return 1;
    }
}
