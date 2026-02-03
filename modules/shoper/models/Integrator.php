<?php
namespace app\modules\shoper\models;

use app\models\IntegrationData;
use app\modules\shoper\library\App;
use app\modules\shoper\models\ShoperAttributes;
use app\modules\shoper\models\ShoperAttributesOptions;
use DreamCommerce\ShopAppstoreLib\Resource\Attribute;
use DreamCommerce\ShopAppstoreLib\Resource\CategoriesTree;
use DreamCommerce\ShopAppstoreLib\Resource\Category;
use DreamCommerce\ShopAppstoreLib\Resource\Order;
use DreamCommerce\ShopAppstoreLib\Resource\OrderProduct;
use DreamCommerce\ShopAppstoreLib\Resource\Producer;
use DreamCommerce\ShopAppstoreLib\Resource\Product as ShoperProduct;
use DreamCommerce\ShopAppstoreLib\Resource\Status;
use DreamCommerce\ShopAppstoreLib\Resource\Subscriber;
use DreamCommerce\ShopAppstoreLib\Resource\UserAddress;
use DreamCommerce\ShopAppstoreLib\Resource\User as ShoperUser;
use DreamCommerce\ShopAppstoreLib\Resource\UserTag;
use Yii;
use \app\models\Customers;
use \app\models\Orders;
use \app\models\Product;
use \app\models\Queue;

class Integrator extends ShoperShops
{

    const XML_PAGE_SIZE = 10000; // 50000

    public static function shoperLog($message, $queueId = 0)
    {
        if ($queueId == '31976') {
            Yii::info($message, 'shoper');
        }
    }

    public function prepareConnection()
    {
        chdir(__DIR__);
        setlocale(LC_ALL, basename(isset($_GET['locale']) ? $_GET['locale'] : 'pl_PL'));
        $config = require '../library/Config.php';
        $app    = new App($config);
        // *** zamiast bootstrap
        $app->shopData = $this;
        if (strtotime($this->expires) - time() < 86400) {
            $app->shopData = $app->refreshToken($app->shopData);
        }
        $app->setClient($app->instantiateClient($app->shopData));
        // ***/ zamiast bootstrap
        return $app;
    }

    public function generateCategoriesTree($client)
    {
        $categoriesResource = new CategoriesTree($client);
        $this->parseCategoriesTree($categoriesResource->get());

    }

    public function parseCategoriesTree($categories)
    {
        foreach ($categories as $res) {
            // print_r($res);
            // continue;
            $parentId = $res->id;
            if (empty($res->children)) {
                continue;
            }
            foreach ($res->children as $child) {
                $cat = ShoperCategories::findOne(['shoper_shops_id' => $this->id, 'category_id' => $child->id]);
                if ($cat && $cat->parent_id != $parentId) {
                    $cat->parent_id = $parentId;
                    $cat->save();
                }

            }
            $this->parseCategoriesTree($res->children);
        }
    }

    public function generateCategory($queue)
    {
        $app = $this->prepareConnection();

        $client             = $app->getClient();
        $categoriesResource = new Category($client);
        if ($queue->page) {
            $categoriesResource->page($queue->page + 1);
            // filter page
        }

        $categoriesResponse = $categoriesResource->get();
        if ($queue->max_page < $categoriesResponse->pages) {
            $queue->max_page = $categoriesResponse->pages;
        }
        $queue->page = $categoriesResponse->page;
        $queue->save();

        echo "gencat " . PHP_EOL;
        var_dump($categoriesResponse);
        foreach ($categoriesResponse as $res) {
            $category = ShoperCategories::findOne(['shoper_shops_id' => $this->id, 'category_id' => $res->category_id]);
            if (! $category) {
                $category = new ShoperCategories(['shoper_shops_id' => $this->id, 'category_id' => $res->category_id]);
            }

            $category->order      = $res->order;
            $category->root       = $res->root;
            $category->in_loyalty = $res->in_loyalty;
            if (! $category->save()) {
                print_r($category->getErrors());
            }

            foreach ($res->translations as $lang => $trans) {
                echo "trans " . $lang . " *****" . PHP_EOL;
                $langCat = ShoperCategoriesLanguage::findOne(['shoper_categories_id' => $category->id, 'translation' => $lang]);
                if (! $langCat) {
                    $langCat = new ShoperCategoriesLanguage(['shoper_categories_id' => $category->id, 'translation' => $lang]);
                }
                $langCat->translation        = $lang;
                $langCat->name               = $trans->name;
                $langCat->description        = $trans->description;
                $langCat->description_bottom = $trans->description_bottom;
                $langCat->active             = $trans->active;
                $langCat->isdefault          = $trans->isdefault;
                $langCat->seo_title          = $trans->seo_title;
                $langCat->seo_description    = mb_substr($trans->seo_description, 0, 250);
                $langCat->seo_keywords       = mb_substr($trans->seo_keywords, 0, 250);
                $langCat->permalink          = $trans->permalink;
                if (! $langCat->save()) {
                    print_r($langCat->getErrors());
                }
            }
            var_dump($res->category_id);
            // die ("!!*");
        }

        if ($queue->max_page <= $queue->page) {
            echo "gen tree";
            $this->generateCategoriesTree($client);

            echo "jeszcze powiązania z tree";

        }
        return true;

        // $queue->save();

        // var_dump($categoriesResponse->count);
        // var_dump($categoriesResponse->pages);
        // var_dump($categoriesResponse->page);
    }

    public function generateAttributes($queue)
    {
        $parameters = $queue->additionalParameters;
        if (! isset($parameters['attributes'])) {
            $parameters['attributes']             = [];
            $parameters['attributes']['page']     = 0;
            $parameters['attributes']['max_page'] = 0;
        }
        $app      = $this->prepareConnection();
        $resource = new Attribute($app->getClient());

        if (isset($parameters['attributes_prev']['page']) && $parameters['attributes']['page'] < $parameters['attributes_prev']['page']) {
            $parameters['attributes']['page'] = $parameters['attributes_prev']['page'] - 1;
        }

        if ($parameters['attributes']['page']) {
            $resource->page($parameters['attributes']['page'] + 1);
            // filter page
        }
        $response = $resource->get();

        if ($parameters['attributes']['max_page'] < $response->pages) {
            $parameters['attributes']['max_page'] = $response->pages;
        }
        $parameters['attributes']['page'] = $response->page;

        $queue->additionalParameters = $parameters;
        $queue->save();
        foreach ($response as $res) {
            echo "attribute id " . $res->attribute_id . PHP_EOL;
            echo "attribute name " . $res->name . PHP_EOL;
            $Attribute = ShoperAttributes::findOne(['shoper_shops_id' => $this->id, 'attribute_id' => $res->attribute_id]);
            if (! $Attribute) {
                $Attribute = new ShoperAttributes(['shoper_shops_id' => $this->id, 'attribute_id' => $res->attribute_id]);
            }
            $Attribute->attribute_id = $res->attribute_id;
            $Attribute->name         = $res->name;
            $Attribute->description  = $res->description ? $res->description : 'no desc';
            if (! $Attribute->save()) {
                print_r($Attribute->getErrors());
            }
            foreach ($res->options as $opt) {
                $option = ShoperAttributesOptions::findOne(['option_id' => $opt->option_id, 'shoper_attributes_id' => $Attribute->id]);
                if (! $option) {
                    $option = new ShoperAttributesOptions(['option_id' => $opt->option_id, 'shoper_attributes_id' => $Attribute->id]);
                }
                $option->value = $opt->value;
                $option->save();
            }
        }

        if ($parameters['attributes']['max_page'] <= $parameters['attributes']['page']) {
            echo "all attributes done";
            return true;
        }
        echo "ATTRIBUTES FIRST";
        return false;
        die("ATTRIBUTES FIRST");
    }

