<?php

namespace app\modules\api\src;

use app\modules\IAI\Application\Config;
use app\modules\IAI\Authorization\Exception\InvalidPublicKeyException;
use app\modules\IAI\Authorization\Oauth2Client;
use app\modules\IAI\Authorization\OpenIdClient;
use \Exception;

class Connection
{
    /**
     * @var
     */
    private $_config;

    /**
     * @var
     */
    private $_user;

    /**
     * @var ApplicationState
     */
    private $_applicationState;

    /**
     * @var Config
     */
    private $_applicationConfig;

    /**
     * @var KeyStorage
     */
    private $_keyStorage;

    /**
     * Connection constructor.
     * @param $user
     */
    public function __construct($user)
    {
        $this->_user = $user;
        $this->_applicationState = new ApplicationState($this->_user);
        $this->_applicationConfig = new Config($this->_user);
        $this->_keyStorage = new KeyStorage($this->_user->username);
    }


    public function getToken()
    {
        if($this->_applicationState->getToken()->hasExpired()) {
            echo "token expired".PHP_EOL;
            if($this->refreshToken() == null) {
                return null;
            }

            return $this->refreshToken()->getToken();
        }

        return $this->_applicationState->getToken();
    }

    /**
     * @return ApplicationState|null
     */
    public function refreshToken()
    {
        try {
            $client = new Oauth2Client($this->_applicationConfig, $this->_keyStorage);
            $this->_applicationState->setToken(
                $client->refreshToken($this->_applicationState->getToken())
            );

            return $this->_applicationState;

        } catch (\Exception $e) {
           echo $e->getMessage(); 
           // die;
            return null;
        }
    }

    /**
     * @param $code string|null
     * @param $state string|null
     *
     * @return ApplicationState|null
     * @throws Exception
     */
    public function getAccessToken($code = null, $state = null)
    {
        if ($this->_user === null) {
            throw new Exception('User cannot be null');
        }

        $this->_config = $this->_applicationConfig;

        try {
            $client = new OpenIdClient($this->_applicationConfig, $this->_keyStorage, $this->_applicationState);

            //Using OpenID Connect authentication server to log in
            if ($code == null && $state == null) {
                //no authorization code, no application state - first step of authentication - get the authorization code
                $client->startAuthentication();
            }

            //got (or should have) authorization code and application state - exchange authorization code for access token
            $token = $client->finalizeAuthentication($code, $state);

            //save received token in session
            $this->_applicationState->setToken($token);

            //get logged in user details and save them in session
            $user = $token->getIdToken()->getClaim('profile', false);
            if ($user === false) {
                throw new \Exception('Couldn\'t log in');
            }
            $this->_applicationState->setUser($user);
            return $this->_applicationState;
        } catch (\Exception $e) {
            echo $e;
            return null;
        }
    }

    public function getConfig()
    {
        return $this->_config;
    }
}
