<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_categories".
 *
 * @property int $id
 * @property int $shoper_shops_id
 * @property int $category_id
 * @property int $order
 * @property int $root
 * @property int $in_loyalty
 * @property int $parent_id
 *
 * @property ShoperShops $shoperShops
 * @property ShoperCategoriesLanguage[] $shoperCategoriesLanguages
 */
class ShoperLanguagesList extends \yii\db\ActiveRecord {
	/**
	 * {@inheritdoc}
	 */
	public static function tableName() {
		return 'shoper_languages_list';
	}

	/**
	 * {@inheritdoc}
	 */
	public function rules() {
		return [
			[['shoper_shops_id', 'currency_id', 'active', 'order', 'locale'], 'required'],
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
			'locale' => 'Język',
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
