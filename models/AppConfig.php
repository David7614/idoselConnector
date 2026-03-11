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
    const FORCE_ALL_INCREMENTAL='FORCE_ALL_INCREMENTAL';
    const DISPLAY_DEBUG='DISPLAY_DEBUG';

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
}
