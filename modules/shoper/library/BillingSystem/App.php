<?php

namespace app\modules\shoper\library\BillingSystem;

use yii;
use DreamCommerce\ShopAppstoreLib\Client;
use DreamCommerce\ShopAppstoreLib\Client\Exception\Exception as ClientException;
use DreamCommerce\ShopAppstoreLib\Exception\HandlerException;
use DreamCommerce\ShopAppstoreLib\Handler;
use DreamCommerce\ShopAppstoreLib\Client\OAuth;
use app\modules\shoper\models\ShoperShops;
use app\modules\shoper\models\ShoperAccessTokens;
use app\modules\shoper\models\ShoperBillings;
use app\modules\shoper\models\ShoperSubscriptions;

class App
{

    /**
     * @var null|Handler
     */
    protected $handler = null;

    /**
     * @var array configuration placeholder
     */
    protected $config = array();


    /**
     * @param string $entrypoint
     * @throws \Exception
     */
    public function __construct($entrypoint, $config)
    {
        $this->config = $config;

        try {
            // instantiate a handler
            $handler = $this->handler = new Handler(
                $entrypoint, $config['appId'], $config['appSecret'], $config['appstoreSecret']
            );

            // subscribe to particular events
            $handler->subscribe('install', array($this, 'installHandler'));
            $handler->subscribe('upgrade', array($this, 'upgradeHandler'));
            $handler->subscribe('billing_install', array($this, 'billingInstallHandler'));
            $handler->subscribe('billing_subscription', array($this, 'billingSubscriptionHandler'));
            $handler->subscribe('uninstall', array($this, 'uninstallHandler'));
        } catch (HandlerException $ex) {
            throw new \Exception('Handler initialization failed', 0, $ex);
        }
    }

