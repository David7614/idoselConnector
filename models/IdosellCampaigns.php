<?php
namespace app\models;

class IdosellCampaigns
{
    private $gate  = "/api/admin/v5/snippets/campaign";
    private $gateSnippets  = "/api/admin/v5/snippets/snippets";




    public function __construct($user)
    {
        $this->_user = $user;
        $this->_client = new \app\modules\idosellv3\models\ApiClient($user->username, $user->getApiKey());
    }

    public function getCampaigns($params = [])
    {
        $response = $this->_client->get($this->gate, $params);
        if ($response->isOk) {
            return $response->data;
        }
        return [];
    }

    public function getCampaign($id)
    {
        $response = $this->_client->get($this->gate . '/' . $id);
        if ($response->isOk) {
            return $response->data;
        }
        return null;
    }

    public function updateCampaign($id, $data)
    {
        $response = $this->_client->put($this->gate . '/' . $id, $data);
        if ($response->isOk) {
            return $response->data;
        }
        return null;
    }


    public function deleteCampaign($id, $user)
    {
        $response = $this->_client->delete($this->gate . '?id=' . $id );
        if (!$response)
        {
            return false;
        }
        if ($response['results'][0]['id']){
            $user->getConfig()->set('campain_id', null);
            return true;
        }
        return false;
    }



    public function createCampaign($shopIds)
    {
        $data['name']='SAMBA.AI - Marketing Automation M2ITSolutions';
        $data['description']='SAMBA.AI - The best of marketing automation system. Wykonane przez M2ITSolutions.pl';
        $data['shop']=$shopIds;
        $data['active']="y";
        $campainData['params']['campaigns']=[
            $data
        ];
        $response = $this->_client->post($this->gate, $campainData);
        // var_dump($campainData);
        // var_dump($response);
        // die();
        if (!$response)
        {
            return false;
        }
        if ($response['results']) {
            return $response;
        }
        return null;
    }



    public function createSnippets($campaignId, $user)
    {

        $snippets=$this->getSnippetList($campaignId, $user);

        $data['params']['snippets']=$snippets;
        // echo "<pre>";
        // var_dump($data);
        // var_dump(json_encode($data));

        $response = $this->_client->post($this->gateSnippets, $data);
        if (!$response)
        {
            return false;
        }


    }

