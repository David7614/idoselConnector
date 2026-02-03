<?php
namespace app\modules\idosellv3\models;

class ApiClient
{
    private $accessToken;
    private $baseUrl;

    public function __construct($baseUrl, $accessToken)
    {
        // if (! $accessToken) {
        //     throw new \Exception('Access token is required');
        // }

        $this->accessToken = $accessToken;
        $this->baseUrl     = $baseUrl;
    }

    public function get($endpoint, $data = [])
    {
        return $this->sendRequest($endpoint, 'GET', $data);
    }
    public function put($endpoint, $data)
    {
        return $this->sendRequest($endpoint, 'PUT', $data);
    }
    public function post($endpoint, $data)
    {
        return $this->sendRequest($endpoint, 'POST', $data);
    }
    public function delete($endpoint, $data=[])
    {
        // echo $endpoint;
        return $this->sendRequest($endpoint, 'DELETE', $data);
    }

    /**
     * Send an API
     *
     * @param string $endpoint - API endpoint, e.g., 'admin/orders/1'
     * @param string $method - HTTP method: GET, POST, PATCH, PUT
     * @param array $data - Data to be sent in the request body (optional)
     * @return array|false - API response or false if request failed
     */
    public function sendRequest($endpoint, $method = 'GET', $data = [])
    {
        $url = 'https://' . $this->baseUrl . $endpoint;

        // echo $url."<br>";

        // Initialize cURL session
        $ch = curl_init($url);

        // Set HTTP headers
        $headers = [
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->accessToken,
        ];

        // var_dump($url);
        // var_dump($headers);
        // die();
        // Set HTTP method and request body for POST, PATCH, and PUT
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PATCH':
            case 'DELETE':
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;

            default: // For GET, no need to send a body
                if (! empty($data)) {
                    $url .= '?' . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if request was successful
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        } else {
            return false; // Return false on error
        }
    }

    public function testApiCredentials(){
        $errors=[];
        $res    = $this->sendRequest('/api/admin/v3/system/config');
        if (!$res){
            $errors[]='Nie udało się wysłać testowego zapytania do bramki system/config - błędny klucz api lub brak uprawnień do System';
        }
        $res    = $this->sendRequest('/api/admin/v4/clients/clients');
        if (!$res){
            $errors[]='Nie udało się wysłać testowego zapytania do bramki clients/clients - błędny klucz api lub brak uprawnień do CRM';
        }
        $res    = $this->post('/api/admin/v4/orders/orders/get', []);
        if (!$res){
            $errors[]='Nie udało się wysłać testowego zapytania do bramki orders/orders/get - błędny klucz api lub brak uprawnień do OMS';
        }
        $res    = $this->post('/api/admin/v4/products/products/get', []);
        if (!$res){
            $errors[]='Nie udało się wysłać testowego zapytania do bramki products/products/get - błędny klucz api lub brak uprawnień do PIM';
        }

        if (empty($errors)){
            return true;
        }
        // echo implode('<br>', $errors);
        // die();
        \Yii::$app->session->addFlash('error', implode('<br>', $errors));
        return false;

    }
}
