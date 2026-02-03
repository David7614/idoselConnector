<?php

namespace app\modules\IAI\Authorization;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Lcobucci\JWT;

/**
 * Class provides OAuth2 refresh token functionality
 */
class Oauth2Client extends AbstractClient
{
    /**
     * OAuth2 provider connector
     *
     * @var GenericProvider
     */
    protected $provider;

    /**
     * {@inheritdoc}
     */
    protected function initProvider()
    {
        $provider = new GenericProvider(
            [
                'clientId' => $this->config->getId(),
                'clientSecret' => $this->config->getSecret(),
                'urlAccessToken' => $this->getAccessTokenUrl(),
                'urlAuthorize' => '',
                'urlResourceOwnerDetails' => '',
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
     * Exchanges current access token for a new (fresh) one
     *
     * @param \League\OAuth2\Client\Token\AccessToken $accessToken Current access token
     *
     * @return \League\OAuth2\Client\Token\AccessToken
     *
     * @throws \app\modules\IAI\Authorization\Exception\InvalidPublicKeyException
     * @throws \app\modules\IAI\Authorization\Exception\InvalidTokenException
     */
    public function refreshToken(AccessToken $accessToken)
    {
        $token = $this->provider->getAccessToken('refresh_token', [
            'refresh_token' => $accessToken->getRefreshToken(),
        ]);

        $Parser = new JWT\Parser();
        $newAccessToken = $Parser->parse($token->getToken());

        if (false === $newAccessToken->verify($this->signer, $this->getPublicKey())) {
            throw new Exception\InvalidTokenException('Received an invalid access token from authorization server.');
        }

        return $token;
    }

}