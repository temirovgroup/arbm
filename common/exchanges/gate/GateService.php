<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\gate;

use GateApi\Api\WalletApi;
use GateApi\Api\WithdrawalApi;
use GateApi\Configuration;
use GuzzleHttp\Client;
use Yii;

class GateService
{
    /**
     * @return \GateApi\Api\SpotApi
     */
    final public static function spot(): \GateApi\Api\SpotApi
    {
        return new \GateApi\Api\SpotApi(
            new Client(),
            self::getConfig(),
        );
    }

    /**
     * @return WithdrawalApi
     */
    final public static function withdraw(): WithdrawalApi
    {
        return new WithdrawalApi(new Client(), self::getConfig());
    }

    /**
     * @return WalletApi
     */
    final public static function wallet(): WalletApi
    {
        return new WalletApi(new Client(), self::getConfig());
    }

    /**
     * @return Configuration
     */
    private static function getConfig(): Configuration
    {
        return Configuration::getDefaultConfiguration()
            ->setKey(Yii::$app->params['gate']['key'])
            ->setSecret(Yii::$app->params['gate']['secret']);
    }
}
