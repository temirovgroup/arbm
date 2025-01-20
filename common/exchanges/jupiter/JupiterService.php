<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\jupiter;

use GuzzleHttp\Client;
use yii\helpers\Json;

class JupiterService
{
    /**
     * @param string $compareBegin
     * @param string $compareWith
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getPrice(string $compareBegin, string $compareWith): array
    {
        $request = self::auth()->request('GET', "price?ids={$compareBegin}&vsToken={$compareWith}");

        return Json::decode($request->getBody()->getContents());
    }

    /**
     * @return Client
     */
    final public static function auth(): Client
    {
        return new Client([
            'base_uri' => 'https://price.jup.ag/v6/',
            'timeout' => 15,
        ]);
    }
}
