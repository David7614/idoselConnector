<?php
namespace app\models;

use Yii;
// productPromoRetailPrice
class IdiosellProduct
{
    private $productData;
    private $userId;
    private $productName        = null;
    private $productDescription = null;
    private $_user;
    private $stock;
    private $stockInSales;
    private $_priceSource       = null;

    public function __construct($productData, $user)
    {
        $this->productData = $productData;
        $this->_user       = $user;
        $this->userId      = $this->_user->id;

    }

    private function getPriceSource()
    {
        if ($this->_priceSource !== null) {
            return $this->_priceSource;
        }
        $productPriceSource = $this->productData;
        if ($selectedShopId = $this->_user->config->get('customer_set_shop_id')) {
            foreach ($this->productData['productShopsAttributes'] as $source)
            {
                if ($source['shopId'] == $selectedShopId)
                {
                    $productPriceSource = $source;
                    break;
                }
            }
        }
        $this->_priceSource = $productPriceSource;
        return $this->_priceSource;
    }

    private function getPrice()
    {
        $productPriceSource = $this->getPriceSource();
        if (isset($productPriceSource['productPromoRetailPrice'])){
            return $productPriceSource['productPromoRetailPrice'];
        }
        return $productPriceSource['productRetailPrice'];
    }
    private function getPrices()
    {
        $productPriceSource = $this->getPriceSource();

        $prices         = [];
        $price          = [];
        $price['NAME']  = 'retail';
        $price['PRICE'] = $productPriceSource['productRetailPrice'];
        if (isset($productPriceSource['productPromoRetailPrice'])){
            $price['PRICE'] = $productPriceSource['productPromoRetailPrice'];
        }
        if ($this->_user->config->get('product_price_before_discount')) {
            $price_before_discount          = $productPriceSource['productRetailPrice'];
            $price['PRICE_BEFORE_DISCOUNT'] = $this->getPriceBeforeDiscount();
        }
        $prices[] = $price;

        $price          = [];
        $price['NAME']  = 'Wholesaler';
        $price['PRICE'] = $productPriceSource['productWholesalePrice'];
        $prices[]       = $price;
        // var_dump($prices);
        // die("!!!!");
        return $prices;
    }
    private function getPriceBeforeDiscount()
    {
        $product = $this->getPriceSource();

        if ($this->_user->config->get('product_price_before_discount')) {
            $price_before_discount = $product['productRetailPrice'];
            if (isset($product['productStrikethroughRetailPrice']) && $product['productStrikethroughRetailPrice'] != 0 && $product['productStrikethroughRetailPrice'] > $price_before_discount) {
                $price_before_discount = $product['productStrikethroughRetailPrice'];
            }
            return $price_before_discount;
        }
        return 0;
    }
    private function getPriceBuy()
    {
        $product = $this->getPriceSource();

        if ($this->_user->config->get('product_price_before_discount')) {
            $price_before_discount = $product['productRetailPrice'];
            return $price_before_discount;
        }
        return 0;
    }
    private function getPriceWholesale()
    {
        $productPriceSource = $this->getPriceSource();
        return $productPriceSource['productWholesalePrice'];
    }
    private function getProductUrl($lang = null)
    {
        $product     = $this->productData;
        $productName = $this->getTitle();
        $productSlug = str_replace(Product::getReplaceFrom(), Product::getReplaceTo(), $productName);
        $productSlug = str_replace('--', '-', $productSlug);
        $productSlug = str_replace('--', '-', $productSlug);

        $shopUrl = $this->_user->getShopUrl();

        if ($lang) {
            $productUrl  = $shopUrl . '/product-' . $lang . '-' . $product['productId'] . '-' . $productSlug . '.html';
            //$productUrl  = $this->_user->url . '/product-' . $lang . '-' . $product['productId'] . '-' . $productSlug . '.html';
        } else {
            $productUrl  = $shopUrl . '/product-pol-' . $product['productId'] . '-' . $productSlug . '.html';
            //$productUrl  = $this->_user->url . '/product-pol-' . $product['productId'] . '-' . $productSlug . '.html';
        }

        return $productUrl;
    }

    private function getShopId()
    {
        $selectedShopId = $this->_user->config->get('customer_set_shop_id');
        $shopId=$selectedShopId?$selectedShopId:1;

        return $shopId;
    }

