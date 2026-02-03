<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_billings".
 *
 * @property int $id
 * @property int|null $shop_id
 * @property string|null $created_at
 *
 * @property ShoperShops $shop
 */
class ShoperBillings extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_billings';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shop_id'], 'integer'],
            [['created_at'], 'safe'],
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
            'created_at' => 'Created At',
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
