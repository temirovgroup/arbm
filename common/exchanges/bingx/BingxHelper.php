<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\bingx;

use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\helpers\ArrayHelper;
use Yii;
use yii\helpers\Json;

class BingxHelper extends ExchangeHelperAbstract
{
    /**
     * Название сервиса
     */
    const SERVICE = 'BingX';
    /**
     * Название тикеров
     */
    const TICKER_NAME = 'bingxTickers';

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
     * @param $allTickersToUsdt
     * @return array|mixed|null
     */
    public static function getCurrencyListToUsdt($allTickersToUsdt = null)
    {
        if (is_null(self::$currencyListToUsdt)) {
            // Если не передано в параметре получаем сами
            if (is_null($allTickersToUsdt)) {
                $allTickersToUsdt = self::getAllTickersToUsdt();
            }

            if (is_null(self::$allCurrencyList)) {
                self::$allCurrencyList = Yii::$app->getCache()->getOrSet('bingxCurrencyList', function () {
                    $currency = BingxService::getCurrency();

                    return Json::decode($currency['data']);
                }, 60);
            }

            self::$currencyListToUsdt = array_reduce(self::$allCurrencyList['data'], function ($items, $item) use ($allTickersToUsdt) {
                foreach ($item['networkList'] as $network) {
                    foreach ($allTickersToUsdt as $ticker) {
                        $symbolName = explode('-', $ticker['symbol']);

                        if ($symbolName[0] === $network['name']) {
                            $items[$network['name']] = $network;
                        }
                    }
                }

                return $items;
            }, []);
        }

        return self::$currencyListToUsdt;
    }

    /**
     * @param string $symbolName
     * @return array
     */
    public static function getChainsByCurrency(string $symbolName)
    {
        self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName]) && self::$currencyListToUsdt[$symbolName]['withdrawEnable']) {
            return [
                [
                    parent::WITHDRAW_FEE_KEY => self::$currencyListToUsdt[$symbolName]['withdrawFee'] ?? null,
                    'name' => mb_strtoupper(self::$currencyListToUsdt[$symbolName]['network']),
                    'isDefault' => self::$currencyListToUsdt[$symbolName]['isDefault'] ?? null,
                    'minConfirm' => self::$currencyListToUsdt[$symbolName]['minConfirm'] ?? null,
                    'withdrawEnable' => self::$currencyListToUsdt[$symbolName]['withdrawEnable'] ?? null,
                    'depositEnable' => self::$currencyListToUsdt[$symbolName]['depositEnable'] ?? null,
                    'withdrawMax' => self::$currencyListToUsdt[$symbolName]['withdrawMax'] ?? null,
                    'withdrawMin' => self::$currencyListToUsdt[$symbolName]['withdrawMin'] ?? null,
                    'depositMin' => self::$currencyListToUsdt[$symbolName]['depositMin'] ?? null,
                ]
            ];
        }

        return [];
    }

    /**
     * @param string $symbolName
     * @return array
     */
    public static function getChainsNameByCurrency(string $symbolName): array
    {
        self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName]) && self::$currencyListToUsdt[$symbolName]['withdrawEnable']) {
            return [mb_strtoupper(self::$currencyListToUsdt[$symbolName]['network'])];
        }

        return [];
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

                if (!empty($chains)) {
                    $items[$symbol] = [
                        'class' => self::class,
                        'service' => self::SERVICE,
                        'source' => self::TICKER_NAME,
                        'symbolName' => $symbol,
                        'symbolNameOrigin' => $symbol,
                        'last' => $item['last'],
                        self::QV_24 => $item['quoteVolume'],
                        'symbol' => $symbolName[0],
                        'chains' => $chains,
                        'chainsName' => $chainsName,
//                    'detail' => $currencyListToUsdt[$symbolName[0]],
                        'hasFeeData' => true,
                        'item' => $item,
                        'takerFeeRate' => 0.001,
                        'makerFeeRate' => 0.001,
                    ];
                }
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
     * Получить последние сделки по валютной паре
     * @param string $symbol
     * @param int $limit
     * @return array|array[]
     */
    public static function getTradeHistories(string $symbol, int $perSecond = 120): array
    {
        $tradeHistories = BingxService::doRequest('/openApi/spot/v1/market/trades', 'GET', [
            'symbol' => $symbol,
        ]);

        $tradeHistories = Json::decode($tradeHistories['data']);

        $tradeInfo = [
            'sell' => [],
            'buy' => [],
        ];

        foreach ($tradeHistories['data'] as $tradeHistory) {
            if (($tradeHistory['time'] / 1000) > (time() - $perSecond)) {
                $tradeData = [
                    'price' => $tradeHistory['price'],
                    'amount' => $tradeHistory['qty'] * $tradeHistory['price'],
                ];

                if ($tradeHistory['buyerMaker']) {
                    $tradeInfo['buy'][] = $tradeData;
                } else {
                    $tradeInfo['sell'][] = $tradeData;
                }
            }
        }

        return $tradeInfo;
    }

    /**
     * @return void
     */
    protected static function setAllTickersToUsdt(): void
    {
        $symbols = BingxService::getTickers();
        $symbols = Json::decode($symbols['data']);

        self::$allTickersToUsdt = array_reduce($symbols['data'], function ($items, $item) {
            if (mb_stripos($item['symbol'], 'USDT')) {
                $items[] = [
                    'symbol' => mb_strtoupper($item['symbol']),
                    'priceChange' => $item['priceChange'],
                    'priceChangePercent' => $item['priceChangePercent'],
                    'last' => $item['lastPrice'],
                    'lastQty' => $item['lastQty'],
                    'highPrice' => $item['highPrice'],
                    'lowPrice' => $item['lowPrice'],
                    'volume' => $item['volume'],
                    'quoteVolume' => $item['quoteVolume'],
                    'openPrice' => $item['openPrice'],
                    'openTime' => $item['openTime'],
                    'closeTime' => $item['closeTime'],
                    'askPrice' => $item['askPrice'],
                    'askQty' => $item['askQty'],
                ];
            }

            return $items;
        }, []);
    }

    /**
     * @param $profitTicker
     * @param array $orders
     * @return void
     */
    protected static function setOrders($profitTicker, array &$orders): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        if (empty(self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']])) {
            $orderBook = BingxService::getDepth($profitTicker[self::TICKER_NAME]['symbolNameOrigin']);
            $orderBook = Json::decode($orderBook['data']);
            $orderBook = $orderBook['data'];

            self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']] = $orderBook;
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
     */
    public static function getOrdersBySymbol(string $symbol): array
    {
        $orders = [];

        if (empty(self::$orderBook[$symbol])) {
            $orderBook = BingxService::getDepth($symbol);
            $orderBook = Json::decode($orderBook['data']);
            $orderBook = $orderBook['data'];

            self::$orderBook[$symbol] = $orderBook;
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