    private function getIndividualTitle()
    {
        // echo "GET INDIVIDUAL TITLE CALL ".PHP_EOL;
        $product            = $this->productData;
        if (!isset($product['productIndividualDescriptionsData'])){
            return null;
        }


        foreach ($product['productIndividualDescriptionsData'] as $langData) {
            if ($langData['langId'] != $this->getLanguage()) {
                continue;
            }
            if ($langData['shopId'] != $this->getShopId()) {
                continue;
            }
            // echo "GET INDIVIDUAL TITLE SET NAME ".PHP_EOL;
            $this->productName        = htmlspecialchars($langData['productName']);
            // $this->productDescription = htmlspecialchars($langData['productDescription']);
        }

        return isset($this->productName) ? $this->productName : null;
    }
    private function getIndividualDescription()
    {
        // echo "GET INDIVIDUAL DESCRIPTION CALL" . PHP_EOL;
        $product            = $this->productData;

        if (!isset($product['productIndividualDescriptionsData'])){
            return null;
        }

        foreach ($product['productIndividualDescriptionsData'] as $langData) {
            if ($langData['langId'] != $this->getLanguage()) {
                continue;
            }
            if ($langData['shopId'] != $this->getShopId()) {
                continue;
            }
            // echo "GET INDIVIDUAL DESC SET DESC ".PHP_EOL;
            // $this->productName        = htmlspecialchars($langData['productName']);
            $this->productDescription = htmlspecialchars($langData['productDescription']);
        }
        return isset($this->productDescription) ? $this->productDescription : null;
    }
    private function getTitle()
    {
        // echo "GET TITLE CALL ".PHP_EOL;
        if ($this->productName) {
            // echo "TITLE EXIST ".PHP_EOL;
            return $this->productName;
        }
        // echo "TITLE NOT EXIST".PHP_EOL;
        $product = $this->productData;


        if ($individualTitle=$this->getIndividualTitle())
        {
            return $individualTitle;
        }

        $product            = $this->productData;

        $productName        = 'empty';
        $productDescription = 'empty';
        foreach ($product['productDescriptionsLangData'] as $langData) {
            if ($langData['langId'] != $this->getLanguage()) {
                continue;
            }
            // echo "GET TITLE SET NAME ".PHP_EOL;
            $this->productName        = htmlspecialchars($langData['productName']);
            // $this->productDescription = htmlspecialchars($langData['productDescription']);
        }
        return isset($this->productName) ? $this->productName : 'undefined';
    }
    private function getDescription()
    {
        // echo "GET DESCRIPTION CALL ".PHP_EOL;
        if ($this->productDescription) {
            return $this->productDescription;
        }
        if ($individualDescription=$this->getIndividualDescription())
        {
            return $individualDescription;
        }
        $product            = $this->productData;
        $productName        = 'empty';
        $productDescription = 'empty';
        foreach ($product['productDescriptionsLangData'] as $langData) {
            if ($langData['langId'] != $this->getLanguage()) {
                continue;
            }
            // echo "GET DESCRIPTION SET NAME ".PHP_EOL;
            // $this->productName        = htmlspecialchars($langData['productName']);
            $this->productDescription = htmlspecialchars($langData['productDescription']);
        }
        return $this->productDescription;
    }

    public function getLanguage()
    {
        $selectedLanguage = $this->_user->config->get('selected_language');
        if (! $selectedLanguage) {
            $selectedLanguage = 'pol';
        }
        return $selectedLanguage;
    }

