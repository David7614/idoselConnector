<?php

namespace app\modules\IAI\Authorization;

use app\modules\IAI\Application as IAIApplication;
use Lcobucci\JWT;

/**
 * Abstract of authentication / authorization provider
 */
abstract class AbstractClient
{
    /**
     * Application config
     *
     * @var IAIApplication\Config
     */
    protected $config;

    /**
     * Public key storage for OpenID Connect / OAuth2 public encryption key
     *
     * @var IAIApplication\PublicKeyStorageInterface
     */
    protected $keyStorage;

    /**
     * OpenID Connect / OAuth2 provider client
     *
     * @var \League\OAuth2\Client\Provider\AbstractProvider
     */
    protected $provider;

    /**
     * Public encryption key
     *
     * @var string
     */
    protected $publicKey;

    /**
     * Signer library
     *
     * @var JWT\Signer\Rsa\Sha256
     */
    protected $signer;

    /**
     * AbstractClient constructor.
     *
     * @param \app\modules\IAI\IAIApplication\Config                 $config Application config
     * @param \app\modules\IAI\PublicKeyStorageInterface             $keyStorage Key storage for OpenID Connect / OAuth2 public encryption key
     *
     * @throws \app\modules\IAI\Authorization\Exception\InvalidPublicKeyException
     */
    public function __construct(IAIApplication\Config $config, IAIApplication\PublicKeyStorageInterface $keyStorage)
    {
        $this->config = $config;
        $this->keyStorage = $keyStorage;
        $this->signer = new JWT\Signer\Rsa\Sha256();
        $this->provider = $this->initProvider();
    }

    /**
     * Initializes and returns OpenID Connect / OAuth2 provider client
     *
     * @return \League\OAuth2\Client\Provider\AbstractProvider
     *
     * @throws \app\modules\IAI\Authorization\Exception\InvalidPublicKeyException
     */
    abstract protected function initProvider();

    /**
     * Retrieves public key
     *
     * @return string
     *
     * @throws \app\modules\IAI\Authorization\Exception\InvalidPublicKeyException
     */
    protected function getPublicKey()
    {
        $key = $this->keyStorage->retrieve();
        if (empty($key)) {
            $key = file_get_contents($this->getPublicKeyUrl());
            if (empty($key)) {
                throw new Exception\InvalidPublicKeyException(
                    'Key obtained from issuer ' . $this->config->getPanelTechnicalDomain() . ' is empty'
                );
            }
            $this->keyStorage->store($key);

            //storage must work properly :)
            $key = $this->keyStorage->retrieve();
            if (empty($key)) {
                throw new Exception\InvalidPublicKeyException('Couldn\'t retrieve public key from storage');
            }
        }

        return $key;
    }

    /**
     * Returns provider OpenID Connect / OAuth2 endpoint url
     *
     * @return string
     */
    private function getProviderEndpointUrl()
    {
        return 'https://' . $this->config->getPanelTechnicalDomain() . '/panel/action/authorize/';
    }

    /**
     * Returns issuer public key location
     *
     * @return string
     */
    protected function getPublicKeyUrl()
    {
        return $this->getProviderEndpointUrl() . 'public_key';
    }

    /**
     * Returns authorization endpoint location
     *
     * @return string
     */
    protected function getAuthorizeUrl()
    {
        return $this->getProviderEndpointUrl() . 'authorize';
    }

    /**
     * Returns access token endpoint location
     *
     * @return string
     */
    protected function getAccessTokenUrl()
    {
        return $this->getProviderEndpointUrl() . 'access_token';
    }
}