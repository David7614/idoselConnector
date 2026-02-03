<?php
namespace app\modules\xml_generator\src;

class SoapRequest{
	
	private $params;

	public function __construct($request=[]){
		$params = [
	        'authenticate' => [
	            //leaving empty - authenticating using OAuth access token
	            'userLogin' => '',
	            'authenticateKey' => '',
	            'system_key' => '',
                'system_login' => ''
	        ],
        ];
        $this->params=array_merge($params, $request);    
	}

	public function addParam($key, $value){
		$this->params['params'][$key]=$value;		
	}

	public function setAuth($auth){
		$this->params['authenticate']=$auth;
	}

	public function getRequest(){
		return $this->params;
	}
}