    public function getBrand()
    {
        $product = $this->productData;
        if ($this->_user->config->get('product_brand')) {
            return htmlspecialchars($product['producerName']);
        }
        return '-';
    }
    public function getImage()
    {
        $product = $this->productData;
        if ($this->_user->config->get('product_image')) {
            if (isset($product['productImages']) && is_array($product['productImages']) && isset($product['productImages'][0])) {
                return $product['productImages'][0]['productImageLargeUrl'];
            }
            // die ("!");
            return $product['productIcon']['productIconLargeUrl'];
        }
        return '-';
    }
    public function getCategoryText()
    {
        $product = $this->productData;
        if (!$this->_user->config->get('product_categorytext')) {
            return '-';
        }
        if (!($menuId=$this->_user->config->get('get_menu_from'))){
            return $product['categoryName'] ? htmlspecialchars($product['categoryName']) : "brak";
        }

        $selectedShopId = $this->_user->config->get('customer_set_shop_id');
        $shopId=$selectedShopId?$selectedShopId:1;
        $selectedLanguage             = $this->_user->config->get('selected_language');
        if (! $selectedLanguage) {
            $selectedLanguage = 'pol';
        }
        // var_dump($product['productMenu']);
        if (isset($product['productMenu'])){
            foreach ($product['productMenu'] as $menuItem){
                if ($menuItem['shopId']==$shopId && $menuItem['menuId']==$menuId){
                    foreach ($menuItem['menuItemDescriptionsLangData'] as $langData){
                        if ($langData['langId'] == $selectedLanguage){
                            return str_replace('\\', '|', $langData['menuItemTextId']);
                        }
                    }

                }
            }

        }
        return "brak";


    }
    public function getIsVisible()
    {
        $product = $this->productData;
        return $product['productIsVisible'] == 'y' ? 'TRUE' : 'FALSE';
    }
    public function getParentId()
    {
        $product = $this->productData;
        if (! isset($product['productVersion'])) {
            return 0;
        }
        if ($product['productVersion']['versionParentId']) {
            return $product['productVersion']['versionParentId'];
        }
        return 0;
    }
    public function getVariantNames()
    {
        $product = $this->productData;
        if (! isset($product['productVersion']['versionGroupNames'])) {
            return '';
        }
        foreach ($product['productVersion']['versionGroupNames']['versionGroupNamesLangData'] as $g) {
            if ($g['langId'] == $this->getLanguage()) {
                return $g['versionGroupName'];
            }
        }
        return '';
    }
    public function getVariantVals()
    {
        $product = $this->productData;
        if (! isset($product['productVersion']['versionNames'])) {
            return '';
        }
        foreach ($product['productVersion']['versionNames']['versionNamesLangData'] as $g) {
            if ($g['langId'] == $this->getLanguage()) {
                return $g['versionName'];
            }
        }
        return '';
    }
    public function getProductLine()
    {
        $product = $this->productData;

        try {
            if ($this->_user->config->get('product_line')) {
                return $product['productSeries']['seriesPanelName'] ?: "brak";
            }
        } catch (\yii\base\ErrorException $e) {
            return "brak";
        }
        return "brak";
    }

    public function getProductDisplayedCode()
    {
        if (!$this->_user->config->get('feature_enabled_product_displayed_code')) {
            return '-';
        }

        if (!$this->_user->config->get('product_displayed_code')) {
            return '-';
        }

        $product = $this->productData;

        if (!$product['productDisplayedCode']) {
            return '-';
        }

        return htmlspecialchars($product['productDisplayedCode']);
    }

    public function getProductNote()
    {
        if (!$this->_user->config->get('feature_enabled_product_note')) {
            return '-';
        }

        if (!$this->_user->config->get('product_note')) {
            return '-';
        }

        $product = $this->productData;

        if (!$product['productNote']) {
            return '-';
        }

        return htmlspecialchars($product['productNote']);
    }

    public function getProductAddingTime()
    {
        $product = $this->productData;

        if (!isset($product['productAddingTime'])) {
            return null;
        }

        return date('Y-m-d', strtotime($product['productAddingTime']));
    }

    public function getProductModificationTime()
    {
        $product = $this->productData;

        if (!isset($product['productModificationTime'])) {
            return null;
        }

        return date('Y-m-d', strtotime($product['productModificationTime']));
    }

    public function getParameters()
    {
        $product = $this->productData;

        $parameters = [];
        try {
            if (isset($product['productParameters'])) {

                foreach ($product['productParameters'] as $paramData) {

                    // print_r($paramData);

                    if (isset($paramData['parameterDescriptionsLangData'])) {
                        foreach ($paramData['parameterDescriptionsLangData'] as $langData) {
                            if ($langData['langId'] == $this->getLanguage()) {
                                $parameterName = htmlspecialchars($langData['parameterName']);
                            }
                        }
                    }

                    // echo " PARAMETER NAME ".$parameterName.PHP_EOL;
                    if (isset($paramData['parameterValues'])) {
                        foreach ($paramData['parameterValues'] as $val) {
                            foreach ($val['parameterValueDescriptionsLangData'] as $langData) {
                                if ($langData['langId'] == $this->getLanguage()) {
                                    $parameterValue = htmlspecialchars($langData['parameterValueName']);
                                }
                            }

                            // echo " PARAMETER VALUE ".$parameterValue.PHP_EOL;
                            $param['NAME']  = htmlspecialchars($parameterName);
                            $param['VALUE'] = htmlspecialchars($parameterValue);
                            $parameters[]   = $param;

                        }
                    }
                }
            }

            $productAddingTime = $this->getProductAddingTime();

            if ($productAddingTime) {
                $parameters[] = [
                    'NAME' => 'Data dodania produktu',
                    'VALUE' => $productAddingTime,
                ];
            }

            $productModificationTime = $this->getProductAddingTime();

            if ($productModificationTime) {
                $parameters[] = [
                    'NAME' => 'Data modyfikacji produktu',
                    'VALUE' => $productModificationTime,
                ];
            }

            $displayedCode = $this->getProductDisplayedCode();

            if ($displayedCode && $displayedCode !== '-') {
                $parameters[] = [
                    'NAME' => 'Kod produktu',
                    'VALUE' => $displayedCode,
                ];
            }

            $note = $this->getProductNote();

            if ($note && $note !== '-') {
                $parameters[] = [
                    'NAME' => 'Adnotacja',
                    'VALUE' => $note,
                ];
            }

            return $parameters;
        } catch (\Exception $e) {
            echo "PARAMETERS ERROR" . PHP_EOL;
            echo $e->getMessage() . PHP_EOL;
            return $parameters;
        }

    }

