<?php

namespace app\modules\xml_generator\src;

// use SoapClient;

class IdioselClient extends \SoapClient
{
	public function __construct($gate, $token){
		$params=[
                'stream_context' => stream_context_create([
                    'http' => [
                        'header' => 'Authorization: Bearer ' . $token
                    ],
                ]),
                'cache_wsdl' => WSDL_CACHE_NONE
            ];
            parent::__construct($gate,$params);
		
        
	}	

}