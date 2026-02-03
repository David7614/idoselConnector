<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "idosel_subscriptions".
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string $customer_login
 * @property string $customer_email
 * @property int $newsletter_approval
 * @property int $sms_approval
 * @property string $date_modification
 * @property int $shop_id
 * @property string $parameters
 */
class IdoselSubscriptions extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'idosel_subscriptions';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['customer_id', 'newsletter_approval', 'sms_approval', 'shop_id', 'user_id'], 'integer'],
            [['date_modification', 'shop_id', 'user_id'], 'required'],
            [['date_modification'], 'safe'],
            [['customer_login', 'customer_email'], 'string', 'max' => 255],
            [['parameters'], 'string'],
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
            'customer_login' => 'Customer Login',
            'customer_email' => 'Customer Email',
            'newsletter_approval' => 'Newsletter Approval',
            'sms_approval' => 'Sms Approval',
            'date_modification' => 'Date Modification',
            'shop_id' => 'Shop ID',
            'parameters' => 'Parameters',
        ];
    }

    public function getCustomerIdent()
    {
        if ($this->customer_id==0 || $this->customer_id==''){
            if ($this->customer_login != ''){
                $customerIdent=$this->customer_login;
            }
            if ($this->customer_email != ''){
                $customerIdent=$this->customer_email;
            }
            $customerIdent='popup-'.htmlspecialchars($customerIdent);
            return $customerIdent;
        }
        else
        {
            $customerIdent=$this->customer_id;
            return $customerIdent;
        }
    }

    private function createCustomerObject()
    {
        $customerIdent=$this->getCustomerIdent();
        $sms_frequency='never';
        if ($this->sms_approval==1){
            $sms_frequency='every day';
        }
        $newsletter_frequency='never';
        if ($this->newsletter_approval==1){
            $newsletter_frequency='every day';
        }

        $customer_item = [];

        $customer_item['customer_id'] = $this->getCustomerIdent();
        $customer_item['email'] = htmlspecialchars($this->customer_email);
        $customer_item['login'] = htmlspecialchars($this->customer_login);
        // echo $this->getCorrectDbDate($customer->shops[0]->date_modification).PHP_EOL;
        $customer_item['registration'] = $this->date_modification;
        $customer_item['last_modification_date'] = $this->date_modification;
        $customer_item['newsletter_frequency'] = $newsletter_frequency;
        $customer_item['sms_frequency'] = $sms_frequency;
        $customer_item['nlf_time'] = $this->date_modification;
        $customer_item['data_permission'] = 'full';
        $customer_item['phone'] = $this->customer_phone;
        $customer_item['is_wholesaler'] = '0 ';
        $customer_item['shop_id'] = $this->shop_id;
        $customer_item['parameters'] = $this->parameters;
        Customers::addCustomer($customer_item, $this->user_id, 9999);
    }

    private function setSynced(){
        $this->sync_flag=1;
        $this->save(false);
        echo "flag saved ".PHP_EOL;
    }

    public static function sync()
    {
        $list=IdoselSubscriptions::find()->where(['sync_flag'=>0])->limit(100000)->all();
        foreach ($list as $item){
            $userId=$item->user_id;
            $customerIdent=$item->getCustomerIdent();
            echo "searching for customer ".$customerIdent." and user ".$userId.PHP_EOL;
            $customer=Customers::find()->where(['user_id'=>$userId, 'customer_id'=>(string)$customerIdent])->one();
            echo "searcing done ".PHP_EOL;
            if (!$customer){
                echo "no customer ".$customerIdent.PHP_EOL;
                $customer=$item->createCustomerObject(); // póki co nie tworzymy tylko paczymy
                // die ("!!!!");
                continue;
            }
            echo "customer found ".$customerIdent.PHP_EOL;
            $sms_frequency='never';
            if ($item->sms_approval==1){
                $sms_frequency='every day';
            }
            $newsletter_frequency='never';
            if ($item->newsletter_approval==1){
                $newsletter_frequency='every day';
            }
            if ($customer->newsletter_frequency==$newsletter_frequency && $customer->sms_frequency==$sms_frequency){
                echo "no changes in customer ".$customerIdent.PHP_EOL;
                $item->setSynced();
                continue;
            }
            echo "updating customer agreements ".$customerIdent.PHP_EOL;
            $customer->newsletter_frequency=$newsletter_frequency;
            $customer->sms_frequency=$sms_frequency;
            $customer->nlf_time=$item->date_modification;
            $customer->save();
            $item->setSynced();

            echo PHP_EOL;

        }
    }

    public static function getLanguage($subscriber, $user)
    {
        if ($user->config->get('customer_language') !== '1') {
            return null;
        }

        if (!$subscriber['language']) {
            return null;
        }

        return strtolower($subscriber['language']);
    }

    public static function getParameters($subscriber, $user)
    {
        $parameters = [];

        try {
            $language = self::getLanguage($subscriber, $user);

            if ($language) {
                $parameters[] = [
                    'NAME' => 'Język klienta',
                    'VALUE' => $language,
                ];
            }

            return $parameters;
        } catch (\Exception $e) {
            echo "PARAMETERS ERROR" . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            return $parameters;
        }
    }

    public static function processSubscriptionItem($user, $approvalsShopId, $customer, $type='newsletter', $page=0){
        // die ("STOP!!");
        if (isset($customer['email'])){
            echo "processing ".$customer['email'].PHP_EOL;
        }
        if (isset($customer['phone_cellular'])){
            echo "processing ".$customer['phone_cellular'].PHP_EOL;
        }



        echo "processing id ".$customer['client_number'].PHP_EOL;
        if (isset($customer['client_number']) && $customer['client_number']!='' && $customer['client_number']!=0){
            $model=self::find()->where(['user_id'=>$user->id, 'customer_id'=>(string)$customer['client_number'] ])->one();
        }else{
            if ($type=='newsletter'){
                $model=self::find()->where(['user_id'=>$user->id, 'customer_email'=>$customer['email']])->one();
            }
            if ($type=='sms'){
                $model=self::find()->where(['user_id'=>$user->id, 'customer_phone'=>$customer['phone_cellular']])->one();
            }

        }
        if (!$model){
            if ($customer['shops'][$approvalsShopId]['approval']!='y'){ // nawet nie tworzymy bez zgód bo i po co
                echo "no approval ".PHP_EOL;
                return false;
            }
            $model=new self();
            $model->customer_id=$customer['client_number'];

            $model->customer_login=$customer['login'];
            $model->user_id=$user->id;


            $model->shop_id=$approvalsShopId;
        }
        if (isset($customer['email'])){
            $model->customer_email=$customer['email'];
        }
        if (isset($customer['phone_cellular'])){
            $model->customer_phone=$customer['phone_cellular'];
        }

        $model->parameters = serialize(self::getParameters($customer, $user));

        $approval=$customer['shops'][$approvalsShopId]['approval']=='y'?'1':'0';

        // if ('tomasz.lison@meblepumo.pl' == $customer['email']){
        //     var_dump($customer);
        //     var_dump($model->id);
        //     var_dump($approval);
        //     die ("MAMCIE");
        // }

        // if ('k_konopacki@tlen.pl' == $customer['email']){
        //     var_dump($customer['shops']);
        //     die ("WAITTTT");
        // }
        if ($model->{$type.'_approval'}==$approval && $model->date_modification==$model->getCorrectDbDate($customer['shops'][$approvalsShopId]['date_modification'])) // skoro nie ma co zmieniać to po co bazę męczyć
        {
            echo "no changes in approval ".PHP_EOL;
            return true;
        }
        $model->sync_flag=0;
        $model->{$type.'_approval'}= $approval ;
        $model->date_modification=$model->getCorrectDbDate($customer['shops'][$approvalsShopId]['date_modification']);
        $model->page=$page;
        if ($model->save()){
            return true;
        }else{
            print_r($customer);
            print_r($model->date_modification);
            print_r($model->getErrors());
            die("!");
        }
    }

    public function getCorrectDbDate($date): string
    {
        if ($date=='0000-00-00 00:00:00'){
            return $date;
        }
        return date('Y-m-d H:i:s', strtotime($date));
    }
}
