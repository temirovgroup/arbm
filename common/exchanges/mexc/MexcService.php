<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\mexc;

use Lin\Mxc\MxcContract;
use Lin\Mxc\MxcSpotV3;
use Yii;

class MexcService
{
    /**
     * @return MxcSpot
     */
    final public static function spot()
    {
        return new MxcSpot(Yii::$app->params['mexc']['key'], Yii::$app->params['mexc']['secret']);
    }

    /**
     * @return MxcSpotV3
     */
    final public static function mxcSpotV3(): MxcSpotV3
    {
        return new MxcSpotV3();
    }

    /**
     * @return MxcContract
     */
    final public static function contract()
    {
        $mexc = new MxcContract();
        $mexc->setOptions([
            //Set the request timeout to 60 seconds by default
            'timeout' => 15,

            //If you are developing locally and need an agent, you can set this
            //'proxy'=>true,
            //More flexible Settings
            /* 'proxy'=>[
             'http'  => 'http://127.0.0.1:12333',
             'https' => 'http://127.0.0.1:12333',
             'no'    =>  ['.cn']
             ], */
            //Close the certificate
            //'verify'=>false,
        ]);

        return $mexc;
    }
}
