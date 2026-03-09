<?php
namespace app\models;

use app\modules\idosellv3\models\ApiClient;
use Yii;
use yii\helpers\Url;
use yii\web\IdentityInterface;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string $client_id
 * @property string $client_secret
 * @property string $register_date
 * @property bool $active
 * @property string $registerToken
 * @property string $uuid
 */
class User extends \yii\db\ActiveRecord implements IdentityInterface
{
    /** Computed via subquery in UserSearch — stores MAX(finished_at) from xml_feed_queue */
    public $lastFinishedAt;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'username', 'email', 'password', 'client_id', 'client_secret'], 'required'],
            [['id', 'active'], 'integer'],
            [['username', 'email', 'password', 'client_id', 'client_secret', 'register_date', 'registerToken', 'uuid'], 'string', 'max' => 255],
            [['shop_type'], 'string', 'max' => 10],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'username'      => 'Adres domeny technicznej',
            'email'         => 'Email',
            'password'      => 'Hasło',
            'client_id'     => 'Client ID',
            'client_secret' => 'Client Secret',
        ];
    }

    /**
     * {@inheritdoc}
     * @return \app\models\queries\UserQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\queries\UserQuery(get_called_class());
    }

    /**
     * @return UserConfig
     */
    public function getConfig()
    {
        return new UserConfig($this->id);
    }

    public function getToken()
    {
        return $this->registerToken;
    }

    /**
     * Register new customer
     *
     * @param $username
     * @param $email
     * @param $password
     *
     * @return bool
     */
    public function register($username, $email, $password, $shop_type = '')
    {
        $username = str_replace(['https://', 'http://'], ['', ''], $username);
        if (($user = self::findByUsername($username)) !== null) {
            throw new \Exception('User is already registered');
        }

        if (($user = self::find()->where(['email' => $email])->one()) !== null) {
            throw new \Exception('User is already registered');
        }

        $this->username      = $username;
        $this->email         = $email;
        $this->shop_type     = $shop_type;
        $this->password      = password_hash($password, PASSWORD_BCRYPT);
        $this->client_id     = sha1($username . $email);
        $this->client_secret = md5(hash('sha256', $this->client_id . $username));
        $this->register_date = date('Y-m-d H:i:s');
        $this->active        = false;
        $this->registerToken = md5($username . rand() . time());
        $this->uuid          = md5(rand());
        $this->save(false);

        return $this;
    }

    /**
     * @param int|string $id
     * @return User|array|IdentityInterface|null
     */
    public static function findIdentity($id)
    {
        if (($user = self::find()->where(['id' => $id])->one()) !== null) {
            return $user;
        }

        return null;
    }

    /**
     * @param $username
     * @return User|array|null
     */
    public static function findByEmail($email)
    {
        if (($user = self::find()->where(['email' => $email])->one()) !== null) {
            return $user;
        }

        return null;
    }

    /**
     * @param $username
     * @return User|array|null
     */
    public static function findByUsername($username)
    {
        if (($user = self::find()->where(['username' => $username])->one()) !== null) {
            return $user;
        }

        return null;
    }

    /**
     * @param mixed $token
     * @param null $type
     * @return User|array|IdentityInterface|null
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        if (($user = self::find()->where(['registerToken' => $token])->one()) !== null) {
            return $user;
        }

        return null;
    }

    /**
     * @param $client_id
     * @return User|array|null
     */
    public static function findByClientID($client_id)
    {
        if (($user = self::find()->where(['client_id' => $client_id])->one()) !== null) {
            return $user;
        }

        return null;
    }

    /**
     * @param $uuid
     * @return User|array|null
     */
    public static function findByUUID($uuid)
    {
        if (($user = self::find()->where(['uuid' => $uuid])->one()) !== null) {
            return $user;
        }

        return null;
    }

    /**
     * @return int|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getAuthKey()
    {
        return $this->registerToken;
    }

    /**
     * @param $password
     * @return bool
     */
    public function validatePassword($password)
    {
        return password_verify($password, $this->password);
    }

    /**
     * @param string $authKey
     * @return bool
     */
    public function validateAuthKey($authKey)
    {
        return $this->registerToken === $authKey;
    }

    /**
     * @param $client_id
     * @param $secret_key
     * @return User
     */
    public static function validateSecretKey($client_id, $secret_key)
    {
        return self::findOne(['client_id' => $client_id, 'secret_key' => $secret_key]);
    }

    /**
     * @param $current_user_id
     *
     * @return int
     */
    public static function getNextUserId($current_user_id): int
    {
        $users_collection = User::find()->all();
        $found_current    = false;
        $next_user_id     = $current_user_id;

        foreach ($users_collection as $user) {
            if ($user->id == $current_user_id) {
                $found_current = true;
                continue;
            }

            if ($found_current) {
                $next_user_id = $user->id;
                break;
            }
        }

        if ($next_user_id == $current_user_id || $current_user_id == 0) {
            return self::findFirstId();
        }

        return $next_user_id;
    }

    /**
     * @return int
     */
    private static function findFirstId(): int
    {
        $model = self::find()->one();

        return $model->id;
    }

    public function getProductsUrl()
    {
        return Url::to(['/xml/' . $this->uuid . '/products.xml'], true);
    }
    public function getOrdersUrl()
    {
        return Url::to(['/xml/' . $this->uuid . '/orders.xml'], true);
    }
    public function getCategoriesUrl()
    {
        return Url::to(['/xml/' . $this->uuid . '/categories.xml'], true);
    }
    public function getCustomersUrl()
    {
        return Url::to(['/xml/' . $this->uuid . '/customers.xml'], true);
    }

    public function getUrl()
    {
        $url = $this->fronturl ? $this->fronturl : $this->username;
        return 'https://' . $url;
    }

    // public function getConnectionClass(){
    //     switch ($this->shop_type){
    //         case 'shoper':

    //         break;
    //     }
    //     return '';
    // }

    public function saveSettings($postData)
    {
        $default_settings = [
            'product_image',
            'product_description',
            'product_brand',
            'product_stock',
            'product_price_before_discount',
            'product_price_buy',
            'product_categorytext',
            'product_line',
            'product_variant',
            'product_parameter',
            'stock_ids',
            'customer_feed_email',
            'customer_feed_registration',
            'customer_feed_first_name',
            'customer_feed_last_name',
            'customer_zip_code',
            'customer_phone',
            'customer_tags',
        ];

        foreach ($default_settings as $index) {
            $valuesToSet[$index] = isset($postData[$index]) ? $postData[$index] : 0;
            $this->config->set($index, $valuesToSet[$index]);
        }

        return ['ok', []];
    }

    public function apiEnabled()
    {
        $apiKey = $this->getUserData('api3_key');
        if ($apiKey) {
            return true;
        }
        return false;
    }

    public function getApiKey()
    {
        $apiKey = $this->getUserDataValue('api3_key');
        return $apiKey;
    }

    public function checkApiReady()
    {
        $client = new ApiClient($this->username, $this->getApiKey());
        return $client->sendRequest('/api/admin/v3/system/config');
    }

    //  public function getUserConfigs()
    // {
    //     return $this->hasMany(UserConfig::className(), ['id_user' => 'id']);
    // }

    /**
     * Gets query for [[UserDatas]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getUserDatas()
    {
        return $this->hasMany(UserData::className(), ['user_id' => 'id']);
    }

    public function getUserData($name)
    {
        return UserData::findOne(['user_id' => $this->id, 'name' => $name]);
    }
    public function getUserDataValue($name)
    {
        $obj = $this->getUserData($name);
        if ($obj) {
            return $obj->value;
        }
        return null;
    }

    public function setUserDataValue($name, $value)
    {
        $obj = $this->getUserData($name);
        if (! $obj) {
            $obj = new UserData(['user_id' => $this->id, 'name' => $name]);
        }
        $obj->value = $value;
        if ($obj->save()) {
            return true;
        }
        var_dump($obj->getErrors());
        return false;
        // return $obj->save();
    }

    public function countDatabaseElements($type)
    {
        switch ($type) {
            case 'products':
                return Product::find()->where(['user_id' => $this->id])->count();
            case 'customer':
                return Customers::find()->where(['user_id' => $this->id])->count();
            case 'order':
                return Ordersv2::find()->where(['user_id' => $this->id])->count();
            case 'category':
                return 'not in db';

        }
        return 0;
    }

    public function getConfigValue($field, $lang = '', $useDefaultIfEmpty = true)
    {
        if ($this->config->get($field . $lang) || ! $useDefaultIfEmpty) {
            return $this->config->get($field . $lang);
        }
        return $this->config->get($field);
    }

    public function setConfigValue($field, $value, $lang)
    {
        $this->config->set($field . $lang, $value);
    }

    public function setActive($active)
    {
        $this->active=$active;
        if ($active){
            // $this->createCampaign();
        }else{
            $this->removeCampaign();
            $this->getConfig()->clearConfigs();
        }
        $this->save();
    }

    private function removeCampaign()
    {
        $IdosellCampainModel=new IdosellCampaigns($this);
        if ($campainId=$this->getConfig()->get('campain_id')){
            $IdosellCampainModel->deleteCampaign($campainId, $this);
        }
    }
    private function createCampaign()
    {
        $IdosellCampainModel=new IdosellCampaigns($this);
        if ($campainId=$this->getConfig()->get('campain_id')){
            $IdosellCampainModel->deleteCampaign($campainId, $this);
        }


        $result=$IdosellCampainModel->createCampaign($shopIds=[1]);
        if (!$result)
        {
            Yii::$app->session->addFlash('error', 'Nie udało się zapisać kampani');
            return false;
        }
        if ($campainId=$result['results'][0]['id']){
            $this->getConfig()->set('campain_id', $campainId);
            $result=$IdosellCampainModel->createSnippets($campainId, $this);
        }
        return true;
    }

    public function saveTrackpoint($trackpoint, $shopIds=[1])
    {
        $this->getConfig()->set('trackpoint', $trackpoint);
        $this->createCampaign($shopIds);
    }

    /* Reset product queue after shop id change
     */
    private function resetProductQueue()
    {

    }

    /* Update frontname in shop after shop id change
     */
    private function updateFrontname()
    {

    }

    public function setCustomerShoipId($newId)
    {
        $oldId = $this->config->get('customer_set_shop_id');

        if ($oldId == $newId)
        {
            return;
        }

        $this->config->set('customer_set_shop_id', $newId);
        $this->resetProductQueue();
        $this->updateFrontname();
    }

    public function getCustomerShopId()
    {
        return $this->config->get('customer_set_shop_id');
    }

    public function setCustomerShopUrl($url)
    {
        if (!$url) {
            $url = $this->getPlainUrl();
        }

        $url = 'https://' . $url;

        $oldUrl = $this->config->get('customer_shop_url');

        if ($oldUrl == $url)
        {
            return;
        }

        $this->config->set('customer_shop_url', $url);
    }

    public function getShopUrl()
    {
        return $this->config->get('customer_shop_url');
    }

    public function getShopUrlFinal()
    {
        $url = $this->config->get('customer_shop_url');
        return $url ?? $this->getUrl();
    }
}
