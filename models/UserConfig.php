<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "user_config".
 *
 * @property int $id
 * @property int $id_user
 * @property string $key
 * @property string $value
 *
 * @property UserConfig $user
 * @property UserConfig[] $userConfigs
 */
class UserConfig extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_config';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'id_user', 'key', 'value'], 'required'],
            [['id', 'id_user'], 'integer'],
            [['value'], 'string'],
            [['key'], 'string', 'max' => 255],
            [['id'], 'unique'],
            [['id_user'], 'exist', 'skipOnError' => true, 'targetClass' => UserConfig::className(), 'targetAttribute' => ['id_user' => 'id']],
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
            'key' => 'Key',
            'value' => 'Value',
        ];
    }

    private $_cache = null;

    public function __construct($id_user = 0, $config = [])
    {
        $this->id_user = $id_user;
        parent::__construct($config);
    }

    private function loadCache()
    {
        if ($this->_cache !== null) {
            return;
        }
        $this->_cache = [];
        $configs = self::find()->where(['id_user' => $this->id_user])->all();
        foreach ($configs as $config) {
            $this->_cache[$config->key] = $config->value;
        }
    }

    public function set($key, $value)
    {
        if (($config = self::find()->where(['id_user' => $this->id_user, 'key' => $key])->one()) !== null) {
            $config->value = $value;
            $result = $config->save(false);
        } else {
            $newConfig = new self();
            $newConfig->id_user = $this->id_user;
            $newConfig->key = $key;
            $newConfig->value = $value;
            $result = $newConfig->save(false);
        }

        if ($this->_cache !== null) {
            $this->_cache[$key] = $value;
        }

        return $result;
    }

    public function clearConfigs()
    {
        $configs = self::find()->where(['id_user' => $this->id_user])->all();
        foreach ($configs as $config) {
            $config->delete();
        }
        return true;

    }

    public function get($key)
    {
        $this->loadCache();
        return $this->_cache[$key] ?? null;
    }

    public function getStockIdsArray()
    {
        $stockIds=$this->get('stock_ids');
        if (!$stockIds){
            return ['1'];
        }
        $stockIdsArray = str_replace(" ", "", $stockIds);
        return explode(",", $stockIdsArray);
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery|\app\models\queries\UserConfigQuery
     */
    public function getUser()
    {
        return $this->hasOne(UserConfig::className(), ['id' => 'id_user']);
    }

    public function getOrdersDateFrom(){
        return $this->get('orders_date_from');
    }
    public function setOrdersDateFrom($orders_date_from){
        if (!$orders_date_from){
            $this->set('orders_date_from', $orders_date_from);
        }
        $date2YB=date('Y-m-d', strtotime(date('Y-m-d') . ' - 2 years'));

        if ($orders_date_from > $date2YB){
            $orders_date_from = $date2YB;
        }
        $this->set('orders_date_from', $orders_date_from);
    }

    /**
     * Gets query for [[UserConfigs]].
     *
     * @return \yii\db\ActiveQuery|\app\models\queries\UserConfigQuery
     */
    public function getUserConfigs()
    {
        return $this->hasMany(UserConfig::className(), ['id_user' => 'id']);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\queries\UserConfigQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\queries\UserConfigQuery(get_called_class());
    }
}
