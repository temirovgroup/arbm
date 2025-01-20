<?php
/**
 * Created by PhpStorm.
 */

namespace common\helpers;

class NumberHelper
{
    /**
     * На сколько процентов цена в одной бирже больше чем в другой
     * @param $price1
     * @param $price2
     * @param $minPercent
     * @param $maxPercent
     * @return bool
     */
    public static function getPercentManyMore($price1, $price2, $minPercent = 2, $maxPercent = 50): bool
    {
        if ($price1 <= 0 || $price2 <= 0) {
            return false;
        }

        $percent = ($price1 / $price2 * 100) - 100;

        return $percent > $minPercent && $percent < $maxPercent;
    }

    /**
     * @param $value
     * @return string
     */
    public static function round($value)
    {
        return  rtrim(rtrim(sprintf('%.15F', $value), '0'), '.');
    }

    /**
     * @param $value
     * @return string
     */
    public static function numFormatSymbol($value): string
    {
        return number_format($value, 2, '.', ' ');
    }
}
