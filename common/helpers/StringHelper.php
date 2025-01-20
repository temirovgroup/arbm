<?php
/**
 * Created by PhpStorm.
 */

namespace common\helpers;

use yii\helpers\Html;

class StringHelper extends \yii\helpers\StringHelper
{
    /**
     * @param $phone
     * @return string
     */
    public static function preparePhoneToInputMask($phone): string
    {
        return ltrim($phone, '7');
    }

    /**
     * +7(999)999-99-99 и 9999999999 в *9999999999
     * @param $phone
     * @param $format
     * @return false|string
     */
    public static function normalizePhone($phone, $format = '+7')
    {
        $phone = self::toInt($phone);

        if (empty($phone) || mb_strlen($phone) < 10) {
            return false;
        }

        if (strlen($phone) > 10) {
            $phone = mb_substr($phone, -10, 10);
        }

        $phone = "{$format}{$phone}";

        return $phone;
    }

    /**
     * @param $string
     * @return array|string|null
     */
    public static function toInt($string)
    {
        return preg_replace("/[^0-9]/", '', $string);
    }

    /**
     * +79999999999 и 8(999)999-99999 в * 999 999-99-99
     *
     * @param $phone
     * @return array|string|string[]|null
     */
    public static function formatPhone($phone)
    {
        return preg_replace("/(\\d{1})(\\d{3}|\\d{4})(\\d{3}|\\d{4})(\\d{2})(\\d{2})$/i", "$1 ($2) $3 $4 $5 $6", self::normalizePhone($phone));
    }

    /**
     * 10000 > 10 000
     * @param $number
     * @param $decimals
     * @return string
     */
    public static function numFormat($number, $decimals = 0): string
    {
        return number_format($number, $decimals, '.', ' ');
    }

    /**
     * @param int $count
     * @param string $d1
     * @param string $d2
     * @param string $d3
     * @return string
     */
    public static function declineWord(int $count, string $d1, string $d2, string $d3): string
    {
        $count = abs(intval($count)) % 100;

        if ($count > 10 && $count < 20) {
            return $d3;
        }

        $count %= 10;

        if ($count === 1) {
            return $d1;
        }

        if ($count > 1 && $count < 5) {
            return $d2;
        }

        return $d3;
    }
}
