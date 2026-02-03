<?php

namespace app\modules\xml_generator\src;

use SoapClient;

class ProductFeed extends XmlFeed
{
    /**
     * @return bool
     *
     * @throws \Exception
     */
    public function generate(): bool
    {
        $temp = $this->getFile(true, true);
        $file = $this->getFile(true, false);

        if(!$this->isFinished()) {
            $created = $this->createOrAddTempProductXml($temp);
        } else {
            $created = $this->createProductsXml($file, $temp);
        }

        return $created;
    }

    /**
     * @param bool $get_file_path
     * @param bool $temp
     *
     * @return string
     */
    public function getFile(bool $get_file_path = false, bool $temp = false): string
    {
        return parent::getFile($get_file_path, $temp);
    }

    private function createOrAddTempProductXml($temp): bool
    {
        $gate = "https://{$this->_user->username}/api/?gate=products/get/128/soap/wsdl";
        $apiClient = new SoapClient(
            $gate,
            [
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => 'Authorization: Bearer ' . $this->_token
                    ],
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE
            ]
        );

        try {
            //building request
            $request = [
                'authenticate' => [
                    //leaving empty - authenticating using OAuth access token
                    'userLogin' => '',
                    'authenticateKey' => ''
                ],
                'get' => [
                    'params' => [
                        'returnProducts' => 'active',
                    ]
                ]
            ];


            $request['params']['resultsPage'] = $this->_queue->page;
            $response = $apiClient->get($request);
            $this->_queue->setMaxPages($response->resultsNumberPage);
            $products = new \SimpleXMLElement('<PRODUCTS/>');

            if ($this->_queue->page >= $response->resultsNumberPage) {
                return true;
            }
            // print_r($response->results); die;
            if ($response->errors && !empty($response->errors->faultString)) {
                return false;
            }

            $replace_from = ['/', ' ', '”', '″', '|',',', 'ą', 'ę', 'ź', 'ć', 'ż', 'ł', 'ó', 'ń', 'Ą', 'Ę', 'Ż', 'Ć', 'Ź', 'Ł', 'Ó', 'Ń'];
            $replace_to = ['-', '-', '-', '-', '-', '-', 'a', 'e', 'z', 'c', 'z', 'l', 'o', 'n', 'a', 'e', 'z', 'c', 'z', 'l', 'o', 'n'];

            foreach ($response->results as $product) {
                $prodChild = $products->addChild('PRODUCT');
                $prodChild->addChild('PRODUCT_ID', $product->productId);

                $productName = htmlspecialchars($product->productDescriptionsLangData[0]->productName);
                $productSlug = str_replace($replace_from, $replace_to, strtolower($productName));
                $productSlug = str_replace('--', '-', $productSlug);
                $productUrl = 'https://' . $this->_user->username . '/pl/products/' . $productSlug . '-' . $product->productId . '.html';

                $prodChild->addChild('URL', $productUrl);
                $prodChild->addChild('TITLE', $productName);
                $prodChild->addChild('PRICE', $product->productRetailPrice);

                if ($this->_user->config->get('product_brand')) {
                    $prodChild->addChild('BRAND', htmlspecialchars($product->producerName));
                }

                if ($this->_user->config->get('product_description')) {
                    $prodChild->addChild('DESCRIPTION', htmlspecialchars($product->productDescriptionsLangData[0]->productDescription));
                }

                if ($this->_user->config->get('product_price_before_discount')) {
                    $prodChild->addChild('PRICE_BEFORE_DISCOUNT', $product->productRetailPrice);
                }

                if ($this->_user->config->get('product_image')) {
                    $prodChild->addChild('IMAGE', $product->productIcon->productIconLargeUrl);
                }

                if ($this->_user->config->get('product_categorytext')) {
                    $prodChild->addChild('CATEGORYTEXT', $product->categoryName);
                }

                $prodChild->addChild('SHOW', $product->productIsVisible == 'y' ? true : false);

                $stock = 0;

                try {
                    if (isset($product->productParameters)) {
                        $parameters = $prodChild->addChild('PARAMETERS');
                        foreach ($product->productParameters as $paramData) {
                            foreach ($paramData->parameterValues as $paramValues) {
                                $parametr = $parameters->addChild('PARAMETR');
                                $parametr->addChild('NAME', htmlspecialchars($paramData->parameterDescriptionsLangData[1]->parameterName));
                                $parametr->addChild('VALUE', htmlspecialchars($paramValues->parameterValueDescriptionsLangData[1]->parameterValueName));
                            }
                        }
                    }
                } catch (\Exception $e) {
                }

                try {
                    if (isset($product->productStocksData->productStocksQuantities)) {

                        $variant = $prodChild->addChild('VARIANT');
                        $i = 0;
                        foreach ($product->productStocksData->productStocksQuantities[0]->productSizesData as $stockData) {
                            $variantChild = $variant->addChild('PRODUCT');
                            $variantChild->addChild('PRODUCT_ID', $product->productId . '-' . $i);
                            $variantChild->addChild('TITLE', htmlspecialchars($product->productDescriptionsLangData[0]->productName) . htmlspecialchars($stockData->sizePanelName));
                            $parameters = $variantChild->addChild('PARAMETERS');

                            $param = $parameters->addChild('PARAMETR');
                            $param->addChild('NAME', 'Size');
                            $param->addChild('VALUE', htmlspecialchars($stockData->sizePanelName));

                            $variantChild->addChild('STOCK', $stockData->productSizeQuantity);
                            $variantChild->addChild('PRICE', $product->productRetailPrice);

                            if ($stockData->productSizeQuantity > 0) {
                                $stock += $stockData->productSizeQuantity;
                            }
                        }
                    }
                } catch (\Exception $e) {
                }

                if ($this->_user->config->get('product_stock')) {
                    $prodChild->addChild('STOCK', $stock);
                }

                $file_handle = fopen($temp, 'a+');
                fwrite($file_handle, $prodChild->asXml());
                fclose($file_handle);
            }
            $this->_queue->increasePage();
            return true;
        } catch (\Exception $e) {
            echo $e;
            return false;
        }
    }

    /**
     * @param $file
     * @param $temp
     *
     * @return bool
     */
    private function createProductsXml($file, $temp): bool
    {
        $products = new \SimpleXMLElement('<PRODUCTS/>');
        $products->addChild('PRODUCT');
        file_put_contents($file, str_replace('<PRODUCT/>', file_get_contents($temp), $products->asXML()));

        return is_file($file);
    }
}