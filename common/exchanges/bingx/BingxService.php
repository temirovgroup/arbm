<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\bingx;

use Yii;

class BingxService
{
    /**
     * Оредеры по валютной паре
     * @param string $symbol
     * @param int $limit
     * @return array
     */
    public static function getDepth(string $symbol, int $limit = 100): array
    {
        return self::doRequest('/openApi/swap/v2/quote/depth', 'GET', [
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }

    /**
     * @param string $coin
     * @return array
     */
    public static function getCurrency(string $coin = ''): array
    {
        return self::doRequest('/openApi/wallets/v1/capital/config/getall', 'GET', [
            'coin' => $coin,
        ]);
    }

    /**
     * Тикеры валютных пар
     * @param string $symbol
     * @return array
     */
    public static function getTickers(string $symbol = ''): array
    {
        return self::doRequest('/openApi/swap/v2/quote/ticker', 'GET', [
            'symbol' => $symbol,
        ]);
    }

    /**
     * Последние сделки по валютной паре
     * @param $symbol
     * @param $limit
     * @return array
     */
    public static function getLatestTrade($symbol, $limit = 10): array
    {
        return self::doRequest('/openApi/swap/v2/quote/trades', 'GET', [
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
    }

    /**
     * @param $uri
     * @param $method
     * @param $payload
     * @return array
     */
    public static function doRequest($uri, $method, $payload = null): array
    {
        $host = "open-api.bingx.com";
        $protocol = 'https';

        $timestamp = round(microtime(true) * 1000);
        $parameters = "timestamp=" . $timestamp;

        if ($payload !== null) {
            foreach ($payload as $key => $value) {
                $parameters .= "&$key=$value";
            }
        }

        $sign = self::calculateHmacSha256($parameters, Yii::$app->params['bingx']['secret']);
        $url = "{$protocol}://{$host}{$uri}?{$parameters}&signature={$sign}";

        $options = [
            "http" => [
                "header" => "X-BX-APIKEY: " . Yii::$app->params['bingx']['key'],
                "method" => $method,
            ],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
        ];

        return [
            'data' => file_get_contents($url, false, stream_context_create($options)),
            'protocol' => $protocol,
            'method' => $method,
            'host' => $host,
            'uri' => $uri,
            'parameters' => $parameters,
            'sign' => $sign,
            $method => $url,
        ];
    }

    /**
     * @param string $input
     * @param string $key
     * @return string
     */
    private static function calculateHmacSha256(string $input, string $key): string
    {
        $hash = hash_hmac("sha256", $input, $key, true);
        $hashHex = bin2hex($hash);

        return strtolower($hashHex);
    }
}
