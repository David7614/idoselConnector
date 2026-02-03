<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_attributes_options".
 *
 * @property int $id
 * @property int $shoper_attributes_id
 * @property string $value
 *
 * @property ShoperAttributes $shoperAttributes
 */
class ShoperAttributesOptions extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_attributes_options';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shoper_attributes_id', 'value'], 'required'],
            [['shoper_attributes_id'], 'integer'],
            [['value'], 'string', 'max' => 250],
            [['shoper_attributes_id'], 'exist', 'skipOnError' => true, 'targetClass' => ShoperAttributes::className(), 'targetAttribute' => ['shoper_attributes_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'shoper_attributes_id' => 'Shoper Attributes ID',
            'value' => 'Value',
        ];
    }

    /**
     * Gets query for [[ShoperAttributes]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperAttributes()
    {
        return $this->hasOne(ShoperAttributes::className(), ['id' => 'shoper_attributes_id']);
    }
}
