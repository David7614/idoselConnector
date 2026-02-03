<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_user_address".
 *
 * @property int $id
 * @property int $shoper_shops_id
 * @property int $address_book_id
 * @property int $user_id
 * @property string $address_name
 * @property string $company_name
 * @property string $pesel
 * @property string $firstname
 * @property string $lastname
 * @property string $street_1
 * @property string $street_2
 * @property string $city
 * @property string $zip_code
 * @property string $state
 * @property string $country
 * @property int $default
 * @property int $shipping_default
 * @property string $phone
 * @property string $sortkey
 * @property string $country_code
 * @property string $tax_identification_number
 *
 * @property ShoperShops $shoperShops
 */
class ShoperUserAddress extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_user_address';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // [['shoper_shops_id', 'address_book_id', 'user_id', 'address_name', 'firstname', 'lastname', 'street_1','city', 'zip_code', 'default', 'shipping_default', 'phone', 'sortkey', 'country_code'], 'required'],
            [['shoper_shops_id', 'address_book_id'], 'required'],
            [['shoper_shops_id', 'address_book_id', 'user_id', 'default', 'shipping_default'], 'integer'],
            [['address_name', 'company_name', 'firstname', 'lastname', 'street_1', 'street_2', 'city', 'sortkey'], 'string', 'max' => 250],
            [['pesel', 'phone'], 'string', 'max' => 25],
            [['zip_code', 'state', 'country'], 'string', 'max' => 15],
            [['country_code'], 'string', 'max' => 5],
            [['tax_identification_number'], 'string', 'max' => 50],
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
            'address_book_id' => 'Address Book ID',
            'user_id' => 'User ID',
            'address_name' => 'Address Name',
            'company_name' => 'Company Name',
            'pesel' => 'Pesel',
            'firstname' => 'Firstname',
            'lastname' => 'Lastname',
            'street_1' => 'Street 1',
            'street_2' => 'Street 2',
            'city' => 'City',
            'zip_code' => 'Zip Code',
            'state' => 'State',
            'country' => 'Country',
            'default' => 'Default',
            'shipping_default' => 'Shipping Default',
            'phone' => 'Phone',
            'sortkey' => 'Sortkey',
            'country_code' => 'Country Code',
            'tax_identification_number' => 'Tax Identification Number',
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
