<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_subscribers".
 *
 * @property int $id
 * @property int $subscriber_id
 * @property int $shoper_shops_id
 * @property string $email
 * @property int $active
 * @property string $dateadd
 * @property string $ipaddress
 * @property int $lang_id
 * @property string $groups
 *
 * @property ShoperShops $shoperShops
 */
class ShoperSubscribers extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_subscribers';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['subscriber_id', 'shoper_shops_id', 'email', 'active', 'dateadd', 'lang_id'], 'required'],
            [['subscriber_id', 'shoper_shops_id', 'active', 'lang_id'], 'integer'],
            [['dateadd'], 'safe'],
            [['email', 'groups'], 'string', 'max' => 250],
            [['ipaddress'], 'string', 'max' => 50],
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
            'subscriber_id' => 'Subscriber ID',
            'shoper_shops_id' => 'Shoper Shops ID',
            'email' => 'Email',
            'active' => 'Active',
            'dateadd' => 'Dateadd',
            'ipaddress' => 'Ipaddress',
            'lang_id' => 'Lang ID',
            'groups' => 'Groups',
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
}
