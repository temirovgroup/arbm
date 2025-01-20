<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\bitmart;

use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\helpers\ArrayHelper;
use Yii;

class BitmartHelper extends ExchangeHelperAbstract
{
    /**
     * Название сервиса
     */
    const SERVICE = 'Bitmart';
    /**
     * Название тикеров
     */
    const TICKER_NAME = 'bitmartTickers';

    const ALTERNATIVE_CHAIN_NAME = [
        'Hedera Mainnet' => 'HBAR',
        'LINEA-ETH' => 'LINEA',
        'BSC' => 'BEP20',
        'NIBI' => 'NIBIRU',
        'BCH' => 'BCHN',
        'ERG' => 'ERGO',
    ];

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
                self::$allCurrencyList = Yii::$app->getCache()->getOrSet('bitmartCurrencyList', function () {
                    return BitmartService::account()->getCurrencies();
                }, 60);
            }

            self::$currencyListToUsdt = array_reduce(self::$allCurrencyList['response']->data->currencies, function ($items, $item) use ($allTickersToUsdt) {
                foreach ($allTickersToUsdt as $ticker) {
                    $symbolName = explode('_', $ticker['symbol']);

                    if ($symbolName[0] === $item->currency) {
                        $items[$item->currency] = $item;
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

        if (isset(self::$currencyListToUsdt[$symbolName]) && self::$currencyListToUsdt[$symbolName]->withdraw_enabled) {
            return [
                [
                    parent::WITHDRAW_FEE_KEY => self::$currencyListToUsdt[$symbolName]->withdraw_minfee,
                    'name' => self::getAlternativeChainName(self::$currencyListToUsdt[$symbolName]->network),
                    'currency' => self::$currencyListToUsdt[$symbolName]->currency,
                    'contract_address' => self::$currencyListToUsdt[$symbolName]->contract_address,
                    'withdraw_enabled' => self::$currencyListToUsdt[$symbolName]->withdraw_enabled,
                    'deposit_enabled' => self::$currencyListToUsdt[$symbolName]->deposit_enabled,
                    'withdraw_minsize' => self::$currencyListToUsdt[$symbolName]->withdraw_minsize,
                ],
            ];
        }

        return [];
    }

    /**
     * @param string $symbolName
     * @return array
     */
    public static function getChainsNameByCurrency(string $symbolName)
    {
        self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName]) && self::$currencyListToUsdt[$symbolName]->withdraw_enabled) {
            return [mb_strtoupper(self::$currencyListToUsdt[$symbolName]->network)];
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
            $symbol = str_replace('_', '-', $item['symbol']);
            $symbolName = explode('-', $symbol);
            $chains = self::getChainsByCurrency($symbolName[0]);

            $items[$symbol] = [
                'class' => self::class,
                'service' => self::SERVICE,
                'source' => self::TICKER_NAME,
                'symbolName' => $symbol,
                'symbolNameOrigin' => $item['symbol'],
                'last' => $item['last'],
                self::QV_24 => $item['qv_24h'],
                'symbol' => $symbolName[0],
                'chainsName' => self::getChainsNameByCurrency($symbolName[0]),
                'chains' => $chains,
                'item' => $item,
            ];

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
     * @param array $symbolPairs
     * @return void
     */
    public static function setFeeDataToProfitSymbolPairs(array &$symbolPairs)
    {
        $symbols = Yii::$app->getCache()->getOrSet('bitmartSymbol', function () {
            $symbols = BitmartService::account()->getBasicFeeRate();

            return $symbols['response']->data;
        }, 60);

        foreach ($symbolPairs as $key => $symbolPair) {
            if (!empty($symbolPairs[$key][self::TICKER_NAME])) {
                $symbolPairs[$key][self::TICKER_NAME]['takerFeeRate'] = $symbols->taker_fee_rate_A;
                $symbolPairs[$key][self::TICKER_NAME]['makerFeeRate'] = $symbols->maker_fee_rate_A;
            }
        }
    }

    /**
     * @param $symbol
     * @param $limit
     * @return array|array[]
     */
    public static function getV3Trades($symbol, $limit = 100): array
    {
        $tradeHistories = BitmartService::spot()->getV3Trades($symbol, 100);
        $tradeHistories = $tradeHistories['response']->data;

        return array_map(function ($item) {
            return [
                'symbol' => $item[0],
                'ts' => $item[1],
                'price' => $item[2],
                'size' => $item[3],
                'side' => $item[4],
            ];
        }, $tradeHistories);
    }

    /**
     * Получить последние сделки по валютной паре
     * @param string $symbol
     * @param int $perSecond
     * @return array|array[]
     */
    public static function getTradeHistories(string $symbol, int $perSecond = 120): array
    {
        $tradeInfo = [
            'sell' => [],
            'buy' => [],
        ];

        foreach (self::getV3Trades($symbol) as $tradeHistory) {
            if (($tradeHistory['ts'] / 1000) > (time() - $perSecond)) {
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
     * @return void
     */
    protected static function setAllTickersToUsdt(): void
    {
        $symbols = BitmartService::spot()->getV3Tickers();
        $symbols = $symbols['response'];

        self::$allTickersToUsdt = array_reduce($symbols->data, function ($items, $item) {
            /**
             * @var $item array
             * [
             * "BTC_USDT",  // symbol
             * "30000.00",  // last
             * "582.08066", // v_24h
             * "4793098.48", // qv_24h
             * "28596.30", // open_24h
             * "31012.44", // high_24h
             * "12.44", // low_24h
             * "0.04909", // fluctuation
             * "30000", // bid_px
             * "1",  // bid_sz
             * "31012.44",  // ask_px
             * "69994.75267", // ask_sz
             * "1691671091933" // ts
             * ],
             */
            $symbolName = explode('_', $item[0]);

            if ($symbolName[1] === 'USDT') {
                $items[] = [
                    'symbol' => $item[0],
                    'last' => $item[1],
                    'v_24h' => $item[2],
                    'qv_24h' => $item[3],
                    'open_24h' => $item[4],
                    'high_24h' => $item[5],
                    'low_24h' => $item[6],
                    'fluctuation' => $item[7],
                    'bid_px' => $item[8],
                    'bid_sz' => $item[9],
                    'ask_px' => $item[10],
                    'ask_sz' => $item[11],
                    'ts' => $item[12],
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
            self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']] = BitmartService::spot()->getV3Book($profitTicker[self::TICKER_NAME]['symbolNameOrigin'], 50);
        }

        self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']] = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['response'];

        if ($profitTickerIndexKey[0]['source'] === self::TICKER_NAME) {
            $bids = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]->data->bids;
            arsort($bids);

            $orders['bids'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $bids;
        } elseif ($profitTickerIndexKey[1]['source'] === self::TICKER_NAME) {
            $asks = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]->data->asks;
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
            self::$orderBook[$symbol] = BitmartService::spot()->getV3Book($symbol, 50);
        }

        self::$orderBook[$symbol] = self::$orderBook[$symbol]['response'];

        $bids = self::$orderBook[$symbol]->data->bids;
        arsort($bids);

        $asks = self::$orderBook[$symbol]->data->asks;
        sort($asks);

        $orders['asks'] = $bids;
        $orders['bids'] = $asks;

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
     * @param $profitTickerIndexKey
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

    /**
     * @param string $chainName
     * @return array|false|string|string[]|null
     */
    protected static function getAlternativeChainName(string $chainName)
    {
        return mb_strtoupper(self::ALTERNATIVE_CHAIN_NAME[$chainName] ?? $chainName);
    }
}
