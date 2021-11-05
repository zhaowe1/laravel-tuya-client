<?php

namespace LaravelTuyaClient;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TuyaClient
{
    public static $client;

    protected $endpoint = '';
    protected $accessId = '';
    protected $accessSecret = '';

    protected $httpClient;

    const KEY_TU_YA_ACCESS_TOKEN = 'tuya:access_token';
    const KEY_TU_YA_TOKEN_RES = 'tuya:token_res';

    /**
     * @return TuyaClient
     */
    public static function getClient($config)
    {
        if (empty(static::$client)) {
            static::$client = new static();
            static::$client->endpoint = $config['endpoint'];
            static::$client->accessId = $config['access_id'];
            static::$client->accessSecret = $config['access_secret'];
            static::$client->httpClient = new Client(['base_uri' => static::$client->endpoint]);
        }

        if (!Cache::has(static::KEY_TU_YA_ACCESS_TOKEN)) {
            if (Cache::has(static::KEY_TU_YA_TOKEN_RES)) {
                $refreshToken = Cache::get(static::KEY_TU_YA_TOKEN_RES)['refresh_token'];
                $tokenRes = static::$client->getToken($refreshToken);
                if (!$tokenRes['success']) {
                    $tokenRes = static::$client->getToken();
                }
            } else {
                $tokenRes = static::$client->getToken();
            }

            if ($tokenRes['success']) {
                Cache::put(static::KEY_TU_YA_ACCESS_TOKEN, $tokenRes['result']['access_token'], $tokenRes['result']['expire_time']);
                Cache::put(static::KEY_TU_YA_TOKEN_RES, $tokenRes['result'], $tokenRes['result']['expire_time'] + 3600);
            }
        }

        return static::$client;
    }

    /**
     * 发送API请求
     * @param string $method
     * @param string $url
     * @param array $options
     * @return array
     */
    public function send($method, $url, $options = [])
    {
        $method = strtoupper($method);

        if (!empty($options['params'])) {
            foreach ($options['params'] as $k => $v) {
                $url = str_replace('{' . $k . '}', $v, $url);
            }
        }
        if (!empty($options['query'])) {
            ksort($options['query']);
            $url .= '?' . http_build_query($options['query']);
        }

        $requestOptions = [
            'headers' => $this->getHeaders($method, $url, isset($options['body']) ? json_encode($options['body']) : ''),
        ];
        if (isset($options['body'])) {
            $requestOptions['json'] = $options['body'];
        }

        $response = $this->httpClient->request($method, $url, $requestOptions);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * 获取TOKEN
     * @param string $refreshToken
     * @return array
     */
    protected function getToken($refreshToken = '')
    {
        if (!empty($refreshToken)) {
            $url = '/v1.0/token/' . $refreshToken;
        } else {
            $url = '/v1.0/token?grant_type=1';
        }

        $response = $this->httpClient->get($url, [
            'headers' => $this->getHeaders('GET', $url),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * 获取请求Header
     * @param string $method
     * @param string $url
     * @param string $body
     * @param array $appendHeaders
     * @return array
     */
    public function getHeaders($method, $url, $body = '', $appendHeaders = [])
    {
        if (substr($url, 0, 11) == '/v1.0/token') {
            $accessToken = '';
        } else {
            $accessToken = Cache::get(static::KEY_TU_YA_ACCESS_TOKEN);
        }

        $nonce = Str::random();
        $t = intval(microtime(true) * 1000);

        $headersStr = '';
        if (!empty($appendHeaders)) {
            foreach ($appendHeaders as $k => $v) {
                $headersStr .= $k . ':' . $v . "\n";
            }
            $headersStr = rtrim($headersStr, "\n");
        }

        $headers = [
            'client_id' => $this->accessId,
            'sign_method' => 'HMAC-SHA256',
            't' => $t,
            'nonce' => $nonce,
        ];
        if (!empty($accessToken)) {
            $headers['access_token'] = $accessToken;
        }

        $stringToSing = $method . "\n" . hash('sha256', $body) . "\n" . $headersStr . "\n" . $url;

        $sign = strtoupper(hash_hmac('sha256', $this->accessId . $accessToken . $t . $nonce . $stringToSing, $this->accessSecret));
        $headers['sign'] = $sign;

        return $headers;
    }
}
