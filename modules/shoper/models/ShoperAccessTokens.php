<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_access_tokens".
 *
 * @property int $id
 * @property int|null $shop_id
 * @property string|null $expires_at
 * @property string|null $created_at
 * @property string|null $access_token
 * @property string|null $refresh_token
 *
 * @property ShoperShops $shop
 */
class ShoperAccessTokens extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_access_tokens';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shop_id'], 'integer'],
            [['expires_at', 'created_at'], 'safe'],
            [['access_token', 'refresh_token'], 'string', 'max' => 50],
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
            'created_at' => 'Created At',
            'access_token' => 'Access Token',
            'refresh_token' => 'Refresh Token',
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
