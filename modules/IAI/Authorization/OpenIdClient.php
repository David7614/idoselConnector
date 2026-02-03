<?php

namespace app\modules\IAI\Authorization;

use app\modules\IAI\Application as IAIApplication;

use OpenIDConnectClient\OpenIDConnectProvider;
use Lcobucci\JWT\Signer;

/**
 * Class provides OpenID Connect authentication and authorization
 */
class OpenIdClient extends AbstractClient
{
    /**
     * Application state access object
     *
     * @var IAIApplication\StateInterface
     */
    protected $state;

    public function __construct(IAIApplication\Config $config, IAIApplication\PublicKeyStorageInterface $keyStorage, IAIApplication\StateInterface $state)
    {
        parent::__construct($config, $keyStorage);
        $this->state = $state;
    }


    /**
     * {@inheritdoc}
     */
    protected function initProvider()
    {
        $provider = new OpenIDConnectProvider(
            [
                'clientId' => $this->config->getId(),
                'clientSecret' => $this->config->getSecret(),
                'idTokenIssuer' => 'https://' . $this->config->getPanelTechnicalDomain(),
                'redirectUri' => $this->config->getRedirectUri(),
                'urlAuthorize' => $this->getAuthorizeUrl(),
                'urlAccessToken' => $this->getAccessTokenUrl(),
                'urlResourceOwnerDetails' => '', //no details endpoint yet
                'publicKey' => $this->getPublicKey(),
                'scopes' => [
                    'openid',
                    'profile',
                    'api-pa'
                ]
            ],
            [
                'signer' => $this->signer
            ]
        );

        return $provider;
    }

    /**
     * Redirects to OpenID Connect authentication server requesting passed scopes
     *
     * @param string[] $scopes
     */
    public function startAuthentication($scopes = [])
    {
        $state = $this->state->createAuthenticationStateString();
        $this->state->setState($state);

        $options = [
            'state' => $state,
            'scope' => $scopes
        ];
        header(sprintf('Location: %s', $this->provider->getAuthorizationUrl($options)), true, 302);
        die;
    }

    /**
     * Finalizes authentication using auth code and application state retrieved from OpenID Connect provider
     *
     * @param string $givenAuthCode Auth code from OpenID Connect provider
     * @param string $givenState Application state from OpenID Connect provider
     *
     * @return \OpenIDConnectClient\AccessToken Access token with idToken
     *
     * @throws \app\modules\IAI\Authorization\Exception\InvalidStateException
     * @throws \app\modules\IAI\Authorization\Exception\InvalidAuthCodeException
     * @throws \app\modules\IAI\Authorization\Exception\InvalidTokenException
     */
    public function finalizeAuthentication($givenAuthCode, $givenState)
    {
        if (empty($givenAuthCode)) {
            throw new Exception\InvalidAuthCodeException('Given auth code is empty');
        }

//        if ($this->state->getCurrentState() !== $givenState) {
//            throw new Exception\InvalidStateException('Given state is different than current application state.');
//        }

        try {
            $token = $this->provider->getAccessToken(
                'authorization_code',
                ['code' => $givenAuthCode]
            );

            return $token;
        } catch (\Exception $e) {
            throw new Exception\InvalidTokenException('Couldn\'t finish authentication. Please try again later.', 0, $e);
        }
    }
}