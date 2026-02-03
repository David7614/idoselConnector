<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_subscriptions".
 *
 * @property int $id
 * @property int $shop_id
 * @property string|null $expires_at
 *
 * @property ShoperShops $shop
 */
class ShoperSubscriptions extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_subscriptions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shop_id'], 'required'],
            [['shop_id'], 'integer'],
            [['expires_at'], 'safe'],
            [['shop_id'], 'exist', 'skipOnError' => true, 'targetClass' => ShoperShops::className(), 'targetAttribute' => ['shop_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'shop_id' => 'Shop ID',
            'expires_at' => 'Expires At',
        ];
    }

    /**
     * Gets query for [[Shop]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShop()
    {
        return $this->hasOne(ShoperShops::className(), ['id' => 'shop_id']);
    }
}
