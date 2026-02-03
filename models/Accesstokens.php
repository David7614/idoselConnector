<?php

namespace app\models;

use League\OAuth2\Client\Token\AccessToken;
use Yii;

/**
 * This is the model class for table "accesstokens".
 *
 * @property int $id
 * @property int|null $id_user
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string|null $expiry
 * @property string|null $scope
 *
 * @property User $user
 */
class Accesstokens extends \yii\db\ActiveRecord
{
    private $_user;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'accesstokens';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user'], 'integer'],
            [['access_token'], 'required'],
            [['access_token', 'refresh_token', 'expiry', 'scope'], 'string', 'max' => 255],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['id_user' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_user' => 'Id User',
            'access_token' => 'Access Token',
            'refresh_token' => 'Refresh Token',
            'expiry' => 'Expiry',
            'scope' => 'Scope',
        ];
    }

    /**
     * @param $user
     */
    public function setUser($user)
    {
        $this->_user = $user;
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'id_user']);
    }

    /**
     * @return AccessToken
     */
    public function getToken()
    {
        /** @var $token self */
        if(($token = self::find()->where(['id_user' => $this->_user->id])->one()) === null) {
            return new AccessToken();
        }

        $config = [
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_in' => $token->expiry - time()
        ];
        return new AccessToken($config);
    }

    /**
     * @param AccessToken $token
     */
    public function setToken(AccessToken $token)
    {
        if(($exists_token = self::find()->where(['id_user' => $this->_user->id])->one()) !== null) {
            $exists_token->access_token = $token->getToken();
            $exists_token->refresh_token = $token->getRefreshToken();
            $exists_token->expiry = $token->getExpires();

            return $exists_token->save(false);
        }

        $this->access_token = $token->getToken();
        $this->refresh_token = $token->getRefreshToken();
        $this->expiry = $token->getExpires();
        return $this->save(false);
    }

    public function createState($state)
    {
        if(($exists_token = self::find()->where(['id_user' => $this->_user->id])->one()) !== null) {
            $exists_token->state = $state;
            return $this->save(false);
        }

        $this->id_user = $this->_user->id;
        $this->access_token = "";
        $this->refresh_token = "";
        $this->expiry = 00;
        $this->state = $state;
        return $this->save(false);
    }

    public function getCurrentState()
    {
        if(($exists_token = self::find()->where(['id_user' => $this->_user->id])->one()) !== null) {
            return $exists_token->state;
        }

        return null;
    }

    /**
     * Method is checking if user is already logged in or not
     *
     * @param $id_user int
     *
     * @return bool
     */
    public static function isLoggedIn($id_user)
    {
        return self::find()->where(['id_user' => $id_user])->andWhere(['not', ['access_token' => ""]])->one() !== null;
    }

    public static function find()
    {
        return new \app\models\queries\AccesstokensQuery(get_called_class());
    }
}