    public function getVariants()
    {
        $this->stock        = 0;
        $this->stockInSales = 0;
        $product            = $this->productData;
        $variants           = [];
        try {

            if (isset($product['productStocksData']['productStocksQuantities'])) {

                $allowedStockIds = $this->_user->config->getStockIdsArray();

                $i     = 0;
                $stock = 0;
                foreach ($product['productStocksData']['productStocksQuantities'] as $productStockQuantity) {
                    if ((in_array($productStockQuantity['stockId'], $allowedStockIds)) || count($allowedStockIds) == 0) {
                        foreach ($productStockQuantity['productSizesData'] as $stockData) {
                            $variant['PRODUCT_ID']            = $product['productId'];
                            $variant['TITLE']                 = substr($this->getTitle() . htmlspecialchars($stockData['sizePanelName']), 0, 249);
                            $variant['IMAGE']                 = $product['productIcon']['productIconLargeUrl'];
                            $variant['PRICE_BEFORE_DISCOUNT'] = isset($product['productRetailPrice']) ? $product['productRetailPrice'] : 0;
                            $variant['DESCRIPTION']           = $this->getDescription();

                            $variant['PARAMETERS'] = [];

                            $param                   = [];
                            $param['NAME']           = 'Size';
                            $param['VALUE']          = htmlspecialchars($stockData['sizePanelName']);
                            $variant['PARAMETERS'][] = $param;

                            $variant['STOCK']     = $stockData['productSizeQuantity'];
                            $variant['PRICE']     = isset($stockData['productRetailPrice']) ? $stockData['productRetailPrice'] : 0;
                            if (isset($stockData['productPromoRetailPrice'])){
                                $variant['PRICE']     = $stockData['productPromoRetailPrice'];
                            }
                            $variant['PRICE_BUY'] = isset($stockData['productRetailPrice']) ? $stockData['productRetailPrice'] : 0;

                            if ($stockData['productSizeQuantity'] < 0) {
                                $stockData['productSizeQuantity'] = 999;
                            }

                            if ($stockData['productSizeQuantity'] > 0) {
                                $stock += $stockData['productSizeQuantity'];
                                echo "**** add variant quantity " . $stockData['productSizeQuantity'] . PHP_EOL;
                            }
                            $variants[] = $variant;
                        }
                    }
                }
                $this->stock = $stock;
                return $variants;
            }

            if (isset($product['productStocksData']['productSizesDispositions']) && isset($product['productStocksData']['productSizesDispositions']['productSizesDispositionsInSales'])) {

                $i            = 0;
                $stockInSales = 0;
                foreach ($product['productStocksData']['productSizesDispositions']['productSizesDispositionsInSales'] as $productStockQuantityInSales) {
                    // if ((in_array($productStockQuantity->stockId, $allowedStockIds)) || count($allowedStockIds) == 0) {
                    // foreach ($productStockQuantity->productSizesData as $stockData) {

                    if ($productStockQuantityInSales['productSizeQuantityAllStocks'] < 0) {
                        $productStockQuantityInSales['productSizeQuantityAllStocks'] = 999;
                    }

                    if ($productStockQuantityInSales['productSizeQuantityAllStocks'] > 0) {
                        $stockInSales += $productStockQuantityInSales['productSizeQuantityAllStocks'];
                        echo "**** add in sales " . $productStockQuantityInSales['productSizeQuantityAllStocks'] . PHP_EOL;
                    }
                    // }
                    // }
                }
                $this->stockInSales = $stockInSales;
                return $variants;
            }
        } catch (\Exception $e) {
            echo "no variants ";
            echo $e->getMessage() . PHP_EOL;
            return $variants;
        }
    }

