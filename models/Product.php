<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "product".
 *
 * @property int $PRODUCT_ID
 * @property string $URL
 * @property string $TITLE
 * @property float $PRICE
 * @property string $BRAND
 * @property string $DESCRIPTION
 * @property float $PRICE_BEFORE_DISCOUNT
 * @property float $PRICE_BUY
 * @property string $IMAGE
 * @property string $CATEGORYTEXT
 * @property string $SHOW
 * @property string $PRODUCT_LINE
 * @property string $PARAMETERS
 * @property string $VARIANT
 * @property string $size_id
 * @property int $STOCK
 * @property string $response
 * @property string $params_hash
 * @property int $user_id
 */
class Product extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'product';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // [['URL', 'TITLE', 'PRICE', 'PRICE_BEFORE_DISCOUNT', 'CATEGORYTEXT', 'SHOW', 'PARAMETERS', 'VARIANT', 'STOCK', 'response', 'params_hash'], 'required'],
            [['PRICE', 'PRICE_BEFORE_DISCOUNT', 'PRICE_WHOLESALE'], 'number'],
            [['DESCRIPTION', 'CATEGORYTEXT', 'PARAMETERS', 'VARIANT', 'PRODUCT_LINE', 'SHOW', 'response', 'PRICES', 'size_id'], 'string'],
            [['STOCK', 'user_id'], 'integer'],
            [['TITLE', 'BRAND', 'IMAGE', 'PRODUCT_LINE'], 'string', 'max' => 250],
            [['URL'], 'string', 'max' => 550],
            [['params_hash'], 'string', 'max' => 50],
            [['translation'], 'string', 'max' => 5],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'PRODUCT_ID' => 'Product ID',
            'URL' => 'Url',
            'TITLE' => 'Title',
            'PRICE' => 'Price',
            'BRAND' => 'Brand',
            'DESCRIPTION' => 'Description',
            'PRICE_BEFORE_DISCOUNT' => 'Price Before Discount',
            'IMAGE' => 'Image',
            'CATEGORYTEXT' => 'Categorytext',
            'SHOW' => 'Show',
            'PARAMETERS' => 'Parameters',
            'VARIANT' => 'Variant',
            'size_id' => 'Size Id',
            'STOCK' => 'Stock',
            'PRODUCT_LINE' => 'Product line',
            'response' => 'Response',
            'params_hash' => 'Params Hash',
        ];
    }

    public static function getReplaceFrom(){
        return ['/', ' ', '”', '″', ',', 'ą', 'ę', 'ź', 'ć', 'ż', 'ł', 'ó', 'ń', '%', '(', ')'];
        
    }
    public static function getReplaceTo(){
        return ['-', '-', '-', '-', '-', 'a', 'e', 'z', 'c', 'z', 'l', 'o', 'n', '', '', ''];
    }

    public function getSlug(){
        $slug=str_replace(Product::getReplaceFrom(), Product::getReplaceTo(), $this->TITLE); 
        return str_replace('--', '-', $slug);
    }

    static public function insertProduct($prodChild, $user_id, $force=false){
        $hash=md5(serialize($prodChild->asXML()));
        // var_dump((string) $prodChild->PRODUCT_ID);
        $productModel=Product::find()->where(['PRODUCT_ID'=>$prodChild->PRODUCT_ID, 'user_id' => $user_id])->one();

        if (!$productModel){
            $productModel=new Product();
        }

        if (!$force && $productModel->PRICE!=(string) $prodChild->PRICE){
            $force=true;
        }
        if (!$force && $productModel->PRICE_BEFORE_DISCOUNT!=(string) $prodChild->PRICE_BEFORE_DISCOUNT){
            $force=true;
        }
        if (!$force && $productModel->PRICE_BUY!=(string) $prodChild->PRICE_BUY){
            $force=true;
        }
        if (!$force && $productModel->PRICE_WHOLESALE!=(string) $prodChild->PRICE_WHOLESALE){
            $force=true;
        }

        if ($hash==$productModel->params_hash && !$force){ // chceck if changed since last save
            echo "hash same ".PHP_EOL;
            return true;
        }

        $productModel->PRODUCT_ID = $prodChild->PRODUCT_ID; 
        $productModel->URL=(string) $prodChild->URL;
        $productModel->TITLE=$prodChild->TITLE?(string) $prodChild->TITLE:'-';
        if ($productModel->TITLE==''){
            $productModel->TITLE='undefined';
        }
        $productModel->PRICE=(string) $prodChild->PRICE;
        $productModel->PRICE_WHOLESALE=(string) $prodChild->PRICE_WHOLESALE;
        $productModel->BRAND=(string) $prodChild->BRAND;
        $productModel->DESCRIPTION=(string) $prodChild->DESCRIPTION;
        $productModel->PRICE_BEFORE_DISCOUNT=$prodChild->PRICE_BEFORE_DISCOUNT?(string) $prodChild->PRICE_BEFORE_DISCOUNT:0;
        $productModel->PRICE_BUY = (string) $prodChild->PRICE_BUY?$prodChild->PRICE_BUY:0;
        $productModel->IMAGE=$prodChild->IMAGE?(string) $prodChild->IMAGE:'-';
        $productModel->CATEGORYTEXT=$prodChild->CATEGORYTEXT?(string) $prodChild->CATEGORYTEXT:'-';
        $productModel->SHOW=(string) $prodChild->SHOW;
        $productModel->PRODUCT_LINE = (string) $prodChild->PRODUCT_LINE;
        $productModel->PARAMETERS=serialize($prodChild->PARAMETERS->asXML());
        $productModel->VARIANT=serialize($prodChild->VARIANT->asXML());
        $productModel->STOCK=(int) $prodChild->STOCK;
        $productModel->response=serialize($prodChild->asXML());
        
        $productModel->params_hash=$hash;
        $productModel->user_id = $user_id;

        if ($productModel->save()){
            return true;
        }
        echo 'app\models\Product::'.PHP_EOL;
        var_dump($productModel->getAttributes());
        // var_dump($productModel->CATEGORYTEXT);
        print_r($productModel->getErrors());
        return false;

    }

    public function getVariantsArray(){
        $variantProducts=Product::find()->where(['user_id'=>$this->user_id,'parent_id'=>$this->parent_id])->all();
        $variants = [];
        foreach ($variantProducts as $variantProduct){
            if ($this->parent_id == $variantProduct->PRODUCT_ID){
                echo "same product as variant".PHP_EOL;
                continue;
            }
            $variant=[];
            $variant['PRODUCT_ID']=$variantProduct->PRODUCT_ID;
            $variant['TITLE']=$variantProduct->TITLE;
            $variant['IMAGE']=$variantProduct->IMAGE;
            $variant['PRICE_BEFORE_DISCOUNT']=$variantProduct->PRICE_BEFORE_DISCOUNT;
            $variant['DESCRIPTION']=$variantProduct->DESCRIPTION;

            $variant['PARAMETERS']=[];
            $variantsNames=explode(' \ ', $variantProduct->variants_names);
            $variantsValues=explode(' \ ', $variantProduct->variants_values);
            if (count($variantsNames) != count($variantsValues)){
                $allPArams=unserialize($variantProduct->PARAMETERS);
                // var_dump($allPArams);
                foreach ($allPArams as $par){
                    foreach ($variantsNames as $i=>$nam){
                        if ($nam ==$par['NAME']){
                            $variantsValues[$i]=$par['VALUE'];
                        }
                    }
                }
            }
            var_dump($variantsNames);
            var_dump($variantsValues);
            foreach ($variantsNames as $i=>$v){
                if (!isset($variantsValues[$i])){
                    echo "empty value ".PHP_EOL;
                    continue;
                }
                $param = [];
                $param['NAME']=$v;
                $param['VALUE']=htmlspecialchars($variantsValues[$i]);
                $variant['PARAMETERS'][]=$param;    
            }
            $variant['PRICE']=$variantProduct->PRICE;
            $variant['PRICES']=unserialize($variantProduct->PRICES);
            

            $variant['STOCK']=$variantProduct->STOCK;
            $variant['URL']=$variantProduct->URL;

            
            $variants[]=$variant;
        }
        return $variants;
    }

    public function getXmlEntity($settings){
        echo "getXmlEntity start ".$this->PRODUCT_ID.PHP_EOL;
        if ($settings['aggregate_groups_as_variants'] && $this->parent_id != 0 && $this->parent_id != $this->PRODUCT_ID ){
            return '';
        }
        $products = new \SimpleXMLElement('<PRODUCTS/>');
        $product=$products->addChild('PRODUCT');
        $product->addChild('PRODUCT_ID', $this->PRODUCT_ID);
        $product->addChild('URL', $this->URL);
        $product->addChild('TITLE', $this->TITLE);
        $product->addChild('PRICE', $this->PRICE);
        $product->addChild('PRICE_WHOLESALE', $this->PRICE_WHOLESALE);
        $product->addChild('BRAND', $this->BRAND);
        $product->addChild('STOCK', $this->STOCK);
        $product->addChild('DESCRIPTION', $this->DESCRIPTION);
        $product->addChild('PRICE_BEFORE_DISCOUNT', $this->PRICE_BEFORE_DISCOUNT);
        $product->addChild('PRICE_BUY', $this->PRICE_BUY);
        $prices=$product->addChild('PRICES');
        foreach (unserialize($this->PRICES) as $p ){
            $price=$prices->addChild('CATEGORY');
            foreach ($p as $index=>$value){
                $price->addChild($index, $value);
            }
        }
        $product->addChild('IMAGE', $this->IMAGE);
        $product->addChild('CATEGORYTEXT', htmlspecialchars($this->CATEGORYTEXT));
        if ($this->PRODUCT_LINE == 'brak' && $this->omnibus_price != 0) // dlaczego? bo jak nie ma product line to tam  ma być omnijebus tak se wymyślili !
        {
            $this->PRODUCT_LINE=$this->omnibus_price;
        }
        $product->addChild('PRODUCT_LINE', htmlspecialchars($this->PRODUCT_LINE)); 
        $product->addChild('SHOW', $this->SHOW);

        $parameters=$product->addChild('PARAMETERS');
        echo "sP".PHP_EOL;
        foreach (unserialize($this->PARAMETERS) as $p ){
            // var_dump($p);
            $parameter=$parameters->addChild('PARAMETER');
            foreach ($p as $index=>$value){
                // echo "add ".$index." ".$value;
                $parameter->addChild($index, $value);
            }
        }
        echo "eP".PHP_EOL;
        echo "sV".PHP_EOL;
        if ($settings['aggregate_groups_as_variants'] && $this->parent_id == $this->PRODUCT_ID ){
            echo "DOTHIS".PHP_EOL;
            echo $this->PRODUCT_ID.PHP_EOL;
            // var_dump($this->getVariantsArray());
            foreach ($this->getVariantsArray() as $p){
                echo ".";
                $variant=$product->addChild('VARIANT');
                foreach ($p as $index=>$value){
                    // echo "add ".$index." ".$value;
                    if (is_array($value)){
                        $params=$variant->addChild($index);
                        foreach ($value as $k=>$v){
                            if (!is_array($v)){
                                $param=$params->addChild($k,$v);
                            }else{
                                if ($index=='PARAMETERS'){
                                    $param=$params->addChild('PARAMETER');
                                    foreach ($v as $k1 => $v1){
                                        $param->addChild($k1,$v1);
                                    }

                                }
                                // var_dump($index);
                                // var_dump($k);
                                // var_dump($v);
                                // die();
                            }
                        }
                    }else{
                        $variant->addChild($index, $value);
                    }
                }
            }
            echo "endforach".PHP_EOL;

        }else{
            echo "DOTHAT";
            $variants=unserialize($this->VARIANT);
            // var_dump($variants);
            // var_dump($this->VARIANT);
            // var_dump(unserialize($this->VARIANT));
            if ($variants && count($variants)>0){
                foreach (unserialize($this->VARIANT) as $p ){
                    // var_dump($p);
                    $variant=$product->addChild('VARIANT');
                    foreach ($p as $index=>$value){
                        echo "add ".$index.PHP_EOL;
                        var_dump($value);
                        if (is_array($value)){
                            $params=$variant->addChild('PARAMETERS');
                            foreach ($value as $k=>$v){
                                if (is_array($v)){
                                    foreach ($v as $pramitemAttr => $paramitem){
                                        $param=$params->addChild('PARAMETER');
                                        $param->addChild($pramitemAttr,$paramitem);
                                    }
                                }else{
                                    $param=$params->addChild('PARAMETER');
                                    $param->addChild($k,$v);
                                }
                            }
                        }else{
                            $variant->addChild($index, $value);
                        }
                    }
                }
            }
        }
        echo "eV".PHP_EOL;


        return $product->asXml();
    }


    public function getUser(){
        return $this->hasOne(User::className(), ['id' => 'user_id']);
    }
}
