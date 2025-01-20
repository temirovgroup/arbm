<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\xt;

use GuzzleHttp\Client;
use yii\helpers\Json;

class XtService
{
    /**
     * reutrn Array
     * (
     * [rc] => 0
     * [mc] => SUCCESS
     * [ma] => Array
     * (
     * )
     *
     * [result] => Array
     * (
     * [0] => Array
     * (
     * [s] => knc_usdt //symbol
     * [t] => 1719777570544 //update time
     * [cv] => -0.0263 //change value
     * [cr] => -0.0469 //change rate
     * [o] => 0.5607 //open
     * [l] => 0.5321 //low
     * [h] => 0.5620 //high
     * [c] => 0.5344 //close
     * [q] => 552855.6 //quantity
     * [v] => 302194.55285 //volume
     * [ap] => 0.5344 //asks price(sell one price)
     * [aq] => 469.2 //asks qty(sell one quantity)
     * [bp] => 0.5341 //bids price(buy one price)
     * [bq] => 474.7 //bids qty(buy one quantity)
     * )
     *
     * )
     *
     * )

     * @param $symbol
     * @return mixed|string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getTickers($symbol = null)
    {
        try {
            $request = self::auth()->request('GET', "ticker?symbol={$symbol}");

            return Json::decode($request->getBody()->getContents());
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * return array()
     * [id] => 44
     * [currency] => knc
     * [displayName] => KNC
     * [type] => FT
     * [nominalValue] =>
     * [fullName] => KNC
     * [logo] => https://a.static-global.com/1/currency/knc.png
     * [cmcLink] => https://coinmarketcap.com/currencies/kyber-network-crystal-v2/
     * [weight] => 1
     * [maxPrecision] => 8
     * [depositStatus] => 1
     * [withdrawStatus] => 1
     * [convertEnabled] => 1
     * [transferEnabled] => 1
     * [isChainExist] => 1
     * [plates] => Array
     * (
     * [0] => 1
     * )
     * [isListing] => 1
     * [withdrawCloseReason] =>
     *
     * @return mixed|string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getCurrency()
    {
        try {
            $request = self::auth()->request('GET', 'currencies');

            return Json::decode($request->getBody()->getContents());
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * [0] => Array
     * (
     * [currency] => btc
     * [supportChains] => Array
     * (
     * [0] => Array
     * (
     * [chain] => Bitcoin
     * [depositEnabled] => 1
     * [withdrawEnabled] => 1
     * [withdrawFeeAmount] => 0.001
     * [withdrawFeeCurrency] => btc
     * [withdrawFeeCurrencyId] => 2
     * [withdrawMinAmount] => 0.00195
     * [depositFeeRate] => 0
     * )
     *
     * [1] => Array
     * (
     * [chain] => BNB Smart Chain
     * [depositEnabled] => 1
     * [withdrawEnabled] => 1
     * [withdrawFeeAmount] => 1.0E-5
     * [withdrawFeeCurrency] => btc
     * [withdrawFeeCurrencyId] => 2
     * [withdrawMinAmount] => 0.00017
     * [depositFeeRate] => 0
     * )
     *
     * [2] => Array
     * (
     * [chain] => Ethereum
     * [depositEnabled] => 1
     * [withdrawEnabled] => 1
     * [withdrawFeeAmount] => 0.0012169995
     * [withdrawFeeCurrency] => btc
     * [withdrawFeeCurrencyId] => 2
     * [withdrawMinAmount] => 0.00017
     * [depositFeeRate] => 0
     * )
     * )
     * )
     *
     * @return mixed|string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getSupportCurrency()
    {
        try {
            $request = self::auth()->request('GET', 'wallet/support/currency');

            return Json::decode($request->getBody()->getContents());
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * @param $symbol
     * @param $limit
     * @return mixed|string|null
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getOrder($symbol, $limit = 100)
    {
        try {
            $request = self::auth()->request('GET', "depth?symbol={$symbol}&limit={$limit}");

            return Json::decode($request->getBody()->getContents());
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * @return Client
     */
    final public static function auth(): Client
    {
        return new Client([
            'base_uri' => 'https://sapi.xt.com/v4/public/',
            'timeout' => 15,
        ]);
    }
}
