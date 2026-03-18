<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "app_config".
 *
 * @property int $id
 * @property string $key
 * @property string $value
 */
class AppConfig extends \yii\db\ActiveRecord
{
    const FORCE_ALL_INCREMENTAL       = 'FORCE_ALL_INCREMENTAL';
    const DISPLAY_DEBUG               = 'DISPLAY_DEBUG';
    const DEFAULT_ORDERS_YEARS_BACK   = 'DEFAULT_ORDERS_YEARS_BACK';

    const STOP_FEED_ORDER             = 'STOP_FEED_ORDER';
    const STOP_FEED_PRODUCT           = 'STOP_FEED_PRODUCT';
    const STOP_FEED_CUSTOMER          = 'STOP_FEED_CUSTOMER';
    const STOP_FEED_CATEGORY          = 'STOP_FEED_CATEGORY';
    const STOP_FEED_SUBSCRIBERS       = 'STOP_FEED_SUBSCRIBERS';
    const STOP_FEED_PHONESUBSCRIBERS  = 'STOP_FEED_PHONESUBSCRIBERS';
    const STOP_FEED_SUBSCRIBERSIMPORT = 'STOP_FEED_SUBSCRIBERSIMPORT';
    const STOP_FEED_CUSTOMERSPARTIAL  = 'STOP_FEED_CUSTOMERSPARTIAL';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'app_config';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['key', 'value'], 'required'],
            [['key'], 'string', 'max' => 25],
            [['value'], 'string', 'max' => 155],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'key' => 'Key',
            'value' => 'Value',
        ];
    }

    static public function setValue($name, $value) {
        $obj=AppConfig::findOne(['key' => $name]);
        if (!$obj){
            $obj = new AppConfig(['key' => $name]);
        }
        $obj->value=(string) $value;
        if ($obj->save()){
            return true;
        }
        var_dump($obj->getErrors());
    }

    static public function getValue($name) {
        $obj=AppConfig::findOne(['key' => $name]);
        if (!$obj){
            return null;
        }
        return $obj->value;
    }

    static public function isTypeStopped(string $type): bool
    {
        $map = [
            'order'            => self::STOP_FEED_ORDER,
            'product'          => self::STOP_FEED_PRODUCT,
            'customer'         => self::STOP_FEED_CUSTOMER,
            'category'         => self::STOP_FEED_CATEGORY,
            'subscribers'      => self::STOP_FEED_SUBSCRIBERS,
            'phonesubscribers' => self::STOP_FEED_PHONESUBSCRIBERS,
            'subscribersimport'=> self::STOP_FEED_SUBSCRIBERSIMPORT,
            'customerspartial' => self::STOP_FEED_CUSTOMERSPARTIAL,
        ];

        if (!isset($map[$type])) {
            return false;
        }

        return self::getValue($map[$type]) == 1;
    }
}