    private function getSnippetList($campaignId, $user)
    {
        $trackpoint=$user->getConfig()->get('trackpoint');

        $snippet=[];
        $snippet['name']='Pixel SAMBAAI';
        $snippet['campaign']=$campaignId;
        $snippet['zone']='head';
        $snippet['body'][0]['body']='<!-- Samba.ai pixel -->
            <script async src="https://yottlyscript.com/script.js?tp='.$trackpoint.'"></script>
            <!-- End Samba.ai pixel -->';
        $snippet['body'][0]['lang']='pol';
        $snippet['active']='y';
        $snippet['pages']['all']='y';
        $snippet['display']['phone']='y';
        $snippet['display']['screen']='y';
        $snippet['display']['tablet']='y';
        $snippet['type']='html';
        $snippet['useAjax']='n';

        $snippets[]=$snippet;

        $snippet=[];
        $snippet['name']='Order';
        $snippet['campaign']=$campaignId;
        $snippet['zone']='bodyEnd';
        $snippet['body'][0]['body']='var _yottlyOnload = _yottlyOnload || []
            _yottlyOnload.push(function () {
            var sambaBasket = [];
            [iai:foreach_products_begin]
            var sambaPrice = [iai:product_price_gross_float] * [iai:product_count];
            sambaBasket.push({
                "productId": "[iai:product_id]",
                "price": sambaPrice
            })
            [iai:foreach_products_end]
             diffAnalytics.order({content: sambaBasket})
            var content = [];
            diffAnalytics.cartInteraction({ content: content, onOrderPage: true})
             }
            )';
        $snippet['body'][0]['lang']='pol';
        $snippet['active']='y';
        $snippet['pages']['all']='n';
        $snippet['pages']['pages'][]='after_order_place';
        $snippet['display']['phone']='y';
        $snippet['display']['screen']='y';
        $snippet['display']['tablet']='y';
        $snippet['type']='javascript';

        $snippets[]=$snippet;

        $snippet=[];
        $snippet['name']='cartInteraction - non cart';
        $snippet['campaign']=$campaignId;
        $snippet['zone']='bodyEnd';
        $snippet['body'][0]['body']='var _yottlyOnload = _yottlyOnload || []
                _yottlyOnload.push(function () {
                function getBasketInfo(callback) {
                    let address = window.location.protocol + "//" + window.location.host + "/ajax/basket.php";

                    let http = new XMLHttpRequest();
                    http.open("GET", address, true);

                    http.onreadystatechange = function () {
                        if (http.readyState == 4 && http.status == 200) {
                            let response = http.responseText;
                            callback(JSON.parse(response));
                        }
                    }
                    http.send(null);
                }

                getBasketInfo((basketInfo) => {
                    let content = [];
                    basketInfo.basket.products.forEach((product) => {
                        console.log(product);
                        content.push({
                            "productId": String(product.id),
                            "amount":  product.count,
                        })
                    })
                    diffAnalytics.cartInteraction({content: content, onOrderPage: false})
                })

                }
                )';
        $snippet['body'][0]['lang']='pol';
        $snippet['active']='y';
        $snippet['pages']['all']='n';
        $snippet['pages']['pages'][]='home';
        $snippet['pages']['pages'][]='navigation';
        $snippet['pages']['pages'][]='product_details';
        $snippet['pages']['pages'][]='search_results';
        $snippet['pages']['pages'][]='mailing_subscribe';
        $snippet['pages']['pages'][]='other_pages';
        $snippet['display']['phone']='y';
        $snippet['display']['screen']='y';
        $snippet['display']['tablet']='y';
        $snippet['type']='javascript';

        $snippets[]=$snippet;

        $snippet=[];
        $snippet['name']='cartInteraction - cart';
        $snippet['campaign']=$campaignId;
        $snippet['zone']='bodyEnd';
        $snippet['body'][0]['body']='var _yottlyOnload = _yottlyOnload || []
                function getBasketInfo(callback) {
                    let address = window.location.protocol + "//" + window.location.host + "/ajax/basket.php";

                    let http = new XMLHttpRequest();
                    http.open("GET", address, true);

                    http.onreadystatechange = function () {
                        if (http.readyState == 4 && http.status == 200) {
                            let response = http.responseText;
                            callback(JSON.parse(response));
                        }
                    }
                    http.send(null);
                }
                let content_ = []
                [iai:foreach_products_begin]
                content_.push({
                    "productId":String([iai:product_id]),
                    "amount":[iai:product_count]
                })
                [iai:foreach_products_end]
                if(content_.length == 0){
                    _yottlyOnload.push(function () {
                        getBasketInfo((basketInfo) => {
                            let content_inner = [];
                            basketInfo.basket.products.forEach((product) => {
                                console.log(product);
                                content_inner.push({
                                    "productId": String(product.id),
                                    "amount":  product.count,
                                })
                            })
                            diffAnalytics.cartInteraction({content: content_inner, onOrderPage: false})
                        })
                     })

                }else{
                    _yottlyOnload.push(function () {
                        diffAnalytics.cartInteraction({content: content_, onOrderPage: false})
                    })
                }


                ';
        $snippet['body'][0]['lang']='pol';
        $snippet['active']='y';
        $snippet['pages']['all']='n';
        $snippet['pages']['pages'][]='basket';
        $snippet['pages']['pages'][]='checkout_payment_delivery';
        $snippet['pages']['pages'][]='checkout_confirmation';
        $snippet['pages']['pages'][]='new_order_placement';
        $snippet['display']['phone']='y';
        $snippet['display']['screen']='y';
        $snippet['display']['tablet']='y';
        $snippet['type']='javascript';

        $snippets[]=$snippet;

        $snippet=[];
        $snippet['name']='userLoggedIn';
        $snippet['campaign']=$campaignId;
        $snippet['zone']='bodyEnd';
        $snippet['body'][0]['body']='<script>
            var _yottlyOnload = _yottlyOnload || []
            _yottlyOnload.push(function () {
                diffAnalytics.customerLoggedIn(""+[iai:client_id]);
             }
            )
            </script>';
        $snippet['body'][0]['lang']='pol';
        $snippet['active']='y';
        $snippet['pages']['all']='y';
        $snippet['display']['phone']='y';
        $snippet['display']['screen']='y';
        $snippet['display']['tablet']='y';
        $snippet['display']['clientType']='registered';
        $snippet['type']='html';

        $snippets[]=$snippet;

        $snippet=[];
        $snippet['name']='productID';
        $snippet['campaign']=$campaignId;
        $snippet['zone']='bodyEnd';
        $snippet['body'][0]['body']='var _yottlyOnload = _yottlyOnload || []
            _yottlyOnload.push(function () {
                diffAnalytics.productId(String([iai:itemcardpage_product_id]))
            }
            )';
        $snippet['body'][0]['lang']='pol';
        $snippet['active']='y';
        $snippet['pages']['all']='n';
        $snippet['pages']['pages'][]='product_details';
        $snippet['display']['phone']='y';
        $snippet['display']['screen']='y';
        $snippet['display']['tablet']='y';
        $snippet['type']='javascript';

        $snippets[]=$snippet;

        return $snippets;
    }


}

