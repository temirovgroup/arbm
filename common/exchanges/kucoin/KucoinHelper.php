<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\kucoin;

use Codeception\Coverage\Subscriber\Printer;
use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\exchanges\helpers\ExchangeHelper;
use common\helpers\ArrayHelper;
use KuCoin\SDK\Exceptions\BusinessException;
use KuCoin\SDK\Exceptions\HttpException;
use KuCoin\SDK\Exceptions\InvalidApiUriException;
use Yii;

class KucoinHelper extends ExchangeHelperAbstract
{
    /**
     * Название сервиса
     */
    const SERVICE = 'KuCoin';
    /**
     * Название тикеров
     */
    const TICKER_NAME = 'kucoinTickers';

    protected static ?array $allTickersToUsdt = null;
    /**
     * @var array|null
     */
    protected static ?array $allCurrencyList = null;
    /**
     * @var array|null
     */
    protected static ?array $currencyListToUsdt = null;
    /**
     * @var array
     */
    protected static array $orderBook = [];

    /**
     * @return void
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    protected static function setAllTickersToUsdt(): void
    {
        $symbols = KucoinService::symbol()->getAllTickers();

        self::$allTickersToUsdt = array_reduce($symbols['ticker'], function ($items, $item) {
            if (mb_stripos($item['symbol'], 'USDT')) {
                $items[] = $item;
            }

            return $items;
        }, []);
    }

    /**
     * Получить валюты в паре USDT (с инф. о сети и прочими данными)
     * @param $allTickersToUsdt
     * @return mixed
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    public static function getCurrencyListToUsdt($allTickersToUsdt = null)
    {
        if (is_null(self::$currencyListToUsdt)) {
            // Если не передано в параметре получаем сами
            if (is_null($allTickersToUsdt)) {
                $allTickersToUsdt = self::getAllTickersToUsdt();
            }

            if (is_null(self::$allCurrencyList)) {
                self::$allCurrencyList = Yii::$app->getCache()->getOrSet('kucoinCurrencyList', function () {
                    return KucoinService::currency()->getList();
                }, 60);
            }

            self::$currencyListToUsdt = array_reduce(self::$allCurrencyList, function ($items, $item) use ($allTickersToUsdt) {
                foreach ($allTickersToUsdt as $ticker) {
                    $symbolName = explode('-', $ticker['symbol']);

                    if ($symbolName[0] === $item['currency']) {
                        $items[$item['currency']] = $item;
                    }
                }

                return $items;
            }, []);
        }

        return self::$currencyListToUsdt;
    }

    /**
     * @param array $tickers
     * @return array
     */
    public static function getTickersDataForCompare(array $tickers): array
    {
        return array_reduce($tickers, function ($items, $item) {
            $symbol = $item['symbol'];
            $symbolName = explode('-', $symbol);

            if (!empty($chainsName = self::getChainsNameByCurrency($symbolName[0]))) {
                $chains = self::getChainsByCurrency($symbolName[0]);

                $items[$symbol] = [
                    'class' => self::class,
                    'service' => self::SERVICE,
                    'source' => self::TICKER_NAME,
                    'symbolName' => $symbol,
                    'symbolNameOrigin' => $symbol,
                    'last' => $item['last'],
                    self::QV_24 => $item['volValue'],
                    'symbol' => $symbolName[0],
                    'chains' => $chains,
                    'chainsName' => $chainsName,
//                    'detail' => $currencyListToUsdt[$symbolName[0]],
                    'hasFeeData' => true,
                    'item' => $item,
                    'takerFeeRate' => $item['takerFeeRate'],
                    'makerFeeRate' => $item['makerFeeRate'],
                ];
            }

            return $items;
        }, []);
    }

    /**
     * @param array $chains
     * @param string $chainName
     * @return mixed
     */
    public static function getFee(array $chains, string $chainName)
    {
        $chains = ArrayHelper::index($chains, 'name');

        return $chains[$chainName][ExchangeHelperAbstract::WITHDRAW_FEE_KEY];
    }

