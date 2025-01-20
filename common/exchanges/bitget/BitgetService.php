<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\bitget;

use Lin\Bitget\BitgetSpotV2;
use Yii;

class BitgetService
{
    /**
     * @return BitgetSpotV2
     */
    public static function spotV2(): BitgetSpotV2
    {
        return new BitgetSpotV2(Yii::$app->params['bitget']['key'], Yii::$app->params['bitget']['secret'], Yii::$app->params['bitget']['passphrase']);
    }
}
