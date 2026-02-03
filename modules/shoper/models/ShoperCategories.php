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
class ShoperCategories extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'shoper_categories';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['shoper_shops_id', 'category_id', 'order', 'root', 'in_loyalty'], 'required'],
            [['shoper_shops_id', 'category_id', 'order', 'root', 'in_loyalty', 'parent_id'], 'integer'],
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
            'category_id' => 'Category ID',
            'order' => 'Order',
            'root' => 'Root',
            'in_loyalty' => 'In Loyalty',
            'parent_id' => 'Parent ID',
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

    /**
     * Gets query for [[ShoperCategoriesLanguages]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getShoperCategoriesLanguages()
    {
        return $this->hasMany(ShoperCategoriesLanguage::className(), ['shoper_categories_id' => 'id']);
    }

    public function getTranslated($lang='pl_PL'){
        // $trans=ShoperCategoriesLanguage::findOne(['shoper_categories_id' => $this->id, 'translation'=>$lang]);
        // if (!$trans){
        //     $trans=ShoperCategoriesLanguage::findOne(['shoper_categories_id' => $this->id]);
        // }
        // return $trans;
        return ShoperCategoriesLanguage::findOne(['shoper_categories_id' => $this->id, 'translation'=>$lang, 'active'=>1]);
    }

    public function getParent(){
        if ($this->parent_id!=0){
            return ShoperCategories::findOne(['category_id'=>$this->parent_id, 'shoper_shops_id'=>$this->shoper_shops_id]);            
        }
        return null;
    }

    public function crawlPath($path=[]){
        $path[]=$this;
        if ($this->parent){
            $path=$this->parent->crawlPath($path);
        }
        return $path;
    }

    public function getFullPath($lang){
        $langPath=[];
        $path=$this->crawlPath();
        print_r($path);
        $path=array_reverse($path);
        print_r($path);
        foreach ($path as $item){
            if ($item->getTranslated($lang)){
                $langPath[]=$item->getTranslated($lang)->name;
            }else{
                $langPath[]=' ';
            }
        }
        // var_dump($path);
        return implode('|',$langPath);
    }

    public function getChildren(&$items){
        echo "get Children ".PHP_EOL;
        // echo $this->shoper_shops_id.PHP_EOL;
        // echo $this->id.PHP_EOL;
        $list = ShoperCategories::find()->where(['shoper_shops_id' => $this->shoper_shops_id, 'parent_id'=>$this->category_id])->all();
        // print_r($list);
        if ($list){
            foreach ($list as $l){
                if (!$l->getTranslated()){
                    continue;
                }

                echo $l->getTranslated()->name."!!!".PHP_EOL;
                $item = $items->addChild('ITEM');
                $item->addChild('TITLE', htmlspecialchars($l->getTranslated()->name));
                $item->addChild('URL', $l->getTranslated()->permalink);
                $l->getChildren($item);
            }
        }
    }
}
