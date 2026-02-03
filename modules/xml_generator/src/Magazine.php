<?php

namespace app\modules\xml_generator\src;

use app\models\Magazines;
use app\models\User;
use app\modules\api\src\Connection;
use SoapClient;
use yii\console\ExitCode;
use yii\db\Exception;

class Magazine
{
    private $_user;
    private $_token;

    public function __construct() {
        $this->_user = User::find()->where(['id'=>19])->one();
        $connection = new Connection($this->_user);

        if($connection->getToken() == null) {
            throw new Exception("cannot get user token");
        }

        try {
            $this->_token = $connection->getToken()->getToken();
        } catch (\InvalidArgumentException $e) {
            throw new Exception("cannot get user token");
        }
    }

    public function getMagazines()
    {
        //creating SOAP client with Authorization header
        $gate = "https://{$this->_user->username}/api/?gate=locations/get/135/soap/wsdl&lang=pol";
        $apiClient = new SoapClient(
            $gate,
            [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => 'Authorization: Bearer ' . $this->_token
                    ],
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE
            ]
        );

        try {
            //building request
            $request = [
                'authenticate' => [
                    //leaving empty - authenticating using OAuth access token
                    'userLogin' => '',
                    'authenticateKey' => ''
                ],
                'get' => [
                    'params' => [
                        'returnProducts' => 'active',
                    ]
                ]
            ];

            $page = 0;
            do {

                $request['params']['resultsPage'] = $page;
                $response = $apiClient->get($request);
                // print_r($response->Results); die;

                foreach ($response->results as $magazine) {
                    $magazines = new Magazines();
                    $magazines->location_code = $magazine->locationId;
                    $magazines->location_id = $magazine->parentId;
                    $magazines->location_name = $magazine->locationName;
                    $magazines->location_path = $magazine->locationPath;
                    $magazines->parent_id = $magazine->locationCode;
                    $magazines->stock_id = $magazine->stockId;
                    $magazines->save();
                }
                $page++;
            } while($page <= $response->resultsNumberPage);

            return true;
        } catch (\Exception $e) {
            echo $e;
            return false;
        }
    }
}