    /**
     * Получить информацию о сетях по названию валюты
     * @param string $symbolName
     * @return mixed
     */
    public static function getChainsByCurrency(string $symbolName)
    {
        self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['chains'], function ($items, $item) {
                if ($item['isWithdrawEnabled']) {
                    $items[] = [
                        parent::WITHDRAW_FEE_KEY => $item['withdrawalMinFee'] ?? 0,
                        'name' => mb_strtoupper($item['chainName']),
                        'withdrawalMinSize' => $item['withdrawalMinSize'] ?? null,
                        'depositMinSize' => $item['depositMinSize'] ?? null,
                        'withdrawFeeRate' => $item['withdrawFeeRate'] ?? null,
                        'isWithdrawEnabled' => $item['isWithdrawEnabled'] ?? null,
                        'isDepositEnabled' => $item['isDepositEnabled'] ?? null,
                        'confirms' => $item['confirms'] ?? null,
                        'preConfirms' => $item['preConfirms'] ?? null,
                        'contractAddress' => $item['contractAddress'] ?? null,
                    ];
                }

                return $items;
            }, []);
        }

        return [];
    }

    /**
     * Получить названия сетей по названию валюты
     * @param string $symbolName
     * @return array|mixed
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    public static function getChainsNameByCurrency(string $symbolName)
    {
        self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['chains'], function ($items, $item) {
                if ($item['isWithdrawEnabled']) {
                    $items[] = mb_strtoupper($item['chainName']);

                    return $items;
                }
            }, []);
        }

        return [];
    }

    /**
     * Получить последние сделки по валютной паре
     * @param string $symbol
     * @param int $perSecond
     * @return array|array[]
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    public static function getTradeHistories(string $symbol, int $perSecond = 120): array
    {
        $tradeHistories = KucoinService::symbol()->getTradeHistories($symbol);

        $tradeInfo = [
            'sell' => [],
            'buy' => [],
        ];

        foreach ($tradeHistories as $tradeHistory) {
            if (intval($tradeHistory['time'] / 1000000000) > (time() - $perSecond)) {
                $tradeData = [
                    'price' => $tradeHistory['price'],
                    'amount' => $tradeHistory['size'] * $tradeHistory['price'],
                ];

                if ($tradeHistory['side'] === 'sell') {
                    $tradeInfo['sell'][] = $tradeData;
                }

                if ($tradeHistory['side'] === 'buy') {
                    $tradeInfo['buy'][] = $tradeData;
                }
            }
        }

        return $tradeInfo;
    }

    /**
     * @param array $profitTicker
     * @param array $orders
     * @return void
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    protected static function setOrders(array $profitTicker, array &$orders): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        if (empty(self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']])) {
            self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']] = KucoinService::symbol()->getAggregatedPartOrderBook($profitTicker[self::TICKER_NAME]['symbolNameOrigin'], 100);
        }

        if ($profitTickerIndexKey[0]['source'] === self::TICKER_NAME) {
            $bids = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['bids'];
            arsort($bids);

            $orders['bids'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $bids;
        } elseif ($profitTickerIndexKey[1]['source'] === self::TICKER_NAME) {
            $asks = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['asks'];
            sort($asks);

            $orders['asks'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $asks;
        }
    }

    /**
     * @param string $symbol
     * @return array
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    public static function getOrdersBySymbol(string $symbol): array
    {
        $orders = [];

        if (empty(self::$orderBook[$symbol])) {
            self::$orderBook[$symbol] = KucoinService::symbol()->getAggregatedPartOrderBook($symbol, 100);
        }

        $orders['asks'] = self::$orderBook[$symbol]['asks'];
        $orders['bids'] = self::$orderBook[$symbol]['bids'];

        if (empty($orders['asks']) || empty($orders['bids'])) {
            return [];
        }

        return $orders;
    }

    /**
     * @param array $profitTicker
     * @param array $allOrders
     * @param array $orders
     * @return void
     */
    protected static function setOrder(array $profitTicker, array $allOrders, array &$orders): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        if ($profitTickerIndexKey[0]['source'] === self::TICKER_NAME) {
            $orders['bids'] = $allOrders['bids'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])];
        } elseif ($profitTickerIndexKey[1]['source'] === self::TICKER_NAME) {
            $orders['asks'] = $allOrders['asks'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])];
        }
    }

    /**
     * @param array $profitTickerIndexKey
     * @param array $sourceName
     * @return void
     */
    protected static function setSourceName(array $profitTickerIndexKey, array &$sourceName): void
    {
        if ($profitTickerIndexKey[0]['service'] === self::SERVICE) {
            $sourceName['bids'] = self::SERVICE;
        } elseif ($profitTickerIndexKey[1]['service'] === self::SERVICE) {
            $sourceName['asks'] = self::SERVICE;
        }
    }

    /**
     * @param array $profitTickerIndexKey
     * @param array $qv_24
     * @return void
     */
    protected static function setQV24(array $profitTickerIndexKey, array &$qv_24): void
    {
        if ($profitTickerIndexKey[0]['service'] === self::SERVICE) {
            $qv_24['bids'] = $profitTickerIndexKey[0][ExchangeHelperAbstract::QV_24];
        } elseif ($profitTickerIndexKey[1]['service'] === self::SERVICE) {
            $qv_24['asks'] = $profitTickerIndexKey[1][ExchangeHelperAbstract::QV_24];
        }
    }

    /**
     * @param array $profitTickerIndexKey
     * @param array $tradeHistories
     * @return void
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    protected static function getTradeHistory(array $profitTickerIndexKey, array &$tradeHistories): void
    {
        if ($profitTickerIndexKey[0]['service'] === self::SERVICE) {
            $tradeHistory = self::getTradeHistories($profitTickerIndexKey[0]['symbolNameOrigin']);

            if (empty($prices = ArrayHelper::getColumn($tradeHistory['sell'], 'price'))) {
                $tradeHistories['buy'] = [
                    'avgPrice' => 0,
                    'amount' => 0,
                ];
            } else {
                $tradeHistories['buy'] = [
                    'avgPrice' => ArrayHelper::arraySumAvg($prices),
                    'amount' => ArrayHelper::arraySumBcadd(ArrayHelper::getColumn($tradeHistory['sell'], 'amount'))
                ];
            }
        } elseif ($profitTickerIndexKey[1]['service'] === self::SERVICE) {
            $tradeHistory = self::getTradeHistories($profitTickerIndexKey[1]['symbolNameOrigin']);

            if (empty($prices = ArrayHelper::getColumn($tradeHistory['buy'], 'price'))) {
                $tradeHistories['sell'] = [
                    'avgPrice' => 0,
                    'amount' => 0,
                ];
            } else {
                $tradeHistories['sell'] = [
                    'avgPrice' => ArrayHelper::arraySumAvg($prices),
                    'amount' => ArrayHelper::arraySumBcadd(ArrayHelper::getColumn($tradeHistory['buy'], 'amount'))
                ];
            }
        }
    }
}
