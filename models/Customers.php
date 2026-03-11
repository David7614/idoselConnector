<?php
namespace app\models;

use Yii;

/**
 * This is the model class for table "customers".
 *
 * @property int $id
 * @property int $customer_id
 * @property string $email
 * @property string $registration
 * @property string $first_name
 * @property string $lastname
 * @property string $zip_code
 * @property string $sms_frequency
 * @property string $newsletter_frequency
 * @property string $nlf_time
 * @property string $data_permission
 * @property string $data_hash
 * @property string $parameters
 * @property int $user_id
 * @property int $page
 *
 * @property User $user
 */
class Customers extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'customers';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['customer_id', 'email', 'login', 'registration', 'user_id', 'page'], 'required'],
            [['user_id', 'page', 'is_wholesaler'], 'integer'],
            [['registration', 'nlf_time'], 'safe'],
            [['customer_id', 'email', 'login', 'first_name', 'lastname', 'sms_frequency', 'newsletter_frequency', 'data_permission', 'data_hash'], 'string', 'max' => 255],
            [['parameters'], 'string'],
            [['zip_code'], 'string', 'max' => 55],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::className(), 'targetAttribute' => ['user_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id'           => 'ID',
            'customer_id'  => 'Customer ID',
            'email'        => 'Email',
            'registration' => 'Registration',
            'first_name'   => 'First Name',
            'lastname'     => 'Lastname',
            'zip_code'     => 'Zip Code',
            'user_id'      => 'User ID',
            'page'         => 'Page',
            'parameters' => 'Parameters',
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

    /**
     * {@inheritdoc}
     * @return \app\models\queries\CustomersQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\queries\CustomersQuery(get_called_class());
    }

    /**
     * @param $customer_data
     * @param $user_id
     * @param $page
     * @return bool
     */
    public static function getCustomer($customer_data, $user_id)
    {
        return self::find()->where(['customer_id' => (string)$customer_data['customer_id'], 'user_id' => $user_id])->one();
    }
    public static function updateCustomerAgreements($userId, $client_number, $type, $value, $nlfTime)
    {
        $customer = self::findOne(['customer_id' => (string)$client_number, 'user_id' => $userId]);
        if (!$customer){
            return ''; // jak nie ma to wpadnie z customersów poprawnie
        }

        if ($customer->{$type.'_frequency'} == $value) {
            return false;
        }
        echo "zmieniamy panocku na ".$value." z ".$customer->{$type.'_frequency'};
        die ("!!");
        echo PHP_EOL;
        $customer->{$type.'_frequency'} = $value;
        $customer->nlf_time = $nlfTime;
        $customer->save();


    }
    public static function addCustomer($customer_data, $user_id, $page): self
    {
        $hash                       = md5(json_encode($customer_data));
        $customer_data['page']      = $page;
        $customer_data['user_id']   = $user_id;
        $customer_data['data_hash'] = $hash;
        $customer=null;
        // var_dump($customer_data);
        if (isset($customer_data['customer_id'])){
            $customer = self::findOne(['customer_id' => (string)$customer_data['customer_id'], 'user_id' => $user_id]);
            if ($customer){
                foreach ($customer_data as $k=>$v){
                    $customer->{$k}=$v;
                }
            }
        }
        $customer = $customer ? $customer : new self($customer_data);

        if (!$customer->email){
            if ($customer->login){
                $customer->email = $customer->login;
            }
        }
        if ($customer->last_modification_date == '0000-00-00 00:00:00'){
            $customer->last_modification_date = NULL;
        }
        if ($customer->registration == '0000-00-00 00:00:00'){
            $customer->registration = NULL;
        }
        if ($customer->save(false)) {
            echo "CUSTOMER SAVED";
            return $customer;
        } else {
            print_r($customer->getErrors());
        }
    }

    public function isCustomerValidForXml(){
        if ($this->newsletter_frequency == 'never' && $this->sms_frequency == 'never'){
            return false;
        }
        return true;
    }

    public function updateCustomer($customer_data): self
    {
        $data_hash = md5(json_encode($customer_data));
        if ($data_hash == $this->data_hash) {
            echo "HASH SAME " . PHP_EOL;
            return $this;
        }

        $this->first_name             = $customer_data['first_name'];
        $this->email             = $customer_data['email'];
        $this->login             = $customer_data['login'];
        $this->lastname               = $customer_data['lastname'];
        $this->newsletter_frequency   = $customer_data['newsletter_frequency'];
        $this->sms_frequency          = $customer_data['sms_frequency'];
        $this->nlf_time               = $customer_data['nlf_time'];
        $this->data_permission        = $customer_data['data_permission'];
        $this->phone                  = $customer_data['phone'];
        $this->last_modification_date = $customer_data['last_modification_date'];
        $this->data_hash              = $data_hash;
        $this->is_wholesaler          = $customer_data['is_wholesaler'];
        $this->parameters             = $customer_data['parameters'];

        if (!$this->email){
            if ($this->login){
                $this->email = $this->login;
            }
        }

        if ($this->save()) {
            return $this;
        }else{
            var_dump($this->getErrors());

            die("!");
        }
    }


}