    /**
     * dispatches controller
     * @param array|null $data
     * @throws \Exception
     */
    public function dispatch($data = null)
    {
        try {
            $this->handler->dispatch($data);
        } catch (HandlerException $ex) {
            if ($ex->getCode() == HandlerException::HASH_FAILED) {
                throw new \Exception('Payload hash verification failed', 0, $ex);
            }
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * install action
     * arguments:
     * - action
     * - shop
     * - shop_url
     * - application_code
     * - application_version
     * - auth_code
     * - hash
     * - timestamp
     *
     * @param array $arguments
     * @throws \Exception
     */
    public function installHandler($arguments)
    {
        echo "install";
        try {
            $tr = Yii::$app->db->beginTransaction();

            $update = true;
            
            $shopModel=ShoperShops::findOne(['shop'=>$arguments['shop']]);
            if (!$shopModel){
                $shopModel=new ShoperShops();
                $shopModel->shop=$arguments['shop'];
                $update = false;
            }
            $shopModel->shop_url=$arguments['shop_url'];
            $shopModel->version=$arguments['application_version'];
            $shopModel->installed=1;
            $shopModel->save();
            $shopId=$shopModel->id;
            

            // get OAuth tokens
            try {
                /** @var OAuth $c */
                $c = $arguments['client'];
                $c->setAuthCode($arguments['auth_code']);
                $tokens = $c->authenticate();
            } catch (ClientException $ex) {
                echo "CE";
                throw new \Exception('Client error', 0, $ex);
            }

            // store tokens in db
            $expirationDate = date('Y-m-d H:i:s', time() + $tokens['expires_in']);
            if ($update) {
                $accesTokenModel=ShoperAccessTokens::findOne(['shop_id'=>$shopId]);
                $accesTokenModel->expires_at=$expirationDate;
                $accesTokenModel->access_token=$tokens['access_token'];
                $accesTokenModel->refresh_token=$tokens['refresh_token'];
                $accesTokenModel->save();
            } else {
                $accesTokenModel=new ShoperAccessTokens(['shop_id'=>$shopId]);
                $accesTokenModel->expires_at=$expirationDate;
                $accesTokenModel->access_token=$tokens['access_token'];
                $accesTokenModel->refresh_token=$tokens['refresh_token'];
                $accesTokenModel->save();
            }

            $tr->commit();
        } catch (\PDOException $ex) {
            echo "Exception";
            if ($tr->inTransaction()) {
                $tr->rollBack();
            }
            throw new \Exception('Database error', 0, $ex);
        } catch (\Exception $ex) {
            echo "Exception2";
            print_r($ex->getMessage());
            if ($tr->inTransaction()) {
                $tr->rollBack();
            }
            throw $ex;
        }
    }

    /**
     * client paid for the app
     * arguments:
     * - action
     * - shop
     * - shop_url
     * - application_code
     * - hash
     * - timestamp
     *
     * @param array $arguments
     * @throws \Exception
     */
    public function billingInstallHandler($arguments)
    {
        try {
            $shopModel=ShoperShops::findOne(['shop'=>$arguments['shop']]);
            if (!$shopModel){
                throw new \Exception('Shop not found: ' . $arguments['shop']);
            }
            $billlingModel= new ShoperBillings(['shop_id'=>$shopModel->id]);
            $billlingModel->save();
        } catch (\PDOException $ex) {
            throw new \Exception('Database error', 0, $ex);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * upgrade action
     * arguments:
     * - action
     * - shop
     * - shop_url
     * - application_code
     * - application_version
     * - hash
     * - timestamp
     *
     * @param array $arguments
     * @throws \Exception
     */
    public function upgradeHandler($arguments)
    {
        try {
            $shopModel=ShoperShops::findOne(['shop'=>$arguments['shop']]);
            if (!$shopModel){
                throw new \Exception('Shop not found: ' . $arguments['shop']);
            }
            $shopModel->version=$arguments['application_version'];
            $shopModel->save();
        } catch (\PDOException $ex) {
            throw new \Exception('Database error', 0, $ex);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * app is being uninstalled
     * arguments:
     * - action
     * - shop
     * - shop_url
     * - application_code
     * - hash
     * - timestamp
     *
     * @param array $arguments
     * @throws \Exception
     */
    public function uninstallHandler($arguments)
    {
        try {

            $shopModel=ShoperShops::findOne(['shop'=>$arguments['shop']]);
            if (!$shopModel){
                throw new \Exception('Shop not found: ' . $arguments['shop']);
            }

            $shopModel->installed=0;
            $shopModel->save();

            $accesTokenModel=ShoperAccessTokens::findOne(['shop_id'=>$shopModel->id]);
            $accesTokenModel->access_token=null;
            $accesTokenModel->refresh_token=null;
            $accesTokenModel->save();

        } catch (\PDOException $ex) {
            throw new \Exception('Database error', 0, $ex);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * client paid for a subscription
     * arguments:
     * - action
     * - shop
     * - shop_url
     * - application_code
     * - subscription_end_time
     * - hash
     * - timestamp
     *
     * @param $arguments
     * @throws \Exception
     */
    public function billingSubscriptionHandler($arguments)
    {
        try {

            $shopModel=ShoperShops::findOne(['shop'=>$arguments['shop']]);
            if (!$shopModel){
                throw new \Exception('Shop not found: ' . $arguments['shop']);
            }
            // make sure we convert timestamp correctly
            $expiresAt = date('Y-m-d H:i:s', strtotime($arguments['subscription_end_time']));

            if (!$expiresAt) {
                throw new \Exception('Malformed timestamp');
            }

            $subscriptionModel= new ShoperSubscriptions();
            $subscriptionModel->shop_id=$shopModel->id;
            $subscriptionModel->expires_at=$expiresAt;
            $subscriptionModel->save();
        } catch (\PDOException $ex) {
            throw new \Exception('Database error', 0, $ex);
        } catch (\Exception $ex) {
            throw $ex;
        }
    }

    /**
     * helper function for ID finding
     * @param $shop
     * @throws \Exception
     * @return string
     */
    public function getShopId($shop)
    {
        $shopModel=ShoperShops::findOne(['shop'=>$shop]);
        if (!$shopModel) {
            throw new \Exception('Shop not found: ' . $shop);
        }

        return $shopModel->id;
    }

}
