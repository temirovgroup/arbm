<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\bitmart;

use BitMart\Futures\APIContractMarket;
use BitMart\Lib\CloudConfig;
use BitMart\Spot\APIAccount;
use BitMart\Spot\APISpot;
use Yii;

class BitmartService
{
    /**
     * @return APISpot
     */
    final public static function spot(): APISpot
    {
        return new APISpot(new CloudConfig([
            'accessKey' => Yii::$app->params['bitmart']['key'],
            'secretKey' => Yii::$app->params['bitmart']['secret'],
            'timeoutSecond' => 15,
        ]));
    }

    /**
     * @return APIContractMarket
     */
    final public static function contractMarket(): APIContractMarket
    {
        return new APIContractMarket(new CloudConfig([
            'timeoutSecond' => 15,
        ]));
    }

    /**
     * @return APIAccount
     */
    final public static function account(): APIAccount
    {
        return new APIAccount(new CloudConfig([
            'accessKey' => Yii::$app->params['bitmart']['key'],
            'secretKey' => Yii::$app->params['bitmart']['secret'],
            'timeoutSecond' => 15,
        ]));
    }
}
