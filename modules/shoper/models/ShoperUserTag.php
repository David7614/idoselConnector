<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_user_tag".
 *
 * @property int $id
 * @property int $shoper_shops_id
 * @property int $tag_id
 * @property string $name
 * @property int $lang_id
 *
 * @property ShoperShops $shoperShops
 */
class ShoperUserTag extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_user_tag';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shoper_shops_id', 'tag_id', 'name', 'lang_id'], 'required'],
            [['shoper_shops_id', 'tag_id', 'lang_id'], 'integer'],
            [['name'], 'string', 'max' => 250],
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
            'tag_id' => 'Tag ID',
            'name' => 'Name',
            'lang_id' => 'Lang ID',
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
