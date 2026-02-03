<?php

namespace app\modules\shoper\models;

use Yii;

/**
 * This is the model class for table "shoper_metafields".
 *
 * @property int $id
 * @property int $shoper_shops_id
 * @property string $object
 * @property string $key
 * @property string $namespace
 * @property string $description
 * @property int $type
 *
 * @property ShoperShops $shoperShops
 */
class ShoperMetafields extends \yii\db\ActiveRecord
{
    const OBJECT='system';
    const NAMESPACE='M2ITSolutions';
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_metafields';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shoper_shops_id', 'object', 'key', 'namespace', 'description', 'type'], 'required'],
            [['shoper_shops_id', 'type'], 'integer'],
            [['object'], 'string', 'max' => 10],
            [['key'], 'string', 'max' => 25],
            [['namespace'], 'string', 'max' => 15],
            [['description'], 'string', 'max' => 250],
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
            'object' => 'Object',
            'key' => 'Key',
            'namespace' => 'Namespace',
            'description' => 'Description',
            'type' => 'Type',
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
