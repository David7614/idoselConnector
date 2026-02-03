<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "positions".
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property int $quantity
 * @property string $price
 * @property int $order_id
 *
 * @property Orders $order
 */
class Positions extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'positions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'product_id', 'amount', 'price', 'order_id'], 'required'],
            [['id', 'product_id', 'amount', 'order_id'], 'integer'],
            [['price'], 'string', 'max' => 255],
            [['order_id'], 'exist', 'skipOnError' => true, 'targetClass' => Orders::className(), 'targetAttribute' => ['order_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'product_id' => 'Product ID',
            'amount' => 'Quantity',
            'price' => 'Price',
            'order_id' => 'Order ID',
        ];
    }

    /**
     * Gets query for [[Order]].
     *
     * @return \yii\db\ActiveQuery|\app\models\queries\OrdersQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Orders::className(), ['id' => 'order_id']);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\queries\PositionsQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\queries\PositionsQuery(get_called_class());
    }
}
