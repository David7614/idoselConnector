<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_categories".
 *
 * @property int $id
 * @property int $shoper_shops_id
 * @property int $currency_id
 * @property int $name
 * @property float $rate
 * @property int $active
 * @property int $order
 * @property int $default
 * @property float $rate_sync
 * @property datetime $rate_date
 *
 * @property ShoperShops $shoperShops
 * @property ShoperCategoriesLanguage[] $shoperCategoriesLanguages
 */
class ShoperCurrenciesList extends \yii\db\ActiveRecord {
	/**
	 * {@inheritdoc}
	 */
	public static function tableName() {
		return 'shoper_currencies_list';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
			[['shoper_shops_id', 'currency_id', 'name', 'active', 'order'], 'required'],
			[['shoper_shops_id'], 'exist', 'skipOnError' => true, 'targetClass' => ShoperShops::className(), 'targetAttribute' => ['shoper_shops_id' => 'id']],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function attributeLabels() {
		return [
			'id' => 'ID',
			'shoper_shops_id' => 'Shoper Shops ID',
			'currency_id' => 'Currency ID',
			'order' => 'Order',
			'active' => 'Active',
		];
	}

	/**
	 * Gets query for [[ShoperShops]].
	 *
	 * @return \yii\db\ActiveQuery
	 */
	public function getShoperShops() {
		return $this->hasOne(ShoperShops::className(), ['id' => 'shoper_shops_id']);
	}

}
