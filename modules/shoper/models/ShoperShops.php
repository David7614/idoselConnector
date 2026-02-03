<?php

namespace app\modules\shoper\models;

use Yii;
use app\modules\shoper\library\App;
use DreamCommerce\ShopAppstoreLib\Resource\Category;
use DreamCommerce\ShopAppstoreLib\Resource\CategoriesTree;

/**
 * This is the model class for table "shoper_shops".
 *
 * @property int $id
 * @property string|null $created_at
 * @property string|null $shop
 * @property string|null $shop_url
 * @property int|null $version
 * @property int|null $installed
 *
 * @property ShoperAccessTokens[] $shoperAccessTokens
 * @property ShoperBillings[] $shoperBillings
 * @property ShoperSubscriptions[] $shoperSubscriptions
 */
class ShoperShops extends \yii\db\ActiveRecord
{
    protected $app;
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_shops';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['created_at'], 'safe'],
            [['version', 'installed'], 'integer'],
            [['shop'], 'string', 'max' => 128],
            [['shop_url'], 'string', 'max' => 512],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'created_at' => 'Created At',
            'shop' => 'Shop',
            'shop_url' => 'Shop Url',
            'version' => 'Version',
            'installed' => 'Installed',
        ];
    }

    /**
     * Gets query for [[ShoperAccessTokens]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperAccessToken()
    {
        return $this->hasOne(ShoperAccessTokens::className(), ['shop_id' => 'id']);
    }

    /**
     * Gets query for [[ShoperBillings]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperBillings()
    {
        return $this->hasMany(ShoperBillings::className(), ['shop_id' => 'id']);
    }

    /**
     * Gets query for [[ShoperSubscriptions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperSubscriptions()
    {
        return $this->hasMany(ShoperSubscriptions::className(), ['shop_id' => 'id']);
    }
    public function getAccess_token(){
        return $this->shoperAccessToken->access_token;
    }
    public function getRefresh_token(){
        return $this->shoperAccessToken->refresh_token;
    }
    public function getExpires(){
        return $this->shoperAccessToken->expires_at;
    }

    public function getUrl(){
        return $this->shop_url;
    }
    
    
}