    public function generateSubscriberData($queue)
    {
        $parameters = $queue->additionalParameters;
        if (! isset($parameters['subscriber'])) {
            $parameters['subscriber']             = [];
            $parameters['subscriber']['page']     = 0;
            $parameters['subscriber']['max_page'] = 0;
        }
        $app      = $this->prepareConnection();
        $resource = new Subscriber($app->getClient());
        // if (isset($parameters['subscriber_prev']['page']) && $parameters['subscriber']['page']<$parameters['subscriber_prev']['page']){
        //     $parameters['subscriber']['page']=$parameters['subscriber_prev']['page']-1;
        // }
        if ($parameters['subscriber']['page']) {
            $resource->page($parameters['subscriber']['page'] + 1);
            // filter page
        }

        if (IntegrationData::getDataValue('INITIAL_CUSTOMERS_DONE', $queue->getCurrentUser()->id) && IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)) {
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at' => [
                    '>=' => IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id),
                ],
            ]);
        }
        var_dump($resource);
        $response = $resource->get();

        echo "sub strona " . $parameters['subscriber']['page'] . " of " . $parameters['subscriber']['max_page'] . PHP_EOL;

        if ($parameters['subscriber']['max_page'] < $response->pages) {
            $parameters['subscriber']['max_page'] = $response->pages;
        }
        $parameters['subscriber']['page'] = $response->page;

        $queue->additionalParameters = $parameters;
        $queue->save();

        var_dump($response);

        foreach ($response as $res) {
            $Subscriber = ShoperSubscribers::findOne(['shoper_shops_id' => $this->id, 'subscriber_id' => $res->subscriber_id]);
            if (! $Subscriber) {
                $Subscriber = new ShoperSubscribers(['shoper_shops_id' => $this->id, 'subscriber_id' => $res->subscriber_id]);
            }
            $Subscriber->email     = $res->email;
            $Subscriber->active    = $res->active;
            $Subscriber->used      = 0;
            $Subscriber->dateadd   = $res->dateadd;
            $Subscriber->ipaddress = $res->ipaddress;
            $Subscriber->lang_id   = $res->lang_id;
            $Subscriber->groups    = serialize($res->groups);
            if (! $Subscriber->save()) {
                print_r($Subscriber->getErrors());
            }
        }

        if ($parameters['subscriber']['max_page'] <= $parameters['subscriber']['page']) {
            echo "all subscriber done";
            return true;
        }
        echo "SUBSCRIBERS FIRST";
        return false;
        die("SUBSCRIBERS FIRST");
    }

    public function generateTags($queue)
    {
        $parameters = $queue->additionalParameters;
        if (! isset($parameters['tagslist'])) {
            $parameters['tagslist']             = [];
            $parameters['tagslist']['page']     = 0;
            $parameters['tagslist']['max_page'] = 0;
        }

        $app      = $this->prepareConnection();
        $resource = new UserTag($app->getClient());
        if (isset($parameters['tagslist_prev']['page']) && $parameters['tagslist']['page'] < $parameters['tagslist_prev']['page']) {
            $parameters['tagslist']['page'] = $parameters['tagslist_prev']['page'] - 1;
        }
        if ($parameters['tagslist']['page']) {
            $resource->page($parameters['tagslist']['page'] + 1);
            // filter page
        }
        $response = $resource->get();

        if ($parameters['tagslist']['max_page'] < $response->pages) {
            $parameters['tagslist']['max_page'] = $response->pages;
        }
        $parameters['tagslist']['page'] = $response->page;

        $queue->additionalParameters = $parameters;
        $queue->save();

        foreach ($response as $res) {
            $Tag = ShoperUserTag::findOne(['shoper_shops_id' => $this->id, 'tag_id' => $res->tag_id]);
            if (! $Tag) {
                $Tag = new ShoperUserTag(['shoper_shops_id' => $this->id, 'tag_id' => $res->tag_id]);
            }
            $Tag->name    = $res->name;
            $Tag->lang_id = $res->lang_id;
            if (! $Tag->save()) {
                print_r($Tag->getErrors());
            }
        }

        if ($parameters['tagslist']['max_page'] <= $parameters['tagslist']['page']) {
            echo "all tagslist done";
            return true;
        }
        echo "tagslist FIRST";
        return false;
        die("tagslist FIRST");
    }
    public function generateAddressData($queue)
    {
        Integrator::shoperLog('3.1 Step: Generate address data', $queue->id);

        $parameters = $queue->additionalParameters;
        if (! isset($parameters['address'])) {
            $parameters['address']             = [];
            $parameters['address']['page']     = 0;
            $parameters['address']['max_page'] = 0;
        }

        $app      = $this->prepareConnection();
        $resource = new UserAddress($app->getClient());

        // if (isset($parameters['address_prev']['page']) &&  $parameters['address']['page']<$parameters['address_prev']['page']){
        //     echo "page is ".$parameters['address']['page'].PHP_EOL;
        //     echo "set page to address prev! ".PHP_EOL;
        //     $parameters['address']['page']=$parameters['address_prev']['page']-1;
        // }

        if ($parameters['address']['page']) {
            $resource->page($parameters['address']['page'] + 1);
            // filter page
        }

        if (IntegrationData::getDataValue('INITIAL_CUSTOMERS_DONE', $queue->getCurrentUser()->id) && IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)) {
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at' => [
                    '>=' => IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id),
                ],
            ]);
        }

        $response = $resource->get();

        if ($parameters['address']['max_page'] < $response->pages) {
            $parameters['address']['max_page'] = $response->pages;
        }
        $parameters['address']['page'] = $response->page;

        echo "page: " . $parameters['address']['page'] . " " . PHP_EOL;
        echo "max page: " . $parameters['address']['max_page'] . " " . PHP_EOL;

        $queue->additionalParameters = $parameters;
        $queue->save();

        Integrator::shoperLog('3.1.1 Step: Generate address data', $queue->id);

        foreach ($response as $res) {
            Integrator::shoperLog('3.2 Step: Generate address data', $queue->id);

            $Address = ShoperUserAddress::findOne(['shoper_shops_id' => $this->id, 'address_book_id' => $res->address_book_id]);
            if (! $Address) {
                $Address = new ShoperUserAddress(['shoper_shops_id' => $this->id, 'address_book_id' => $res->address_book_id]);
                Integrator::shoperLog('3.3 Step: Generate address data - create address object', $queue->id);
            }

            Integrator::shoperLog('3.4 Step: Generate address data - After get address object', $queue->id);

            // Integrator::shoperLog('==== data', $queue->id);
            // Integrator::shoperLog(print_r($res, true), $queue->id);
            // Integrator::shoperLog('==== data', $queue->id);

            $Address->user_id                   = $res->user_id;
            $Address->address_name              = $res->address_name;
            $Address->company_name              = $res->company_name;
            $Address->pesel                     = $res->pesel;
            $Address->firstname                 = $res->firstname;
            $Address->lastname                  = $res->lastname;
            $Address->street_1                  = $res->street_1;
            $Address->street_2                  = $res->street_2;
            $Address->city                      = $res->city;
            $Address->zip_code                  = $res->zip_code;
            $Address->state                     = substr($res->state, 0, 15);
            $Address->country                   = $res->country;
            $Address->default                   = $res->default;
            $Address->shipping_default          = $res->shipping_default;
            $Address->phone                     = $res->phone;
            $Address->sortkey                   = $res->sortkey;
            $Address->country_code              = $res->country_code;
            $Address->tax_identification_number = $res->tax_identification_number;

            Integrator::shoperLog('3.5 Step: Generate address data - Before save address', $queue->id);

            if (! $Address->save()) {
                Integrator::shoperLog('3.6 Step: Generate address data - Address save ERROR:', $queue->id);
                Integrator::shoperLog(print_r($Address->getErrors(), true), $queue->id);
                Integrator::shoperLog('3.7 Step: Generate address data - Address save ERROR', $queue->id);
                print_r($Address->getErrors());
            } else {
                Integrator::shoperLog('3.8 Step: Generate address data - Address saved', $queue->id);
                echo "address saved ";
                // echo $Address->user_id." ".$Address->address_book_id.PHP_EOL;
            }

            Integrator::shoperLog('3.9 Step: Generate address data - After save address', $queue->id);
        }

        if ($parameters['address']['max_page'] <= $parameters['address']['page']) {
            Integrator::shoperLog('3.10 Step: Generate address data - All address done', $queue->id);
            echo "all address done";
            return true;
        }

        echo "ADDRESSES FIRST";
        Integrator::shoperLog('3.11 Step: Generate address data - ADDRESSES FIRST', $queue->id);

        return false;
        die("ADDRESSES FIRST");
    }

    public function generateCustomertest($user)
    {
        echo "initial customers done : " . PHP_EOL;
        var_dump(IntegrationData::getDataValue('INITIAL_CUSTOMERS_DONE', $user->id));
        var_dump(IntegrationData::getLastCustomerIntegrationDate($user->id));
        echo "***" . PHP_EOL;
        $app      = $this->prepareConnection();
        $client   = $app->getClient();
        $resource = new ShoperUser($client);
        $resource->limit(1);
        // $resource->filters(['origin'=>0]);
        // $resource->filters(['active'=>'1']);

        if (IntegrationData::getDataValue('INITIAL_CUSTOMERS_DONE', $user->id) && IntegrationData::getLastCustomerIntegrationDate($user->id)) {
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at' => [
                    '>=' => IntegrationData::getLastCustomerIntegrationDate($user->id),
                ],
            ]);
        }
        $resource->order(['user_id']);

        try {
            $response = $resource->get();
            var_dump($response);
        } catch (\DreamCommerce\ShopAppstoreLib\Exception\Exception $ex) {
            die($ex->getMessage());
        }

        echo "test gen";
    }
    public function generateCustomer($queue)
    {
        Integrator::shoperLog('- 2.1 Step: Generate customer', $queue->id);

        $parameters = $queue->additionalParameters;
        if (! isset($parameters['tagslist']['max_page']) || $parameters['tagslist']['max_page'] > $parameters['tagslist']['page']) {
            if (! $this->generateTags($queue)) {
                return false;
            }
        }

        Integrator::shoperLog('- 2.1.1 Step: Generate customer', $queue->id);

        if (! isset($parameters['address']['max_page']) || $parameters['address']['max_page'] > $parameters['address']['page']) {
            if (! $this->generateAddressData($queue)) {
                return false;
            }
        }

        Integrator::shoperLog('- 2.1.2 Step: Generate customer', $queue->id);

        if (! isset($parameters['subscriber']['max_page']) || $parameters['subscriber']['max_page'] > $parameters['subscriber']['page']) {
            if (! $this->generateSubscriberData($queue)) {
                return false;
            }
        }
        $app = $this->prepareConnection();

        Integrator::shoperLog('- 2.1.3 Step: Generate customer', $queue->id);

        $client   = $app->getClient();
        $resource = new ShoperUser($client);
        if ($queue->page) {
            $resource->page($queue->page + 1);
            // filter page
        }

        Integrator::shoperLog('- 2.1.4 Step: Generate customer', $queue->id);

        echo "get res" . PHP_EOL;

        if (IntegrationData::getDataValue('INITIAL_CUSTOMERS_DONE', $queue->getCurrentUser()->id) && IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id)) {
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at' => [
                    '>=' => IntegrationData::getLastCustomerIntegrationDate($queue->getCurrentUser()->id),
                ],
            ]);
        }

        $response = $resource->get();
        if ($queue->max_page < $response->pages) {
            $queue->max_page = $response->pages;
        }
        // var_dump($response);
        $queue->page = $response->page;
        $queue->save();

        foreach ($response as $res) {
            Integrator::shoperLog('- 2.2 Step: Generate customer', $queue->id);

            $Customer = Customers::findOne(['customer_id' => (string) $res->user_id, 'user_id' => $queue->getCurrentUser()->id]);
            if (! $Customer) {
                $Customer = new Customers(['customer_id' => $res->user_id, 'user_id' => $queue->getCurrentUser()->id]);
                Integrator::shoperLog('- 2.3 Step: Generate customer - Created customer object', $queue->id);
            }

            Integrator::shoperLog('- 2.4 Step: Generate customer - After get customer', $queue->id);

            $Customer->verify_email = $res->verify_email;
            $Customer->email        = $res->email;
            $Customer->registration = $res->date_add;
            $Customer->first_name   = $res->firstname;
            $Customer->lastname     = $res->lastname;
            $Customer->page         = $queue->page;
            $AddressData            = ShoperUserAddress::findOne(['shoper_shops_id' => $this->id, 'default' => 1, 'user_id' => $Customer->customer_id]);

            if ($AddressData) {
                $Customer->zip_code = $AddressData->zip_code;
                $Customer->phone    = $AddressData->phone;
            }

            $Customer->newsletter_frequency = 'never';
            $Customer->sms_frequency        = 'never';
            $Customer->nlf_time             = $res->date_add;

            if ($res->newsletter) {
                $Subscriber = ShoperSubscribers::findOne(['shoper_shops_id' => $this->id, 'email' => $Customer->email]);
                if ($Subscriber) {
                    $Customer->newsletter_frequency = $Subscriber->active ? 'every day' : 'never';
                    $Customer->nlf_time             = $Subscriber->dateadd;
                }
            }

            $Customer->data_permission = 'full'; // pewnie z adresów trzeba;
            $Customer->tags            = serialize($res->tags);

            Integrator::shoperLog('- 2.5 Step: Generate customer - Before save customer', $queue->id);

            if (! $Customer->save()) {
                Integrator::shoperLog('- 2.6 Step: Generate customer - save ERROR:', $queue->id);
                Integrator::shoperLog(print_r($Customer->getErrors(), true), $queue->id);
                Integrator::shoperLog('- 2.6 Step: Generate customer - save ERROR', $queue->id);
                print_r($Customer->getErrors());
                die("!!");
            }

            Integrator::shoperLog('- 2.7 Step: Generate customer - After save customer', $queue->id);

            echo "uno";
        }

        if ($queue->max_page <= $queue->page) {
            IntegrationData::setLastCustomerIntegrationDate(date('Y-m-d'), $queue->getCurrentUser()->id);
            IntegrationData::setData('INITIAL_CUSTOMERS_DONE', 1, $queue->getCurrentUser()->id);
        }

        return true;
    }

    public function generateProducers($queue)
    {
        $parameters = $queue->additionalParameters;
        if (! isset($parameters['producers'])) {
            $parameters['producers']             = [];
            $parameters['producers']['page']     = 0;
            $parameters['producers']['max_page'] = 0;
        }
        // print_r($parameters);
        $app      = $this->prepareConnection();
        $resource = new Producer($app->getClient());

        if (isset($parameters['producers_prev']['page']) && $parameters['producers']['page'] < $parameters['producers_prev']['page']) {
            $parameters['producers']['page'] = $parameters['producers_prev']['page'] - 1;
        }

        if ($parameters['producers']['page']) {
            $resource->page($parameters['producers']['page'] + 1);
            // filter page
        }
        $response = $resource->get();

        if ($parameters['producers']['max_page'] < $response->pages) {
            $parameters['producers']['max_page'] = $response->pages;
        }
        $parameters['producers']['page'] = $response->page;
        // print_r($parameters);

        $queue->additionalParameters = $parameters;
        $queue->save();
        foreach ($response as $res) {
            $ShoperProducer = ShoperProducer::findOne(['shoper_shops_id' => $this->id, 'producer_id' => $res->producer_id]);
            if (! $ShoperProducer) {
                $ShoperProducer = new ShoperProducer(['shoper_shops_id' => $this->id, 'producer_id' => $res->producer_id]);
            }
            $ShoperProducer->name = $res->name;
            if (! $ShoperProducer->save()) {
                print_r($ShoperProducer->getErrors());
            }
        }

        if ($parameters['producers']['max_page'] <= $parameters['producers']['page']) {
            echo "all producers done";
            return true;
        }
        echo "PRODUCERS FIRST";
        return false;
        die("PRODUCERS FIRST");
    }

    public function generateProduct($queue)
    {

        if ($queue->page == 0) {
            if (! $this->generateAttributes($queue)) {
                return false;
            }
            if (! $this->generateProducers($queue)) {
                return false;
            }
        }

        $app = $this->prepareConnection();

        $client   = $app->getClient();
        $resource = new ShoperProduct($client);
        if ($queue->page) {
            $resource->page($queue->page + 1);
            // filter page
        }

        if (IntegrationData::getDataValue('INITIAL_PRODUCTS_DONE', $queue->getCurrentUser()->id) && IntegrationData::getDataValue('LAST_PRODUCTS_DONE', $queue->getCurrentUser()->id)) {
            echo "CONSTRIAINT" . PHP_EOL;
            echo IntegrationData::getDataValue('LAST_PRODUCTS_DONE', $queue->getCurrentUser()->id) . PHP_EOL;
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at' => [
                    '>=' => IntegrationData::getDataValue('LAST_PRODUCTS_DONE', $queue->getCurrentUser()->id),
                ],
            ]);
        }

        echo "GENERETE PRODUCT --- " . PHP_EOL;
        $response = $resource->get();
        var_dump($response->pages);
        if ($queue->max_page < $response->pages) {
            $queue->max_page = $response->pages;
        }
        $queue->page = $response->page;
        $queue->save();
        foreach ($response as $res) {
            // var_dump($res);
            foreach ($res->translations as $lang => $trans) {
                if ($res->product_id == '3156') {
                    // die ("GOCIA");
                }
                echo $res->product_id . PHP_EOL;
                echo "!!!";
                echo PHP_EOL;
                $Product = Product::findOne(['user_id' => $queue->getCurrentUser()->id, 'PRODUCT_ID' => $res->product_id, 'translation' => $lang]);
                if (! $Product) {
                    $Product = new Product(['user_id' => $queue->getCurrentUser()->id, 'PRODUCT_ID' => $res->product_id, 'translation' => $lang]);
                }
                $Product->URL   = $trans->permalink;
                $Product->TITLE = $trans->name;
                echo "!!!" . $trans->name . PHP_EOL;
                $Product->PRICE = str_replace(',', '.', $res->stock->comp_promo_price);
                echo "*** producer ***" . PHP_EOL;
                $Producer       = ShoperProducer::findOne(['shoper_shops_id' => $this->id, 'producer_id' => $res->producer_id]);
                $Product->BRAND = $Producer ? $Producer->name : 'brak';
                echo "*** /producer ***" . PHP_EOL;
                $Product->DESCRIPTION           = $trans->description;
                $Product->PRICE_BEFORE_DISCOUNT = str_replace(',', '.', $res->stock->price);
                $Product->PRICE_BUY             = str_replace(',', '.', $res->stock->price_buying);
                if ($res->main_image) {
                    // $queue->getCurrentUser()->getUrl() - ale nie do końća bo nadal kwestia samej ściezki
                    if (isset($res->main_image->unic_name) && $res->main_image->unic_name != '') {
                        $Product->IMAGE = $queue->getCurrentUser()->getUrl() . '/userdata/public/gfx/' . $res->main_image->unic_name . '/' . 'pic' . '.' . $res->main_image->extension;
                    } else {
                        $Product->IMAGE = $queue->getCurrentUser()->getUrl() . '/userdata/public/gfx/' . $res->main_image->gfx_id . '/' . 'pic' . '.' . $res->main_image->extension;
                    }
                }
                $Product->PRODUCT_LINE = 'brak';
                echo "CATEGORY OBJ 1" . PHP_EOL;
                $CategoryObj = ShoperCategories::findOne(['shoper_shops_id' => $this->id, 'category_id' => $res->category_id]);
                if (! $CategoryObj) {
                    echo "no category imported yet for cat " . $res->category_id;
                    // die ("no category imported yet for cat ".$res->category_id);
                    continue;
                }
                echo "exist " . $CategoryObj->id . PHP_EOL;
                $Product->CATEGORYTEXT = $CategoryObj->getFullPath($lang);
                echo "/CATEGORY OBJ" . PHP_EOL;
                $Product->SHOW   = $trans->active;
                $parametersArray = [];
                var_dump($res->attributes);
                echo "*** attributes ***" . PHP_EOL;
                if ($res->attributes) {
                    foreach ($res->attributes as $attributeId => $attributeOptions) {
                        $Attribute = ShoperAttributes::findOne(['shoper_shops_id' => $this->id, 'attribute_id' => $attributeId]);
                        // echo $attributeId;
                        //          var_dump($Attribute);
                        foreach ($attributeOptions as $k => $v) {
                            $Attribute         = ShoperAttributes::findOne(['shoper_shops_id' => $this->id, 'attribute_id' => $k]);
                            $paramArr          = [];
                            $paramArr['NAME']  = $Attribute->name;
                            $paramArr['VALUE'] = $v;
                            $parametersArray[] = $paramArr;
                        }
                    }
                }
                echo "*** /attributes ***" . PHP_EOL;
                $Product->PARAMETERS = serialize($parametersArray);
                $variantArray        = [];
                if ($res->options) {
                    foreach ($res->options as $optionId) {
                        $variant               = [];
                        $variant['PRODUCT_ID'] = $optionId;
                        $variantArray[]        = $variant;
                        // $variantString.='<VARIANT>';
                        // $variantString.='<PRODUCT_ID>'.$optionId.'</PRODUCT_ID>';
                        // $variantString.='<TITLE>'.$optionId.'</TITLE>';
                        // $variantString.='<DESCRIPTION>'.$optionId.'</DESCRIPTION>';
                        // $variantString.='<PARAMETERS>
                        //                              <PARAMETER>
                        //                                        <NAME>Size</NAME>
                        //                                        <VALUE>XXL</VALUE>
                        //                              </PARAMETER>
                        //                              <PARAMETER>
                        //                                        <NAME>EAN</NAME>
                        //                                        <VALUE>467891186861118</VALUE>
                        //                              </PARAMETER>
                        //                    </PARAMETERS>';
                        //             $variantString.='<PRICE>'.$optionId.'</PRICE>';
                        //             $variantString.='<PRICE_BUY>'.$optionId.'</PRICE_BUY>';
                        //             $variantString.='<STOCK>'.$optionId.'</STOCK>';
                        //             $variantString.='<IMAGE>'.$optionId.'</IMAGE>';
                        //             $variantString.='<URL>'.$optionId.'</URL>';
                        // $variantString.='</VARIANT>';
                    }
                }
                $Product->VARIANT     = serialize($variantArray);
                $Product->STOCK       = $res->stock->stock;
                $Product->response    = serialize($res);
                $Product->params_hash = $hash = md5(serialize($res));
                if (! $Product->save()) {
                    print_r($Product->getErrors());
                }
            }
        }

        if ($queue->max_page <= $queue->page) {
            IntegrationData::setData('LAST_PRODUCTS_DONE', date('Y-m-d'), $queue->getCurrentUser()->id);
            IntegrationData::setData('INITIAL_PRODUCTS_DONE', 1, $queue->getCurrentUser()->id);
        }

        return true;
    }

    public function generateProducttest($user)
    {

        $app = $this->prepareConnection();

        $client   = $app->getClient();
        $resource = new ShoperProduct($client);
        $resource->page(8);

        if (IntegrationData::getDataValue('INITIAL_PRODUCTS_DONE', $user->id) && IntegrationData::getDataValue('LAST_PRODUCTS_DONE', $user->id)) {
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at' => [
                    '>=' => IntegrationData::getDataValue('LAST_PRODUCTS_DONE', $user->id),
                    // '>='=>date('Y-m-d')
                ],
            ]);
        }
        $resource->limit(1);
        echo "DATE: " . PHP_EOL;
        echo IntegrationData::getDataValue('LAST_PRODUCTS_DONE', $user->id) . PHP_EOL;

        $response = $resource->get();
        echo "RES NUMBER" . PHP_EOL;
        var_dump($response);
        die("ONLY DISPLAY");
        foreach ($response as $res) {
            // var_dump($res);
            foreach ($res->translations as $lang => $trans) {

                // echo $user->getUrl().'/userdata/public/gfx/'.$res->main_image->gfx_id.'/'.'pic'.'.'.$res->main_image->extension.PHP_EOL;
                // die ("STOP");
                echo "****** " . $lang . " ******* PROD " . $res->product_id . PHP_EOL;
                // $Product=Product::findOne(['user_id'=>$user->id, 'PRODUCT_ID'=>$res->product_id, 'translation'=>$lang]);
                // echo $Product->ID.PHP_EOL;
                // var_dump($Product);
                // print_r($trans);
                echo "!!!";
                $newProductCreated = false;
                $Product           = Product::findOne(['user_id' => $user->id, 'PRODUCT_ID' => $res->product_id, 'translation' => $lang]);
                if (! $Product) {
                    $Product = new Product(['user_id' => $user->id, 'PRODUCT_ID' => $res->product_id, 'translation' => $lang]);
                    $Product->save();
                    $newProductCreated = true;
                }
                $Product->URL   = $trans->permalink;
                $Product->TITLE = $trans->name;
                echo "!!!" . $trans->name . PHP_EOL;
                $Product->PRICE = str_replace(',', '.', $res->stock->comp_promo_price);
                echo "*** producer ***" . PHP_EOL;
                $Producer       = ShoperProducer::findOne(['shoper_shops_id' => $this->id, 'producer_id' => $res->producer_id]);
                $Product->BRAND = $Producer ? $Producer->name : 'brak';
                echo "*** /producer ***" . PHP_EOL;
                $Product->DESCRIPTION           = $trans->description;
                $Product->PRICE_BEFORE_DISCOUNT = str_replace(',', '.', $res->stock->price);
                $Product->PRICE_BUY             = str_replace(',', '.', $res->stock->price_buying);
                if ($res->main_image) {
                    $Product->IMAGE = $this->shop_url . '/userdata/public/gfx/' . $res->main_image->gfx_id . '/' . 'pic' . '.' . $res->main_image->extension;
                }
                $Product->PRODUCT_LINE = 'brak';
                echo "CATEGORY OBJ 2" . PHP_EOL;
                $CategoryObj = ShoperCategories::findOne(['shoper_shops_id' => $this->id, 'category_id' => $res->category_id]);
                if (! $CategoryObj) {
                    die("no category imported yet");
                }
                $Product->CATEGORYTEXT = $CategoryObj->getFullPath($lang);
                echo "/CATEGORY OBJ" . PHP_EOL;
                $Product->SHOW   = $trans->active;
                $parametersArray = [];
                var_dump($res->attributes);
                echo "*** attributes ***" . PHP_EOL;
                if ($res->attributes) {
                    foreach ($res->attributes as $attributeId => $attributeOptions) {
                        $Attribute = ShoperAttributes::findOne(['shoper_shops_id' => $this->id, 'attribute_id' => $attributeId]);
                        // echo $attributeId;
                        //          var_dump($Attribute);
                        foreach ($attributeOptions as $k => $v) {
                            $Attribute         = ShoperAttributes::findOne(['shoper_shops_id' => $this->id, 'attribute_id' => $k]);
                            $paramArr          = [];
                            $paramArr['NAME']  = $Attribute->name;
                            $paramArr['VALUE'] = $v;
                            $parametersArray[] = $paramArr;
                        }
                    }
                }
                echo "*** /attributes ***" . PHP_EOL;
                $Product->PARAMETERS = serialize($parametersArray);
                $variantArray        = [];
                if ($res->options) {
                    foreach ($res->options as $optionId) {
                        $variant               = [];
                        $variant['PRODUCT_ID'] = $optionId;
                        $variantArray[]        = $variant;
                        // $variantString.='<VARIANT>';
                        // $variantString.='<PRODUCT_ID>'.$optionId.'</PRODUCT_ID>';
                        // $variantString.='<TITLE>'.$optionId.'</TITLE>';
                        // $variantString.='<DESCRIPTION>'.$optionId.'</DESCRIPTION>';
                        // $variantString.='<PARAMETERS>
                        //                              <PARAMETER>
                        //                                        <NAME>Size</NAME>
                        //                                        <VALUE>XXL</VALUE>
                        //                              </PARAMETER>
                        //                              <PARAMETER>
                        //                                        <NAME>EAN</NAME>
                        //                                        <VALUE>467891186861118</VALUE>
                        //                              </PARAMETER>
                        //                    </PARAMETERS>';
                        //             $variantString.='<PRICE>'.$optionId.'</PRICE>';
                        //             $variantString.='<PRICE_BUY>'.$optionId.'</PRICE_BUY>';
                        //             $variantString.='<STOCK>'.$optionId.'</STOCK>';
                        //             $variantString.='<IMAGE>'.$optionId.'</IMAGE>';
                        //             $variantString.='<URL>'.$optionId.'</URL>';
                        // $variantString.='</VARIANT>';
                    }
                }
                $Product->VARIANT     = serialize($variantArray);
                $Product->STOCK       = $res->stock->stock;
                $Product->response    = serialize($res);
                $Product->params_hash = $hash = md5(serialize($res));
                if ($newProductCreated) {
                    $checkProduct = Product::find()->where(['user_id' => $user->id, 'PRODUCT_ID' => $res->product_id, 'translation' => $lang])->andWhere(['!=', 'PRODUCT_ID', $Product->ID])->one();
                    if ($checkProduct) {
                                  // $Product->delete();
                        continue; // nie ma zapisywania po raz drugi na raz
                    }
                }
                if (! $Product->save()) {
                    print_r($Product->getErrors());
                }
            }
            die("ONLY 1");
        }

        die();

        return true;
    }

    public function generateStatuses($queue)
    {
        $parameters = $queue->additionalParameters;
        if (! isset($parameters['statuses'])) {
            $parameters['statuses']             = [];
            $parameters['statuses']['page']     = 0;
            $parameters['statuses']['max_page'] = 0;
        }
        // print_r($parameters);
        $app      = $this->prepareConnection();
        $resource = new Status($app->getClient());

        if (isset($parameters['statuses_prev']['page']) && $parameters['statuses']['page'] < $parameters['statuses_prev']['page']) {
            $parameters['statuses']['page'] = $parameters['statuses_prev']['page'] - 1;
        }

        if ($parameters['statuses']['page']) {
            $resource->page($parameters['statuses']['page'] + 1);
            // filter page
        }
        $response = $resource->get();

        if ($parameters['statuses']['max_page'] < $response->pages) {
            $parameters['statuses']['max_page'] = $response->pages;
        }
        $parameters['statuses']['page'] = $response->page;
        // print_r($parameters);

        $queue->additionalParameters = $parameters;
        $queue->save();
        foreach ($response as $res) {
            foreach ($res->translations as $lang => $trans) {
                $ShoperStatus = ShoperStatus::findOne(['shoper_shops_id' => $this->id, 'status_id' => $res->status_id, 'translation' => $lang]);
                if (! $ShoperStatus) {
                    $ShoperStatus = new ShoperStatus(['shoper_shops_id' => $this->id, 'status_id' => $res->status_id, 'translation' => $lang]);
                }
                $ShoperStatus->active  = $res->active;
                $ShoperStatus->default = $res->default;
                $ShoperStatus->type    = $res->type;
                $ShoperStatus->order   = $res->order;
                $ShoperStatus->name    = $trans->name;
                $ShoperStatus->message = $trans->message ? $trans->message : 'not set';
                if (! $ShoperStatus->save()) {
                    print_r($ShoperStatus->getErrors());
                }
            }

        }

        if ($parameters['statuses']['max_page'] <= $parameters['statuses']['page']) {
            echo "all statuses done";
            return true;
        }
        return false;
        die("statuses FIRST");
    }

    public function generateOrderTest($user, $queue)
    {
        return $this->generateOrder($queue);
    }
    public function generateOrder($queue)
    {
        Integrator::shoperLog('- 2.1 Step: Generate order', $queue->id);
        $this->checkOrdersDateFrom($queue);

        if ($queue->page == 0) {
            if (! $this->generateStatuses($queue)) {
                return false;
            }
            // $this->generateOrdersPositions($queue);
        }

        Integrator::shoperLog('- 2.1.1 Step: Generate order', $queue->id);

        $app = $this->prepareConnection();

        $client   = $app->getClient();
        $resource = new Order($client);
        if ($queue->page) {
            $resource->page($queue->page + 1);
            // filter page
        }

        Integrator::shoperLog('- 2.1.2 Step: Generate order', $queue->id);

        if (IntegrationData::getDataValue('INITIAL_ORDERS_DONE', $queue->getCurrentUser()->id) && IntegrationData::getDataValue('LAST_ORDERS_DONE', $queue->getCurrentUser()->id)) {
            $resource->filters([
                // 'origin' => [0,1,2],
                'updated_at' => [
                    '>=' => IntegrationData::getDataValue('LAST_ORDERS_DONE', $queue->getCurrentUser()->id),
                ],
                // 'created_at' => [
                //     '>=' => IntegrationData::getDataValue('LAST_ORDERS_DONE', $queue->getCurrentUser()->id),
                // ],
            ]);
        }
        // if ($queue->id == '736414'){
        //     var_dump($resource);
        //     die("RES");
        // }
        $response = $resource->get();
        if ($queue->max_page < $response->pages) {
            $queue->max_page = $response->pages;
        }

        Integrator::shoperLog('- 2.1.3 Step: Generate order', $queue->id);

        $queue->page = $response->page;
        $queue->save();

        Integrator::shoperLog('- 2.1.4 Step: Generate order', $queue->id);

        Integrator::shoperLog('-------------------------------------------', $queue->id);
        Integrator::shoperLog(print_r(sizeof($response), true), $queue->id);
        Integrator::shoperLog('-------------------------------------------', $queue->id);

        if (! count($response)) {

        }
        $grabOrdersdateFrom = $queue->getCurrentUser()->getConfig()->getOrdersDateFrom();
        foreach ($response as $res) {
            print_r($res);
            Integrator::shoperLog('2.2 Step: Before get order', $queue->id);

            if ($grabOrdersdateFrom) {
                if (strtotime($res->date) < strtotime($grabOrdersdateFrom)) {
                    Integrator::shoperLog('2.2.1 Step: Order date is older than date from', $queue->id);
                    continue;
                }
            }



            $Order = Orders::find()->where(['order_id' => $res->order_id])
                ->andWhere(['user_id' => $queue->getCurrentUser()->id])
                ->one();

            // $Order = Orders::findOne(['user_id' => $queue->getCurrentUser()->id, 'order_id' => $res->order_id]);
            if (! $Order) {
                // Integrator::shoperLog('teeeeeeeeeeeeeeeeeeeeeeest', $queue->id);
                Integrator::shoperLog('2.2.1 Step: order_id: ' . $res->order_id, $queue->id);
                Integrator::shoperLog('2.2.2 Step: user_id: ' . $queue->getCurrentUser()->id, $queue->id);
                Integrator::shoperLog('2.2.3 Step: order', $queue->id);
                Integrator::shoperLog(print_r(! $Order, true), $queue->id);
                Integrator::shoperLog(var_dump($Order), $queue->id);

                Integrator::shoperLog('2.3 Step: new order object', $queue->id);
                $Order = new Orders(['user_id' => $queue->getCurrentUser()->id, 'order_id' => $res->order_id]);
                Integrator::shoperLog('2.4 Step: order object created', $queue->id);
            }

            Integrator::shoperLog('2.5 Step: After get order - id ' . $Order->id, $queue->id);

            $Order->customer_id = $res->user_id;
            $Order->created_on  = $res->date;
            $Order->finished_on = $res->date;

            echo 'Response status: ' . $res->status_id . PHP_EOL;

            $ShoperStatus        = ShoperStatus::findOne(['shoper_shops_id' => $this->id, 'status_id' => $res->status_id]);
            $Order->status       = $ShoperStatus->sambaStatus;
            $Order->email        = $res->email;
            $Order->phone        = $res->delivery_address->phone;
            $Order->zip_code     = $res->delivery_address->postcode;
            $Order->country_code = $res->delivery_address->country_code;
            $Order->page         = $queue->page + 1;

            $orderProduct = new OrderProduct($client);
            $orderProduct->filters(['order_id' => $res->order_id]);
            $Products = $orderProduct->get();
            $items    = [];

            foreach ($Products as $orderItem) {
                $item['product_id'] = $orderItem->product_id;
                $item['amount']     = $orderItem->quantity;
                $item['price']      = $orderItem->quantity * $orderItem->price;
                $items[]            = $item;
            }

            $Order->order_positions = serialize($items);

            Integrator::shoperLog('2.6 Step: Before order save', $queue->id);

            if (! $Order->save()) {
                Integrator::shoperLog('2.7 Step: Order save error:', $queue->id);
                Integrator::shoperLog(print_r($Order->getErrors(), true), $queue->id);
                Integrator::shoperLog('2.8 Step: Order save error', $queue->id);
                print_r($Order->getErrors());
            }

            Integrator::shoperLog('2.9 Step: After order save', $queue->id);
        }

        Integrator::shoperLog('- 2.10 Step: Generate order done', $queue->id);

        if ($queue->max_page <= $queue->page) {
            IntegrationData::setData('LAST_ORDERS_DONE', date('Y-m-d'), $queue->getCurrentUser()->id);
            IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $queue->getCurrentUser()->id);
        }

        return true;
    }

    public function prepareFile($queue)
    {
        echo "file preparing" . PHP_EOL;
        echo $queue->integration_type . PHP_EOL;
        switch ($queue->integration_type) {
            case 'product':
                return $this->prepareProductsFile($queue);
                break;
            case 'category':
                return $this->prepareCategoriesFile($queue);
                break;
            case 'customer':

                return $this->prepareCustomersFile($queue);
                break;
            case 'order':
                return $this->prepareOrdersFile($queue);
                break;
            case 'tags':
                return true; // no tags file
                break;
        }
        return false;
    }

    public function prepareDiversedFile($queue)
    {
        echo "file preparing diversed";
        switch ($queue->integration_type) {
            case 'product':
                return $this->prepareProductsFile($queue);
                break;
            case 'category':
                return $this->prepareCategoriesFile($queue);
                break;
            case 'customer':
                if (! $this->isFinished($queue)) {
                    return $this->prepareCustomersDiversedFile($queue);
                } else {
                    return $this->createCustomerXml();
                }
                break;
            case 'order':
                return $this->prepareOrdersFile($queue);
                break;
            case 'tags':
                return true; // no tags file
                break;
        }
        return false;
    }

    public function isFinished($queue)
    {
        if ($queue->max_page == 0 && $queue->page == 0) {
            return false;
        }

        return $queue->page >= $queue->max_page;
    }

    public function prepareProductsFile($queue)
    {

        $selectedLanguage = $queue->getCurrentUser()->config->get('selected_language');

        $products = new \SimpleXMLElement('<PRODUCTS/>');
        if (isset($selectedLanguage) && $selectedLanguage != '') {
            echo "---has lang" . PHP_EOL;
            $productsQueryList = Product::find()->where(['user_id' => $queue->getCurrentUser()->id, 'translation' => $selectedLanguage])->all();
        } else {
            echo "---no lang" . PHP_EOL;
            $productsQueryList = Product::find()->where(['user_id' => $queue->getCurrentUser()->id])->all();
        }

        foreach ($productsQueryList as $product) {
            echo ".";
            $prodChild = $products->addChild('PRODUCT');
            $prodChild->addChild('SHOW', $product->SHOW ? 'TRUE' : 'FALSE');
            $prodChild->addChild('PRODUCT_ID', $product->PRODUCT_ID);
            $prodChild->addChild('URL', $product->URL);
            $prodChild->addChild('TITLE', htmlspecialchars($product->TITLE));
            // var_dump($product->PRICE);
            $prodChild->addChild('PRICE', str_replace(',', '.', $product->PRICE));
            $prodChild->addChild('BRAND', htmlspecialchars($product->BRAND));
            $prodChild->addChild('DESCRIPTION', htmlspecialchars($product->DESCRIPTION));
            $prodChild->addChild('PRICE_BEFORE_DISCOUNT', $product->PRICE_BEFORE_DISCOUNT);
            $prodChild->addChild('PRICE_BUY', str_replace(',', '.', $product->PRICE_BUY));
            $prodChild->addChild('IMAGE', $product->IMAGE);
            $prodChild->addChild('PRODUCT_LINE', htmlspecialchars($product->PRODUCT_LINE));
            $prodChild->addChild('CATEGORYTEXT', htmlspecialchars($product->CATEGORYTEXT));

            $parameters = $prodChild->addChild('PARAMETERS');
            foreach (unserialize($product->PARAMETERS) as $param) {
                $parameter = $parameters->addChild('PARAMETER');
                $parameter->addChild('NAME', $param['NAME']);
                $parameter->addChild('VALUE', htmlspecialchars($param['VALUE']));
            }
            // var_dump(unserialize($product->VARIANT));
            // die ("!!");
            foreach (unserialize($product->VARIANT) as $variant) {
                $productVariant = $prodChild->addChild('VARIANT');
                foreach ($variant as $k => $v) {
                    $productVariant->addChild($k, $v);
                }
            }
            $prodChild->addChild('STOCK', $product->STOCK);
        }
        // print_r($products->asXML());
        if (file_put_contents($this->getProductsFile(), $products->asXML())) {
            return true;
        }

        return false;
    }
    public function prepareCategoriesFile($queue)
    {
        echo "prepareCategoriesFile" . PHP_EOL;
        $categories = new \SimpleXMLElement('<CATEGORY/>');
        foreach (ShoperCategories::find()->where(['shoper_shops_id' => $this->id])->andWhere(['or', ['parent_id' => 0], ['parent_id' => null]])->all() as $category) {
            if (! $category->getTranslated()) {
                continue;
            }
            $item = $categories->addChild('ITEM');
            $item->addChild('TITLE', htmlspecialchars($category->getTranslated()->name));
            $item->addChild('URL', $category->getTranslated()->permalink);
            $category->getChildren($item);
        }
        echo "PUT IN FILE" . PHP_EOL;
        if (file_put_contents($this->getCategoriesFile(), $categories->asXML())) {
            return true;
        }

        return false;
    }

    public function prepareCustomersFile($queue)
    {
        $usedEmails = [];
        $customers  = new \SimpleXMLElement('<CUSTOMERS/>');
        echo "start foreach " . PHP_EOL;
        foreach (Customers::find()->where(['user_id' => $queue->getCurrentUser()->id])->all() as $customer) {
            if (Queue::isDisallowedEmail($customer->email)) {
                // ommit allegro etc
                continue;
            }
            if ($customer->newsletter_frequency == 'never' && $customer->sms_frequency == 'never') {
                // ommit customers with no agreement
                continue;
            }
            $usedEmails[] = $customer->email;
            // var_dump($customer);
            $item = $customers->addChild('CUSTOMER');
            $item->addChild('CUSTOMER_ID', $customer->customer_id);
            // echo htmlspecialchars($customer->email);
            $item->addChild('EMAIL', htmlspecialchars($customer->email));
            $item->addChild('REGISTRATION', $this->getCorrectSambaDate($customer->registration));
            $item->addChild('FIRST_NAME', htmlspecialchars($customer->first_name));
            $item->addChild('LAST_NAME', htmlspecialchars($customer->lastname));
            $item->addChild('NEWSLETTER_FREQUENCY', $customer->newsletter_frequency);
            if ($customer->zip_code) {
                $item->addChild('ZIP_CODE', $customer->zip_code);
            }
            if ($customer->phone) {
                $number = str_replace(['+', '-'], '', filter_var($customer->phone, FILTER_SANITIZE_NUMBER_INT));
                $number = preg_replace("/[^0-9]/", "", $number);
                $item->addChild('PHONE', $number);
            }
            $item->addChild('SMS_FREQUENCY', $customer->sms_frequency);
            $item->addChild('DATA_PERMISSION', $customer->data_permission);
            $item->addChild('NLF_TIME', $this->getCorrectSambaDate($customer->nlf_time));

            $paramsChild = $item->addChild('PARAMETERS');
            $lastName    = $paramsChild->addChild('PARAMETER');
            $lastName->addChild('NAME', 'LAST_NAME');
            $lastName->addChild('VALUE', htmlspecialchars($customer->lastname));

            $firstName = $paramsChild->addChild('PARAMETER');
            $firstName->addChild('NAME', 'FIRST_NAME');
            $firstName->addChild('VALUE', htmlspecialchars($customer->first_name));

            $tags = unserialize($customer->tags);
            if ($tags) {
                foreach ($tags as $tag) {
                    $Tag        = ShoperUserTag::findOne(['shoper_shops_id' => $this->id, 'tag_id' => $tag]);
                    $paramChild = $paramsChild->addChild('PARAMETER');
                    $paramChild->addChild('NAME', $Tag->name);
                    $paramChild->addChild('VALUE', '1');
                }
            }

        }
        echo "end foreach " . PHP_EOL;
/*
foreach (ShoperSubscribers::find()->where(['shoper_shops_id'=>$this->id, 'active' => 1])->all() as $subscriber) {
if (in_array($subscriber, $usedEmails)){
continue;
}
$item = $customers->addChild('CUSTOMER');
$item->addChild('CUSTOMER_ID', 'SUBSCRIBER'.$subscriber->subscriber_id);
$item->addChild('EMAIL', htmlspecialchars($subscriber->email));
$item->addChild('REGISTRATION', $this->getCorrectSambaDate($subscriber->dateadd));
$item->addChild('NEWSLETTER_FREQUENCY', 'every day');
$item->addChild('NLF_TIME', $this->getCorrectSambaDate($subscriber->dateadd));
}*/

        echo "put tp " . $this->getCustomersFile() . PHP_EOL;
        if (file_put_contents($this->getCustomersFile(), $customers->asXML())) {
            return true;
        }

        return false;
    }

    private function createCustomerXml()
    {
        $file = $this->getCustomersFile();
        $temp = $this->getCustomersTempFile();

        $customer = new \SimpleXMLElement('<CUSTOMERS/>');
        $customer->addChild('CUSTOMER');
        file_put_contents($file, str_replace('<CUSTOMER/>', file_get_contents($temp), $customer->asXML()));
        file_put_contents($temp, '');
        return is_file($file) ? 10 : 0;
    }

    public function prepareCustomersDiversedFile($queue)
    {

        $temp = $this->getCustomersTempFile();

        $integrationDataCurrentPage = $queue->page;
        $integrationDataMaxPage     = $queue->max_page;
        $page_size                  = self::XML_PAGE_SIZE;

        $customers_query   = Customers::find()->where(['user_id' => $queue->getCurrentUser()->id]);
        $subscribers_query = ShoperSubscribers::find()->where(['shoper_shops_id' => $this->id, 'active' => 1, 'used' => 0]);

        $page            = $integrationDataCurrentPage;
        $customerPages   = ceil($customers_query->count() / $page_size);
        $subscriberPages = ceil($subscribers_query->count() / $page_size);
        if ($integrationDataMaxPage == 0) {
            $pages = $customerPages;
            $pages += $subscriberPages + 1;
            // $pages+=1; // to fit everything else
            $queue->max_page        = $pages;
            $integrationDataMaxPage = $pages;
            $queue->page            = $page;
            $queue->save();
        }

        echo PHP_EOL."customer pages " . $customerPages . PHP_EOL;
        echo "subscriber pages " . $subscriberPages . PHP_EOL;
        echo " PAGE " . $page . " of " . $integrationDataMaxPage . PHP_EOL;
        // die("!");
        $usedEmails = [];
        $customers  = new \SimpleXMLElement('<CUSTOMERS/>');
        echo "!";
        if ($page <= $customerPages) {
            $customers_db = $customers_query->limit($page_size)->offset(($page) * $page_size)->all();
            foreach ($customers_db as $customer) {
                echo ".";
                if (Queue::isDisallowedEmail($customer->email)) {
                    // ommit allegro etc
                    continue;
                }
                $usedEmails[] = $customer->email;
                // var_dump($customer);
                $item = $customers->addChild('CUSTOMER');
                $item->addChild('CUSTOMER_ID', $customer->customer_id);
                // echo htmlspecialchars($customer->email);
                $item->addChild('EMAIL', htmlspecialchars($customer->email));
                $item->addChild('REGISTRATION', $this->getCorrectSambaDate($customer->registration));
                $item->addChild('FIRST_NAME', htmlspecialchars($customer->first_name));
                $item->addChild('LAST_NAME', htmlspecialchars($customer->lastname));
                $item->addChild('NEWSLETTER_FREQUENCY', $customer->newsletter_frequency);
                if ($customer->zip_code) {
                    $item->addChild('ZIP_CODE', $customer->zip_code);
                }
                if ($customer->phone) {
                    $number = str_replace(['+', '-'], '', filter_var($customer->phone, FILTER_SANITIZE_NUMBER_INT));
                    $number = preg_replace("/[^0-9]/", "", $number);
                    $item->addChild('PHONE', $number);
                }
                $item->addChild('SMS_FREQUENCY', $customer->sms_frequency);
                $item->addChild('DATA_PERMISSION', $customer->data_permission);
                $item->addChild('NLF_TIME', $this->getCorrectSambaDate($customer->nlf_time));

                $paramsChild = $item->addChild('PARAMETERS');
                $lastName    = $paramsChild->addChild('PARAMETER');
                $lastName->addChild('NAME', 'LAST_NAME');
                $lastName->addChild('VALUE', htmlspecialchars($customer->lastname));

                $firstName = $paramsChild->addChild('PARAMETER');
                $firstName->addChild('NAME', 'FIRST_NAME');
                $firstName->addChild('VALUE', htmlspecialchars($customer->first_name));

                $tags = unserialize($customer->tags);
                if ($tags) {
                    foreach ($tags as $tag) {
                        $Tag        = ShoperUserTag::findOne(['shoper_shops_id' => $this->id, 'tag_id' => $tag]);
                        $paramChild = $paramsChild->addChild('PARAMETER');
                        $paramChild->addChild('NAME', $Tag->name);
                        $paramChild->addChild('VALUE', '1');
                    }
                }

                $file_handle = fopen($temp, 'a+');
                fwrite($file_handle, $item->asXml());
                fclose($file_handle);

            }
        } else {
            $offset         = $page - $customerPages - 1;
            $subscribers_db = $subscribers_query->limit($page_size)->offset(($offset) * $page_size)->all();

            foreach ($subscribers_db as $subscriber) {
                echo "#";
                if (in_array($subscriber, $usedEmails)) {
                    $subscriber->used = 1;
                    $subscriber->save();
                    continue;
                }
                $item = $customers->addChild('CUSTOMER');
                $item->addChild('CUSTOMER_ID', 'popup-' . htmlspecialchars($subscriber->email));
                $item->addChild('EMAIL', htmlspecialchars($subscriber->email));
                $item->addChild('REGISTRATION', $this->getCorrectSambaDate($subscriber->dateadd));
                $item->addChild('NEWSLETTER_FREQUENCY', 'every day');
                $item->addChild('NLF_TIME', $this->getCorrectSambaDate($subscriber->dateadd));

                $subscriber->used = 1;
                $subscriber->save();

                $file_handle = fopen($temp, 'a+');
                fwrite($file_handle, $item->asXml());
                fclose($file_handle);

            }
        }

        $page++;

        $queue->page = $page;
        $queue->save();

        if ($page > (int) $integrationDataMaxPage) {
            // echo $page.PHP_EOL;
            // echo $integrationDataMaxPage.PHP_EOL;
            // die ("JUZ !!!!!");
            echo "FINISHED ";
            // $this->createCustomerXml($file, $temp);

            return 1;
        }
        // echo "put tp ".$this->getCustomersFile().PHP_EOL;
        // if (file_put_contents($this->getCustomersFile(), $customers->asXML())){
        //     return true;
        // }

        return false;
    }

    public function prepareOrdersFile($queue)
    {
        $orders = new \SimpleXMLElement('<ORDERS/>');
        foreach (Orders::find()->where(['user_id' => $queue->getCurrentUser()->id])->all() as $order) {
            if (Queue::isDisallowedEmail($order->email)) {
                // omit allegro etc
                continue;
            }
            $ordChild = $orders->addChild('ORDER');
            $ordChild->addChild('ORDER_ID', $order->order_id);
            // $ordChild->addChild('CUSTOMER_ID', $order->customer_id);
            $ordChild->addChild('CREATED_ON', $this->getCorrectSambaDate($order->created_on));

            if ($order->status == 'finished') {
                $ordChild->addChild('FINISHED_ON', $this->getCorrectSambaDate($order->finished_on));
            }

            $ordChild->addChild('STATUS', $order->status);
            $email = htmlentities(html_entity_decode($order->email, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $ordChild->addChild('EMAIL', $email);
            $phone = htmlentities(html_entity_decode($order->phone, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $ordChild->addChild('PHONE', str_replace(' ', '', $phone));
            $ordChild->addChild('ZIP_CODE', $order->zip_code);
            $ordChild->addChild('COUNTRY_CODE', $order->country_code);

            $ordItems = $ordChild->addChild('ITEMS');
            foreach ($order->getPositions() as $product) {
                $prodItem = $ordItems->addChild('ITEM');
                $prodItem->addChild('PRODUCT_ID', $product['product_id']);
                $prodItem->addChild('AMOUNT', $product['amount']);
                $prodItem->addChild('PRICE', $product['price']);
            }
        }
        if (file_put_contents($this->getOrdersFile(), $orders->asXML())) {
            return true;
        }

        return false;
    }

    public function getCustomersFile()
    {
        return Yii::$app->basePath . '/modules/shoper/files/' . $this->shop . '.customers.xml';
    }
    public function getCustomersTempFile()
    {
        return Yii::$app->basePath . '/modules/shoper/files/temp_' . $this->shop . '.customers.xml';
    }
    public function getProductsFile()
    {
        return Yii::$app->basePath . '/modules/shoper/files/' . $this->shop . '.products.xml';
    }
    public function getCategoriesFile()
    {
        return Yii::$app->basePath . '/modules/shoper/files/' . $this->shop . '.categories.xml';
    }
    public function getOrdersFile()
    {
        return Yii::$app->basePath . '/modules/shoper/files/' . $this->shop . '.orders.xml';
    }

    public function getCorrectSambaDate($date): string
    {
        $datetime = new \DateTime($date);
//        return $datetime->format('Y-m-d H:i:s.')
        return $datetime->format(DATE_RFC3339_EXTENDED);
    }

    public function checkMetaField($name, $type)
    {
        $meta = ShoperMetafields::findOne(['shoper_shops_id' => $this->id, 'key' => $name]);
        if (! $meta) {
            $data = [
                'namespace'   => ShoperMetafields::NAMESPACE,
                'key'         => $name,
                'description' => 'samba integration field',
                'type'        => $type,
            ];

            $app        = $this->prepareConnection();
            $client     = $app->getClient();
            $resource   = new \DreamCommerce\ShopAppstoreLib\Resource\Metafield($client);
            $listParams = $resource->get();
            if ($listParams) {
                foreach ($listParams as $par) {
                    if ($par->key == $name) {
                        $resource->delete(ShoperMetafields::OBJECT, $par->metafield_id);
                    }
                }
            }

            $result = $resource->post(ShoperMetafields::OBJECT, $data);
            if ($result) {
                $meta            = new ShoperMetafields(['shoper_shops_id' => $this->id, 'key' => $name]);
                $meta->object    = ShoperMetafields::OBJECT;
                $meta->namespace = ShoperMetafields::NAMESPACE;

                $meta->description  = 'samba integration field';
                $meta->type         = $type;
                $meta->metafield_id = $result;
                if (! $meta->save()) {
                    print_r($meta->getErrors());
                    die("!!");
                }
            }
        }
        return $meta;
    }

    public function setMetafield($name, $value, $type)
    {
        $meta = $this->checkMetaField($name, $type);

        if (! $meta) {
            die("error meta");
        }
        $app      = $this->prepareConnection();
        $client   = $app->getClient();
        $resource = new \DreamCommerce\ShopAppstoreLib\Resource\MetafieldValue($client);
        $data     = [
            'metafield_id' => $meta->metafield_id,
            'object_id'    => $meta->id,
            'value'        => $value,
        ];
        $resource->filters(['metafield_id' => $meta->metafield_id]);
        $result = $resource->get();
        if (count($result)) {
            $res = $resource->put($result[0]->value_id, ['value' => $value]);
            return $res;

        }
        $res = $resource->post($data);
        var_dump($res);
        return $res;

    }

    public function getCollections()
    {
        $app      = $this->prepareConnection();
        $client   = $app->getClient();
        $resource = new \DreamCommerce\ShopAppstoreLib\Resource\Collection($client);
        $result   = $resource->get();
        return $result;
    }

    public function getLanguagesList()
    {
        $list = ShoperLanguagesList::find()->where(['shoper_shops_id' => $this->id])->orderBy('order')->all();
        if (! $list) {
            $list      = [];
            $languages = $this->getLanguages();
            foreach ($languages as $lang) {
                $item = ShoperLanguagesList::findOne(['shoper_shops_id' => $this->id, 'locale' => $lang->locale]);
                if (! $item) {
                    $item = new ShoperLanguagesList(['shoper_shops_id' => $this->id, 'locale' => $lang->locale]);
                }
                $item->currency_id = $lang->currency_id;
                $item->active      = $lang->active;
                $item->order       = $lang->order;
                $item->save();
                $list[] = $item;
            }
        }
        return $list;

    }
    public function getCurrenciesList()
    {
        $list = ShoperCurrenciesList::find()->where(['shoper_shops_id' => $this->id])->orderBy('order')->all();
        if (! $list) {
            $list      = [];
            $languages = $this->getCurrencies();
            foreach ($languages as $lang) {
                $item = ShoperCurrenciesList::findOne(['shoper_shops_id' => $this->id, 'currency_id' => $lang->currency_id]);
                if (! $item) {
                    $item = new ShoperCurrenciesList(['shoper_shops_id' => $this->id, 'currency_id' => $lang->currency_id]);
                }
                $item->currency_id = $lang->currency_id;
                $item->active      = $lang->active;
                $item->order       = $lang->order;
                $item->name        = $lang->name;
                $item->rate        = $lang->rate;
                $item->default     = $lang->default;
                $item->rate_sync   = $lang->rate_sync;
                $item->rate_date   = $lang->rate_date;
                $item->save();
                $list[] = $item;
            }
        }
        return $list;

    }

    public function getCurrencies()
    {
        $app      = $this->prepareConnection();
        $client   = $app->getClient();
        $resource = new \DreamCommerce\ShopAppstoreLib\Resource\Currency($client);
        $result   = $resource->get();
        return $result;
    }

    public function getLanguages()
    {
        $app      = $this->prepareConnection();
        $client   = $app->getClient();
        $resource = new \DreamCommerce\ShopAppstoreLib\Resource\Language($client);
        $result   = $resource->get();
        return $result;
    }

    public function getMetafield($name)
    {
        $meta = ShoperMetafields::findOne(['shoper_shops_id' => $this->id, 'key' => $name]);
        if (! $meta) {
            return false;
        }
        $app      = $this->prepareConnection();
        $client   = $app->getClient();
        $resource = new \DreamCommerce\ShopAppstoreLib\Resource\MetafieldValue($client);
        $resource->filters(['metafield_id' => $meta->metafield_id]);
        $result = $resource->get();
        if (count($result)) {
            return $result[0]->value;
        }
        return false;
    }

    public function generateSubscribersimport($queue)
    {
        die("TBC");
    }

    public function checkOrdersDateFrom($queue)
    {
        $dateFrom = $queue->getCurrentUser()->getConfig()->getOrdersDateFrom();
        if (! $dateFrom) {
            return true;
        }
        $dateFrom = date('Y-m-d', strtotime($dateFrom . ' -10 day'));
        echo " DATE FROM " . PHP_EOL;
        var_dump($dateFrom);
        echo $dateFrom . PHP_EOL;
        $check = Orders::find()->where(['user_id' => $queue->getCurrentUser()->id])->andWhere(['<', 'created_on', $dateFrom])->limit(1)->one();
        if ($check) {
            // var_dump($check);
            // die("!STOP");
            Orders::deleteAll(['and',
                ['user_id' => $queue->getCurrentUser()->id],
                ['<', 'created_on', $dateFrom],
            ]);
            // Orders::deleteAll(['user_id'=>$queue->getCurrentUser()->id]);
            IntegrationData::setData('INITIAL_ORDERS_DONE', 1, $queue->getCurrentUser()->id);
            IntegrationData::setData('last_orders_integration_date', $dateFrom, $queue->getCurrentUser()->id);
            IntegrationData::setData('LAST_ORDERS_DONE', $dateFrom, $queue->getCurrentUser()->id);
            $queue->page     = 0;
            $queue->max_page = 0;
            echo "DELETED ORDERS, RECOMMENCING" . PHP_EOL;
            die("!");
        }
        // die("!!");
        // getOrdersDateFrom
    }

}
