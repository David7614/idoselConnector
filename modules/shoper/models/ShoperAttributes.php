<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_attributes".
 *
 * @property int $id
 * @property int $attribute_id
 * @property string $name
 * @property string $description
 *
 * @property ShoperAttributesOptions[] $shoperAttributesOptions
 */
class ShoperAttributes extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_attributes';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['attribute_id', 'name', 'description'], 'required'],
            [['attribute_id'], 'integer'],
            [['description'], 'string'],
            [['name'], 'string', 'max' => 250],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'attribute_id' => 'Attribute ID',
            'name' => 'Name',
            'description' => 'Description',
        ];
    }

    /**
     * Gets query for [[ShoperAttributesOptions]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperAttributesOptions()
    {
        return $this->hasMany(ShoperAttributesOptions::className(), ['shoper_attributes_id' => 'id']);
    }
}
