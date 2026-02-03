<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_categories_language".
 *
 * @property int $id
 * @property int $shoper_categories_id
 * @property string $translation
 * @property string $name
 * @property string $description
 * @property string $description_bottom
 * @property int $active
 * @property int $isdefault
 * @property string $seo_title
 * @property string $seo_description
 * @property string $seo_keywords
 * @property string $permalink
 *
 * @property ShoperCategories $shoperCategories
 */
class ShoperCategoriesLanguage extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_categories_language';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shoper_categories_id', 'translation', 'name', 'permalink'], 'required'],
            [['id', 'shoper_categories_id', 'active', 'isdefault'], 'integer'],
            [['description', 'description_bottom'], 'string'],
            [['translation'], 'string', 'max' => 5],
            [['name', 'seo_title', 'seo_description', 'seo_keywords', 'permalink'], 'string', 'max' => 250],
            [['shoper_categories_id'], 'exist', 'skipOnError' => true, 'targetClass' => ShoperCategories::className(), 'targetAttribute' => ['shoper_categories_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'shoper_categories_id' => 'Shoper Categories ID',
            'translation' => 'Translation',
            'name' => 'Name',
            'description' => 'Description',
            'description_bottom' => 'Description Bottom',
            'active' => 'Active',
            'isdefault' => 'Isdefault',
            'seo_title' => 'Seo Title',
            'seo_description' => 'Seo Description',
            'seo_keywords' => 'Seo Keywords',
            'permalink' => 'Permalink',
        ];
    }

    /**
     * Gets query for [[ShoperCategories]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperCategories()
    {
        return $this->hasOne(ShoperCategories::className(), ['id' => 'shoper_categories_id']);
    }
}
