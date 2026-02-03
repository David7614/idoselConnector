<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_status".
 *
 * @property int $id
 * @property int $shoper_shops_id
 * @property int $status_id
 * @property int $active
 * @property int $default
 * @property int $type
 * @property int $order
 * @property string $translation
 * @property string $name
 * @property string $message
 *
 * @property Orders $order0
 * @property ShoperShops $shoperShops
 */
class ShoperStatus extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_status';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shoper_shops_id', 'status_id', 'translation', 'name', 'message'], 'required'],
            [['shoper_shops_id', 'status_id', 'active', 'default', 'type', 'order'], 'integer'],
            [['message'], 'string'],
            [['translation'], 'string', 'max' => 5],
            [['name'], 'string', 'max' => 250],
            [['shoper_shops_id'], 'exist', 'skipOnError' => true, 'targetClass' => ShoperShops::className(), 'targetAttribute' => ['shoper_shops_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'shoper_shops_id' => 'Shoper Shops ID',
            'status_id' => 'Status ID',
            'active' => 'Active',
            'default' => 'Default',
            'type' => 'Type',
            'order' => 'Order',
            'translation' => 'Translation',
            'name' => 'Name',
            'message' => 'Message',
        ];
    }

    /**
     * Gets query for [[ShoperShops]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperShops()
    {
        return $this->hasOne(ShoperShops::className(), ['id' => 'shoper_shops_id']);
    }

    public function getSambaStatus(){
        if ($this->type==4){
            return 'canceled';
        }
        if ($this->type==3){
            return 'finished';
        }
        return 'created';
    }


    public function beforeSave($insert){
        if (!parent::beforeSave($insert)) {
            return false;
        }
        $this->message=preg_replace("/[^[:alnum:][:space:]]/u", '', $this->message);
        return true;

    }

}
