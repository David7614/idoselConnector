<?php
namespace app\commands;

use app\models\User;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Diagnostyka kluczy API IdoSell per user (storefront w multistore).
 */
class ApiTestController extends Controller
{
    /**
     * Bramki testowe - pokrywaja kazdy obszar uprawnien aplikacji.
     * body=null -> GET, body!=null -> POST z tym payloadem.
     */
    private function gates()
    {
        $probe = ['params' => ['resultsLimit' => 1, 'resultsPage' => 0]];
        return [
            ['area' => 'System (config)', 'method' => 'GET',  'path' => '/api/admin/v3/system/config',          'body' => null],
            ['area' => 'CRM (clients)',    'method' => 'GET',  'path' => '/api/admin/v4/clients/clients',          'body' => null],
            ['area' => 'OMS (orders)',     'method' => 'POST', 'path' => '/api/admin/v4/orders/orders/get',        'body' => $probe],
            ['area' => 'PIM (products)',   'method' => 'POST', 'path' => '/api/admin/v4/products/products/get',     'body' => $probe],
        ];
    }

    /**
     * Testuje klucz API dla danego usera i wypisuje pelna diagnostyke.
     *
     * Przyklad:
     *   php yii api-test/key 238 "YXBwbG...a2lu"
     *   php yii api-test/key 238           (uzyje klucza zapisanego u usera)
     *
     * @param int         $userId id usera (storefront) - z niego brany jest host API (username)
     * @param string|null $apiKey klucz do przetestowania; pominiety -> klucz z bazy usera
     * @return int kod wyjscia (0 = wszystkie bramki OK)
     */
    public function actionKey($userId, $apiKey = null)
    {
        $user = User::findOne((int) $userId);
        if (!$user) {
            $this->stderr("BLAD: nie ma usera o id={$userId}\n", Console::FG_RED);
            return ExitCode::NOINPUT;
        }

        $host = $user->username;
        if ($apiKey === null || $apiKey === '') {
            $apiKey = $user->getApiKey();
            $keySource = 'z bazy (api3_key)';
        } else {
            $keySource = 'z parametru';
        }

        $this->stdout("=== Test klucza API ===\n", Console::BOLD);
        $this->stdout(sprintf("user id   : %d\n", $user->id));
        $this->stdout(sprintf("username  : %s\n", $host !== '' ? $host : '(pusty!)'));
        $this->stdout(sprintf("active    : %s\n", $user->active ? 'tak' : 'NIE (0)'));
        $this->stdout(sprintf("user_type : %s\n", $user->user_type !== null && $user->user_type !== '' ? $user->user_type : '(brak)'));
        $this->stdout(sprintf("klucz     : %s, dlugosc=%d, podglad=%s\n",
            $keySource,
            strlen((string) $apiKey),
            $this->maskKey((string) $apiKey)
        ));

        if ($host === '' || $host === null) {
            $this->stderr("BLAD: user nie ma username - brak hosta do odpytania API.\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }
        if ($apiKey === null || $apiKey === '') {
            $this->stderr("BLAD: brak klucza API (ani w parametrze, ani w bazie).\n", Console::FG_RED);
            return ExitCode::CONFIG;
        }

        $this->stdout("\n");
        $failures = 0;

        foreach ($this->gates() as $gate) {
            $res = $this->request($host, $gate['path'], $gate['method'], $apiKey, $gate['body']);
            $ok  = $this->isOk($res);
            if (!$ok) {
                $failures++;
            }

            $this->stdout(sprintf("[%s] %s\n", $ok ? ' OK ' : 'FAIL', $gate['area']),
                $ok ? Console::FG_GREEN : Console::FG_RED);
            $this->stdout(sprintf("     %s %s\n", $gate['method'], $res['url']));

            if ($res['curl_errno'] !== 0) {
                $this->stdout(sprintf("     cURL blad #%d: %s\n", $res['curl_errno'], $res['curl_error']), Console::FG_RED);
            } else {
                $this->stdout(sprintf("     HTTP %d, czas %.0f ms, %d B\n",
                    $res['http_code'], $res['time_ms'], $res['size']));
            }

            $diag = $this->diagnose($res);
            if ($diag !== '') {
                $this->stdout("     -> " . $diag . "\n", $ok ? Console::FG_GREY : Console::FG_YELLOW);
            }

            if (!$ok && $res['body'] !== '') {
                $this->stdout("     body: " . $this->snippet($res['body']) . "\n", Console::FG_GREY);
            }

            // Z system/config wyciagnij dane sklepu - w multistore pomaga potwierdzic ktory to sklep.
            if ($ok && strpos($gate['path'], 'system/config') !== false) {
                $this->printShopInfo($res['body']);
            }

            $this->stdout("\n");
        }

        if ($failures === 0) {
            $this->stdout("WYNIK: klucz dziala na wszystkich bramkach.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout(sprintf("WYNIK: %d z %d bramek nie przeszlo.\n", $failures, count($this->gates())), Console::FG_RED);
        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Pojedyncze zapytanie do API z pelna diagnostyka (bez polykania bledow jak ApiClient).
     */
    private function request($host, $path, $method, $apiKey, $body)
    {
        $url = 'https://' . $host . $path;
        $ch  = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-API-KEY: ' . $apiKey,
            ],
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? []));
        }

        $start    = microtime(true);
        $response = curl_exec($ch);
        $timeMs   = (microtime(true) - $start) * 1000;

        $result = [
            'url'        => $url,
            'http_code'  => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'size'       => is_string($response) ? strlen($response) : 0,
            'time_ms'    => $timeMs,
            'curl_errno' => curl_errno($ch),
            'curl_error' => curl_error($ch),
            'body'       => is_string($response) ? $response : '',
        ];
        curl_close($ch);
        return $result;
    }

    private function isOk($res)
    {
        if ($res['curl_errno'] !== 0) {
            return false;
        }
        if ($res['http_code'] < 200 || $res['http_code'] >= 300) {
            return false;
        }
        // 2xx, ale IdoSell potrafi zwrocic errors/faultCode w ciele przy statusie 200/207.
        $json = json_decode($res['body'], true);
        if (is_array($json) && isset($json['errors']) && !empty($json['errors']['faultCode'])) {
            return false;
        }
        return true;
    }

    /**
     * Czytelna interpretacja odpowiedzi.
     */
    private function diagnose($res)
    {
        if ($res['curl_errno'] !== 0) {
            return 'Brak polaczenia z hostem (DNS/TLS/timeout) - sprawdz czy username to poprawny adres sklepu.';
        }

        $json = json_decode($res['body'], true);
        if (is_array($json) && isset($json['errors']['faultCode'])) {
            return sprintf('API faultCode=%s: %s',
                $json['errors']['faultCode'],
                $json['errors']['faultString'] ?? '(bez opisu)');
        }

        switch (true) {
            case $res['http_code'] === 0:
                return 'Serwer nie odpowiedzial.';
            case $res['http_code'] === 401:
            case $res['http_code'] === 403:
                return 'Odrzucone (401/403) - bledny klucz API lub brak uprawnien do tego obszaru.';
            case $res['http_code'] === 404:
                return 'Bramka nie znaleziona (404) - inna wersja API lub zly adres.';
            case $res['http_code'] === 500:
                return 'Blad serwera API (500) - czesto zly/pusty payload albo problem po stronie sklepu.';
            case $res['http_code'] >= 200 && $res['http_code'] < 300:
                return '';
            default:
                return 'Nieoczekiwany kod HTTP.';
        }
    }

    private function printShopInfo($body)
    {
        $json = json_decode($body, true);
        if (!is_array($json)) {
            return;
        }
        $owner = $json['shop_owner_data'] ?? [];
        $this->stdout(sprintf("     sklep: client_id=%s, dedicated_server=%s, firma=%s\n",
            $json['client_id'] ?? '?',
            isset($json['dedicated_server']) ? ($json['dedicated_server'] ? 'tak' : 'nie') : '?',
            $owner['company_name'] ?? '?'
        ), Console::FG_CYAN);
    }

    private function maskKey($key)
    {
        $len = strlen($key);
        if ($len <= 10) {
            return str_repeat('*', $len);
        }
        return substr($key, 0, 6) . '...' . substr($key, -4);
    }

    private function snippet($body, $max = 400)
    {
        $body = trim(preg_replace('/\s+/', ' ', $body));
        return strlen($body) > $max ? substr($body, 0, $max) . '...' : $body;
    }
}
