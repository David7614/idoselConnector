<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "orders".
 *
 * @property int $id
 * @property int $order_id
 * @property int $customer_id
 * @property string $created_on
 * @property string $finished_on
 * @property string $status
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $zip_code
 * @property string|null $country_code
 * @property int $user_id
 * @property int $page
 *
 * @property User $user
 */
class Ordersv2 extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'ordersv2';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order_id', 'customer_id', 'created_on', 'status', 'user_id'], 'required'],
            [['order_id','customer_id', 'user_id', 'page'], 'integer'],
            [['email', 'created_on', 'finished_on', 'status', 'phone', 'zip_code', 'country_code'], 'string'],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'order_id' => 'Order ID',
            'customer_id' => 'Customer ID',
            'created_on' => 'Created On',
            'finished_on' => 'Finished On',
            'status' => 'Status',
            'email' => 'Email',
            'phone' => 'Phone',
            'zip_code' => 'Zip Code',
            'country_code' => 'Country Code',
            'user_id' => 'User ID',
        ];
    }

    /**
     * Gets query for [[User]].
     *
     * @return \yii\db\ActiveQuery|\app\models\queries\UserQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }

    public function getCustomer()
    {
        return Customers::find()->where(['customer_id' => $this->customer_id])->one();
    }

    /**
     * @param array $orderItems
     * @param int $user_id
     * @param int $page
     *
     * @return Orders
     */
    public static function addOrderTomek(array $orderItems, int $user_id, int $page): Orders
    {
        if(($order = self::find()->where(['order_id' => $orderItems['order_id'], 'user_id' => $user_id])->one())) {
            

            // $order->save();
            // return $order;
        }

        $order = new self([
            'order_id' => $orderItems['order_id'],
            'customer_id' => $orderItems['customer_id'],
            'created_on' => $orderItems['created_on'],
            'status' => $orderItems['status'],
            'email' => htmlentities(html_entity_decode($orderItems['email'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8'),
            'phone' => $orderItems['phone'],
            'zip_code' => $orderItems['zip_code'],
            'country_code' => $orderItems['country_code'],
            'user_id' => $user_id,
            'page' => $page,
            'order_positions' => serialize($orderItems['order_positions'])
        ]);

        $order->status = $orderItems['status'];
        $order->finished_on = $orderItems['finished_on'];

        if ($order->save()){
            echo "saved";
        }else{
            print_r($order->getErrors());
        }

        return $order;
    }
    public static function addOrder(array $orderItems, int $user_id, int $page): Ordersv2
    {

        $orderId=$orderItems['order_id'];
        if (isset($orderItems['orderSerialNumber']) && $orderItems['orderSerialNumber']){
            $orderId=$orderItems['orderSerialNumber'];
        }

        $order = self::find()->where(['order_id' => $orderId, 'user_id' => $user_id])->one();
        if(!$order){
            $order = new self([
                'order_id' => $orderId,
                'user_id' => $user_id
            ]);
        }


        $order->customer_id=$orderItems['customer_id'];
        $order->created_on=$orderItems['created_on'];
        $order->finished_on=$orderItems['finished_on'];
        $order->status=$orderItems['status'];
        $order->email=htmlentities(html_entity_decode($orderItems['email'], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $order->phone=$orderItems['phone'];
        $order->zip_code=$orderItems['zip_code'];
        $order->country_code=$orderItems['country_code'];
        $order->order_positions=$orderItems['order_positions'];
        $order->page=$page;

        $order->save(false);
        if ($order->save()){
            echo "saved".PHP_EOL;
        }else{
            print_r($order->getErrors());
            die ("!!!!!!!!");
        }
        return $order;
    }

    /**
     * @param array $positionItem
     * @return bool
     */
    public function addPosition(array $positionItem): bool
    {
        if(($position = $this->findPosition($positionItem['product_id'])) !== null) {
            $position->amount = $positionItem['amount'];
            $position->price = $positionItem['price'];

            return $position->save();
        }

        $positionItem['order_id'] = $this->id;

        $position = new Positions($positionItem);
        return $position->save();
    }

    public function findPosition($product_id)
    {
        return Positions::find()->where(['order_id' => $this->id, 'product_id' => $product_id])->one();
    }

    public function getPositions(): array
    {
        return unserialize($this->order_positions);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\queries\OrdersQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\queries\OrdersQuery(get_called_class());
    }
}