    public function shouldForce($force, $productModel, $productUrl, $stockSaved)
    {
        if (!$force && $productModel->URL !== $productUrl) {
            $force = true;
        }

        if (!$force && $productModel->PRICE != $this->getPrice()) {
            $force = true;
        }

        if (!$force && $productModel->PRICE_BEFORE_DISCOUNT != $this->getPriceBeforeDiscount()) {
            $force = true;
        }

        if (!$force && $productModel->PRICE_BUY != $this->getPriceBuy()) {
            $force = true;
        }

        if (!$force && $productModel->PRICE_WHOLESALE != $this->getPriceWholesale()) {
            $force = true;
        }

        if (!$force && $productModel->STOCK != $stockSaved) {
            $force = true;
        }

        if ($productModel->PRICES != '-') {
            if (!$force && unserialize($productModel->PRICES) != $this->getPrices()) {
                $force = true;
            }
        } else {
            $force = true;
        }

        if (!$force && $productModel->parent_id != $this->getParentId()) {
            $force = true;
        }

        return $force;
    }

    public function createSizeVariants(Product $productModel)
    {
        if ($this->_user->config->get('product_aggregate_sizes_as_products') != '1') {
            return null;
        }

        /** @var Product $product */
        $product = $this->productData;

        if (
            !isset($product['productStocksData']) || 
            !isset($product['productStocksData']['productSizesDispositions']) ||
            !isset($product['productStocksData']['productSizesDispositions']['productSizesDispositionsInSales']) ||
            !is_array($product['productStocksData']['productSizesDispositions']['productSizesDispositionsInSales'])
        ) {
            return null;
        }

        $sizeVariants = $product['productStocksData']['productSizesDispositions']['productSizesDispositionsInSales'];

        foreach($sizeVariants as $productVariant) {
            if (!isset($productVariant['sizePanelName'])) {
                continue;
            }

            if ($productVariant['sizeId'] == 'uniw') {
                continue;
            }

            $variantId = $productModel->PRODUCT_ID . '-' . $productVariant['sizePanelName'];

            $variantModel = Product::find()->where(['PRODUCT_ID' => $variantId, 'user_id' => $this->_user->id])->one();

            if (!$variantModel) {
                $variantModel = new Product();
                $variantModel->from_api_page = $this->productData['from_api_page'];
            }

            $variantUrl = $productModel->URL . '?selected_size=' . $productVariant['sizeId'];

            $variantTitle = $productModel->TITLE . ' ' . $productVariant['sizePanelName'];

            $variantStock = $productVariant['productSizeQuantityAllStocks'] ?? 0;

            $variantModel->PRODUCT_ID            = $variantId;
            $variantModel->URL                   = $variantUrl;
            $variantModel->TITLE                 = $variantTitle;
            $variantModel->PRICE                 = $productModel->PRICE;
            $variantModel->PRICE_WHOLESALE       = $productModel->PRICE_WHOLESALE;
            $variantModel->BRAND                 = $productModel->BRAND;
            $variantModel->DESCRIPTION           = $productModel->DESCRIPTION;
            $variantModel->PRICE_BEFORE_DISCOUNT = $productModel->PRICE_BEFORE_DISCOUNT;
            $variantModel->PRICE_BUY             = $productModel->PRICE_BUY;
            $variantModel->PRICES                = $productModel->PRICES;
            $variantModel->IMAGE                 = $productModel->IMAGE;
            $variantModel->CATEGORYTEXT          = $productModel->CATEGORYTEXT;
            $variantModel->SHOW                  = $productModel->SHOW;
            $variantModel->PRODUCT_LINE          = $productModel->PRODUCT_LINE;
            $variantModel->PARAMETERS            = $productModel->PARAMETERS;
            $variantModel->VARIANT               = $productModel->VARIANT;
            $variantModel->STOCK                 = $variantStock;
            $variantModel->response              = '-';
            $variantModel->params_hash           = $productModel->params_hash;
            $variantModel->user_id               = $productModel->user_id;
            $variantModel->translation           = $productModel->translation;

            $variantModel->variants_names  = '';
            $variantModel->variants_values = '';
            if ($productModel->parent_id && $productModel->variants_names && $productModel->variants_values) {
                $variantModel->parent_id       = $productModel->parent_id;
                $variantModel->variants_names  = $productModel->variants_names;
                $variantModel->variants_values = $productModel->variants_values;
            }

            if ($variantModel->save(false)) {
                echo '>--- createSizeVariants - Error' . PHP_EOL;
                continue;
            }
        }
    }

