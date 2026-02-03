<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "integration_data".
 *
 * @property int $id
 * @property int $customer_id
 * @property string $task
 * @property string $value
 *
 * @property User $customer
 */
class IntegrationData extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'integration_data';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['customer_id', 'task', 'value'], 'required'],
            [['customer_id'], 'integer'],
            [['task', 'value'], 'string', 'max' => 255],
            [['customer_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['customer_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'customer_id' => 'Customer ID',
            'task' => 'Task',
            'value' => 'Value',
        ];
    }

    /**
     * Gets query for [[Customer]].
     *
     * @return \yii\db\ActiveQuery|\app\models\queries\CustomersQuery
     */
    public function getCustomer()
    {
        return $this->hasOne(User::className(), ['id' => 'customer_id']);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\queries\IntegrationDataQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\queries\IntegrationDataQuery(get_called_class());
    }
    
    public static function getLastIntegrationDate(int $customer_id): string
    {
        $data = self::getData('last_integration_date', $customer_id);
        return $data != null ? $data->value : date('Y-m-d');
    }

    public static function setLastIntegrationDate(string $date, int $customer_id): bool
    {
        return self::setData('last_integration_date', $date, $customer_id);
    }

    public static function getLastCustomerIntegrationDate(int $customer_id): string
    {
        $data = self::getData('last_customer_integration_date', $customer_id);

        return $data != null ? $data->value : date('Y-m-d');
    }

    public static function setLastCustomerIntegrationDate(string $date, int $customer_id): bool
    {
        return self::setData('last_customer_integration_date', $date, $customer_id);
    }

    public static function getLastOrdersIntegrationDate(int $customer_id): string
    {
        $data = self::getData('last_orders_integration_date', $customer_id);

        return $data != null ? $data->value : date('Y-m-d');
    }

    public static function setLastOrdersIntegrationDate(string $date, int $customer_id): bool
    {
        return self::setData('last_orders_integration_date', $date, $customer_id);
    }

    public static function isNew($type, $customer_id)
    {
        $data = self::getData("IS_NEW_$type", $customer_id);
        if($data != null) {
            return $data->value;
        }

        return false;
    }

    public static function setIsNew($type, $is_new, $customer_id)
    {
        $is_new=$is_new?1:0; 
        if(self::isNew($type, $customer_id)) {
            self::removeData("IS_NEW_$type", $customer_id);
            return true;
        }

        self::setData("IS_NEW_$type", $is_new, $customer_id);
        return true;
    }


    public static function setData(string $key, string $value, int $customer_id)
    {
        if(($data = self::find()->where(['task' => $key, 'customer_id' => $customer_id])->one()) !== null ) {
            $data->value = $value;
            if ($data->save()){
                return true;
            }    
        }

        echo "NOO".PHP_EOL;

        $data = new self();
        $data->customer_id = $customer_id;
        $data->task = $key;
        $data->value = $value;
        if ($data->save()){
            return true;
        }    
        echo $data->customer_id.PHP_EOL;
        echo $data->task.PHP_EOL;
        echo $data->value.PHP_EOL;
        print_r($data->getErrors());
        return false;
    }


    public static function getData($key, $customer_id)
    {
        return self::find()->where(['task' => $key, 'customer_id' => $customer_id])->one();
    }

    public static function getDataValue($key, $customer_id)
    {
        if ($obj=self::getData($key, $customer_id)){
            return $obj->value;
        }
        return '';
        // return self::find()->where(['task' => $key, 'customer_id' => $customer_id])->one();
    }

    public static function removeData($key, $customer_id) 
    {
        $data = self::getData($key, $customer_id);
        if($data !== null) {
            $data->delete();
        }
    }


}
