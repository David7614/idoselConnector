<?php
namespace app\modules\api\src;

use app\models\Accesstokens;
use http\Exception\RuntimeException;
use \League\OAuth2\Client\Token\AccessToken;
use Yii;
use yii\web\Controller;

/**
 * Keeps current state of application - wraps up $_SESSION and implements required StateInterface
 */
class ApplicationState implements \app\modules\IAI\Application\StateInterface
{
    /**
     * @var int
     */
    private $_user_id;

    /**
     * @var Accesstokens
     */
    private $_access_tokens_depot;

    /**
     * ApplicationState constructor.
     * @param $user
     */
    public function __construct($user)
    {
        $this->_user_id = $user;
        $this->_access_tokens_depot = new Accesstokens();
        $this->_access_tokens_depot->setUser($user);
    }

    /**
     * {@inheritdoc}
     */
    public function setState($state)
    {
        $this->_access_tokens_depot->createState($state);
        Yii::$app->session->set('application_state', $state);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentState()
    {

        // return Yii::$app->session->get('application_state');
        return $this->_access_tokens_depot->getCurrentState();
    }

    /**
     * {@inheritdoc}
     */
    public function createAuthenticationStateString()
    {
        echo "test";
        return bin2hex(random_bytes(31));
    }

    /**
     * Checks if user is logged in
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        return Accesstokens::isLoggedIn(Yii::$app->user->id);
        // return !is_null(Yii::$app->session->get('user'));
    }

    /**
     * Sets user in session
     *
     * @param stdClass $user
     */
    public function setUser($user)
    {
        Yii::$app->session->set('user', $user);
    }

    /**
     * Gets user from session
     *
     * @return stdClass
     */
    public function getUser()
    {
        return Yii::$app->session->get('user');
    }

    /**
     * Saves token in session
     *
     * @param AccessToken $accessToken
     */
    public function setToken(AccessToken $accessToken)
    {
        $this->_access_tokens_depot->setToken($accessToken);
    }

    /**
     * Returns token from session
     *
     * @return AccessToken
     *
     * @throws RuntimeException when there is no token saved in session
     */
    public function getToken()
    {
        if(is_null($this->_access_tokens_depot->getToken())) {
            throw new RuntimeException('Token is null');
        }

        // var_dump($this->_access_tokens_depot->getToken()->getToken());

        try {
            return $this->_access_tokens_depot->getToken();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Checks if access token is still valid
     *
     * @return bool
     *
     * @throws RuntimeException when there is no token saved in session or token has no expire time
     */
    public function hasToRefreshToken()
    {
        return $this->getToken()->hasExpired();
    }
}