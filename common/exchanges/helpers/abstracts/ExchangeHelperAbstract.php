<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\helpers\abstracts;

abstract class ExchangeHelperAbstract
{
    const WITHDRAW_FEE_KEY = 'withdrawFee';
    const WITHDRAW_FEE_PERCENT_KEY = 'withdrawFeePercent';
    const QV_24 = 'qv_24';

    protected static ?array $allTickersToUsdt = null;

    /**
     * Получить валюты в паре USDT
     * @param $allTickersToUsdt
     * @return mixed
     */
    abstract public static function getCurrencyListToUsdt($allTickersToUsdt = null);

    /**
     * Получить информацию о сетях по названию валюты
     * @param string $symbolName
     * @return mixed
     */
    abstract public static function getChainsByCurrency(string $symbolName);

    /**
     * Получить названия сетей по названию валюты
     * @param string $symbolName
     * @return mixed
     */
    abstract public static function getChainsNameByCurrency(string $symbolName);

    /**
     * Получить данные для сравнения
     * @param array $tickers
     * @return array
     */
    abstract public static function getTickersDataForCompare(array $tickers): array;

    /**
     * @param array $chains
     * @param string $chainName
     * @return mixed
     */
    abstract public static function getFee(array $chains, string $chainName);

    /**
     * @return void
     */
    abstract protected static function setAllTickersToUsdt(): void;

    /**
     * @param array $profitTicker
     * @param array $orders
     * @return void
     */
    abstract protected static function setOrders(array $profitTicker, array &$orders): void;

    /**
     * @param array $profitTicker
     * @param array $allOrders
     * @param array $orders
     * @return void
     */
    abstract protected static function setOrder(array $profitTicker, array $allOrders, array &$orders): void;

    /**
     * @param string $symbol
     * @return array
     */
    abstract public static function getOrdersBySymbol(string $symbol): array;

    /**
     * @param array $profitTickerIndexKey
     * @param array $sourceName
     * @return void
     */
    abstract protected static function setSourceName(array $profitTickerIndexKey, array &$sourceName): void;

    /**
     * @param array $profitTickerIndexKey
     * @param array $qv_24
     * @return void
     */
    abstract protected static function setQV24(array $profitTickerIndexKey, array &$qv_24): void;

    /**
     * @param array $profitTickerIndexKey
     * @param array $sourceName
     * @return void
     */
    abstract protected static function getTradeHistory(array $profitTickerIndexKey, array &$tradeHistories): void;

    /**
     * Получить данные по всем парам *_USDT
     * @return array|null
     */
    public static function getAllTickersToUsdt()
    {
        if (is_null(static::$allTickersToUsdt)) {
            static::setAllTickersToUsdt();
        }

        return static::$allTickersToUsdt;
    }

    /**
     * @param array $profitTicker
     * @param array $orders
     * @return void
     */
    public static function setOrdersTheir(array $profitTicker, array &$orders): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        $profitTickerIndexKey[0]['class']::setOrders($profitTicker, $orders);
        $profitTickerIndexKey[1]['class']::setOrders($profitTicker, $orders);
    }

    /**
     * @param array $profitTicker
     * @param array $allOrders
     * @return array
     */
    public static function setOrderTheir(array $profitTicker, array $allOrders, array &$orders): array
    {
        $profitTickerIndexKey = array_values($profitTicker);

        $profitTickerIndexKey[0]['class']::setOrder($profitTicker, $allOrders, $orders);
        $profitTickerIndexKey[1]['class']::setOrder($profitTicker, $allOrders, $orders);

        return $orders;
    }

    /**
     * @param array $profitTicker
     * @param array $tradeHistories
     * @return void
     */
    public static function setTradeHistories(array $profitTicker, array &$tradeHistories)
    {
        $profitTickerIndexKey = array_values($profitTicker);

        $profitTickerIndexKey[0]['class']::getTradeHistory($profitTickerIndexKey, $tradeHistories);
        $profitTickerIndexKey[1]['class']::getTradeHistory($profitTickerIndexKey, $tradeHistories);
    }

    /**
     * @param array $profitTicker
     * @param array $sourceName
     * @return void
     */
    public static function setSourceNameTheir(array $profitTicker, array &$sourceName): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        $profitTickerIndexKey[0]['class']::setSourceName($profitTickerIndexKey, $sourceName);
        $profitTickerIndexKey[1]['class']::setSourceName($profitTickerIndexKey, $sourceName);
    }

    /**
     * @param array $profitTicker
     * @param array $qv_24
     * @return void
     */
    public static function setQV24Their(array $profitTicker, array &$qv_24): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        $profitTickerIndexKey[0]['class']::setQV24($profitTickerIndexKey, $qv_24);
        $profitTickerIndexKey[1]['class']::setQV24($profitTickerIndexKey, $qv_24);
    }

    /**
     * @param string $symbolName
     * @return string
     */
    public static function getOrderItemName(string $symbolName): string
    {
        return static::TICKER_NAME . $symbolName;
    }
}