    public function prepareFromApi($force = false)
    {

        // TODO hash
        $hash = md5(serialize($this->productData));

        $productModel = Product::find()->where(['PRODUCT_ID' => (string) $this->productData['productId'], 'user_id' => $this->_user->id])->one();

        if (! $productModel) {
            $productModel                = new Product();
            $productModel->from_api_page = $this->productData['from_api_page'];
        }

        $productUrl = null;

        if ($this->_user->config->get('product_url_lang_variants')) {
            $productUrl = $this->getProductUrl($this->getLanguage());
        } else {
            $productUrl = $this->getProductUrl();
        }

        #if (!$force && $productModel->URL !== $productUrl) {
        #    $force = true;
        #}

        #if (! $force && $productModel->PRICE != $this->getPrice()) {
        #    $force = true;
        #}
        #if (! $force && $productModel->PRICE_BEFORE_DISCOUNT != $this->getPriceBeforeDiscount()) {
        #    $force = true;
        #}
        #if (! $force && $productModel->PRICE_BUY != $this->getPriceBuy()) {
        #    $force = true;
        #}
        #if (! $force && $productModel->PRICE_WHOLESALE != $this->getPriceWholesale()) {
        #    $force = true;
        #}
	#
        $variants    = $this->getVariants();

        $stockSaved = (int) $this->stock;
        // $stockSaved =(int) $this->stockInSales;

        if ($this->_user->config->get('get_quantity_from')) {
            $stockSaved = (int) $this->stockInSales;
            #echo "!";
            #var_dump($this->stock);
            #echo ":";
            #var_dump($this->stockInSales);
            #var_dump($this->productData['productStocksData']);
            #echo "!" . PHP_EOL;
        }

        #if (! $force && $productModel->STOCK != $stockSaved) {
        #    $force = true;
        #}
        #if ($productModel->PRICES != '-') {
        #    if (! $force && unserialize($productModel->PRICES) != $this->getPrices()) {
        #        $force = true;
        #    }
        #} else {
        #    $force = true;
        #}
        #if (! $force && $productModel->parent_id != $this->getParentId()) {
        #    $force = true;
        #}
	
        $shouldForce = $this->shouldForce($force, $productModel, $productUrl, $stockSaved);

        if ($hash == $productModel->params_hash && !$shouldForce) { // chceck if changed since last save
            echo "hash same " . PHP_EOL;
            return true;
        }

        $productModel->PRODUCT_ID            = (string) $this->productData['productId'];

        $productModel->URL = $productUrl;

        $productModel->TITLE                 = substr($this->getTitle(), 0, 249);

        $productModel->PRICE                 = $this->getPrice();
        $productModel->PRICE_WHOLESALE       = $this->getPriceWholesale();
        $productModel->BRAND                 = $this->getBrand();
        $productModel->DESCRIPTION           = $this->getDescription();
        $productModel->PRICE_BEFORE_DISCOUNT = $this->getPriceBeforeDiscount();
        $productModel->PRICE_BUY             = $this->getPriceBuy();
        $productModel->PRICES                = serialize($this->getPrices());
        $productModel->IMAGE                 = $this->getImage();
        $productModel->CATEGORYTEXT          = $this->getCategoryText();
        $productModel->SHOW                  = $this->getIsVisible();
        $productModel->PRODUCT_LINE          = $this->getProductLine();
        // var_dump($this->getParameters());
        // die ("SZTOP SZKOP");
        $productModel->PARAMETERS = serialize($this->getParameters());
        $productModel->VARIANT    = serialize($variants);
        $productModel->STOCK      = $stockSaved;

        $productModel->response = '-';

        $productModel->params_hash = $hash;
        $productModel->user_id     = $this->_user->id;
        $productModel->translation = $this->getLanguage();
        $productModel->variants_names  = '';
        $productModel->variants_values = '';
        if ($this->getParentId() && $this->getVariantNames() && $this->getVariantVals()) {
            $productModel->parent_id       = $this->getParentId();
            $productModel->variants_names  = $this->getVariantNames();
            $productModel->variants_values = $this->getVariantVals();
        }
        $product=$this->productData;

        if ($productModel->save(false)) {
            $this->createSizeVariants($productModel);
            return true;
        }
        var_dump($productModel->getErrors());
        return false;

    }
}
