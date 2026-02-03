<?php
namespace app\models;

use Yii;
use yii\httpclient\Client;
// productPromoRetailPrice
class IdiosellApp
{
    const APPLICATION_KEY = 'ZGMxntfgvupNNOhVOBCUA6tcCysB30mJ';
    // const APPLICATION_KEY = 'IwdTU8jlFkGLg8e0/Oii0i/vXL76sjIw';
    const DEVELOPER_ID = 'ppasek@samba.ai';

    public static function decryptApiKey($key){
        $applicationKey=IdiosellApp::APPLICATION_KEY;
        $iv = trim(@file_get_contents('https://apps.idosell.com/keyset'));
        if ($iv) {
            $key = openssl_decrypt(
                base64_decode($key),
                'AES-256-CBC',
                $applicationKey,
                0,
                $iv
            );
        }
        return $key;
    }

    public static function getSign(){
        $login=IdiosellApp::DEVELOPER_ID;
        $applicationKey=IdiosellApp::APPLICATION_KEY;
        $sign = hash('sha256', $login  . '|' . date('Y-m-d') .  '|' . $applicationKey);
        return $sign;
    }
    public static function getUser($data){
        $username=self::trimUrl($data['api_url']);
        $user=User::findOne(['username' => $username]);
        if (!$user){
            $model=new User();
            $password=$username.'123';
            $user=$model->register($username, $username, $password, 'idiosell');

        }
        return $user;
        //$user->setUserDataValue('api3_key', $apiKey);
    }

    public static function trimUrl($url){
        $url=str_replace('/api/admin/', '', $url);
        $url=str_replace('https://', '', $url);
        $url=str_replace('http://', '', $url);
        return $url;
    }

    public function confirmInstall($user){
        $client = new Client([
            'baseUrl' => 'https://apps.idosell.com/api/',
        ]);
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setUrl('application/installation/done')
            ->addHeaders(['content-type' => 'application/json'])
            ->setContent(json_encode([
                'api_license' => $user->getUserDataValue('api_license'),
                'application_id' => $user->getUserDataValue('application_id'),
                'developer' => IdiosellApp::DEVELOPER_ID,
                'sign' => self::getSign(),
            ]))
            ->send();

        return $response;


    }

}
