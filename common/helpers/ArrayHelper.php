<?php
/**
 * Created by PhpStorm.
 */

namespace common\helpers;

class ArrayHelper extends \yii\helpers\ArrayHelper
{
    /**
     * @param array $array
     * @return int
     */
    public static function arraySumBcadd(array $array)
    {
        return array_reduce($array, function ($items, $item) {
            $items = bcadd(NumberHelper::round($items), NumberHelper::round($item), 13);

            return $items;
        }, 0);
    }

    /**
     * @param array $array
     * @return string
     */
    public static function arraySumAvg(array $array)
    {
        return bcdiv(self::arraySumBcadd($array), count($array), 13);
    }
}
