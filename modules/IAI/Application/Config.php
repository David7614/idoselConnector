<?php

namespace app\modules\IAI\Application;

use app\models\User;
use Yii;
use yii\helpers\Url;

/**
 * Class representing application configuration
 */
class Config
{
    private $_user;

    public function __construct($user)
    {
        $this->_user = $user;
    }

    /**
     * Sets IAI Panel's technical domain
     *
     * @return string
     * @throws \Exception
     */
    public function getPanelTechnicalDomain()
    {
        return $this->_user->username;
    }

    /**
     * Gets IAI Panel's technical domain
     *
     * @param string $panelTechnicalDomain
     *
     * @return Config
     * @throws \Exception
     */
    public function setPanelTechnicalDomain($panelTechnicalDomain)
    {
        $this->_user->config->set('panel-technical-domain', $panelTechnicalDomain);

        return $this;
    }

    /**
     * Gets application ID (client_id)
     *
     * @return string
     */
    public function getId()
    {
        return $this->_user->client_id;
    }

    /**
     * Sets application ID (client_id)
     *
     * @param string $id
     *
     * @return Config
     * @throws \Exception
     */
    public function setId($id)
    {

        $this->_user->client_id = $id;

        $this->_user->save();

        return $this;
    }

    /**
     * Gets application secret (client_secret)
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->_user->client_secret;
    }

    /**
     * Sets application secret (client_secret)
     *
     * @param string $secret
     *
     * @return Config
     * @throws \Exception
     */
    public function setSecret($secret)
    {
        $this->_user->client_secret = $secret;
        $this->_user->save();

        return $this;
    }

    /**
     * Gets URI to redirect to after authentication
     *
     * @return string
     * @throws \Exception
     */
    public function getRedirectUri()
    {
        return Url::home(true).'site/panel/';
    }

    /**
     * Sets URI to redirect to after authentication
     *
     * @param string $redirectUri
     *
     * @return Config
     * @throws \Exception
     */
    public function setRedirectUri($redirectUri)
    {
        $this->_user->config->set('redirect-url', $redirectUri);

        return $this;
    }
}