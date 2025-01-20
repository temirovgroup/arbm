<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\kucoin;

use common\exchanges\kucoin\publicApi\Currency;
use KuCoin\SDK\Auth;
use KuCoin\SDK\PrivateApi\Withdrawal;
use KuCoin\SDK\PublicApi\Symbol;
use Yii;
use function PHPUnit\Framework\isNull;

class KucoinService
{
    private static ?Auth $auth = null;

    /**
     * @return Symbol
     */
    public static function symbol(): Symbol
    {
        return new Symbol(self::auth());
    }

    /**
     * @return Currency
     */
    public static function currency(): Currency
    {
        return new Currency(self::auth());
    }

    /**
     * @return Withdrawal
     */
    public static function withdrawal(): Withdrawal
    {
        return new Withdrawal(self::auth());
    }

    /**
     * @return Auth
     */
    final public static function auth(): Auth
    {
        if (isNull(self::$auth)) {
            self::$auth = new Auth(Yii::$app->params['kucoin']['key'], Yii::$app->params['kucoin']['secret'], Yii::$app->params['kucoin']['passphrase']);
        }

        return self::$auth;
    }
}
