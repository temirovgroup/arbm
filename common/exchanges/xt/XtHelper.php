<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\xt;

use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\exchanges\helpers\ExchangeHelper;
use common\helpers\ArrayHelper;
use Yii;
use yii\helpers\Json;

class XtHelper extends ExchangeHelperAbstract
{
    /**
     * Название сервиса
     */
    const SERVICE = 'Xt';
    /**
     * Название тикеров
     */
    const TICKER_NAME = 'XtTickers';

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
                self::$allCurrencyList = Yii::$app->getCache()->getOrSet('xtCurrencyList', function () {
                    return XtService::getSupportCurrency();
                }, 60);
            }

            self::$currencyListToUsdt = array_reduce(self::$allCurrencyList['result'], function ($items, $item) use ($allTickersToUsdt) {
                foreach ($allTickersToUsdt as $ticker) {
                    $symbolName = explode('_', $ticker['symbol']);
                    $symbolNameUpper = mb_strtoupper($symbolName[0]);
                    $itemCurrency = mb_strtoupper($item['currency']);

                    if ($symbolNameUpper === $itemCurrency) {
                        $items[$itemCurrency] = $item;
                    }
                }

                return $items;
            }, []);
        }

        return self::$currencyListToUsdt;
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
     * @param string $symbolName
     * @return array|mixed
     */
    public static function getChainsByCurrency(string $symbolName)
    {
        self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['supportChains'], function ($items, $item) {
                if ($item['withdrawEnabled']) {
                    $items[] = [
                        parent::WITHDRAW_FEE_KEY => $item['withdrawFeeAmount'] ?? 0,
                        'name' => self::getAlternativeChainName($item['chain']),
                        'isWithdrawEnabled' => $item['withdrawEnabled'] ?? null,
                        'depositEnabled' => $item['depositEnabled'] ?? null,
                        'withdrawFeeAmount' => $item['withdrawFeeAmount'] ?? null,
                        'withdrawFeeCurrency' => $item['withdrawFeeCurrency'] ?? null,
                        'withdrawFeeCurrencyId' => $item['withdrawFeeCurrencyId'] ?? null,
                        'withdrawMinAmount' => $item['withdrawMinAmount'] ?? null,
                        'depositFeeRate' => $item['depositFeeRate'] ?? null,
                    ];
                }

                return $items;
            }, []);
        }

        return [];
    }

    /**
     * @param string $symbolName
     * @return array|mixed
     */
    public static function getChainsNameByCurrency(string $symbolName)
    {
        self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['supportChains'], function ($items, $item) {
                if ($item['withdrawEnabled']) {
                    $items[] = self::getAlternativeChainName($item['chain']);
                }

                return $items;
            }, []);
        }

        return [];
    }

    public static function getTickersDataForCompare(array $tickers): array
    {
        return array_reduce($tickers, function ($items, $item) {
            $symbol = str_ireplace('_', '-', $item['symbol']);
            $symbolName = explode('-', $symbol);

            if (!empty($chainsName = self::getChainsNameByCurrency($symbolName[0]))) {
                $chains = self::getChainsByCurrency($symbolName[0]);

                foreach ($chains as $chain) {
                    if (in_array($chain['name'], $chainsName)) {
                        if ($chain['isWithdrawEnabled']) {
                            $items[$symbol] = [
                                'class' => self::class,
                                'service' => self::SERVICE,
                                'source' => self::TICKER_NAME,
                                'symbolName' => $symbol,
                                'symbolNameOrigin' => $item['symbol'],
                                'last' => $item['last'],
                                self::QV_24 => $item['volume'],
                                'symbol' => $symbolName[0],
                                'chains' => $chains,
                                'chainsName' => $chainsName,
//                    'detail' => $currencyListToUsdt[$symbolName[0]],
                                'hasFeeData' => true,
                                'item' => $item,
                                'takerFeeRate' => 0.002,
                                'makerFeeRate' => 0.002,
                            ];

                            break;
                        }
                    }
                }
            }

            return $items;
        }, []);
    }

    /**
     * @param $symbol
     * @param $limit
     * @return array|array[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getTradeRecent($symbol, $limit = 100): array
    {
        $request = XtService::auth()->request('GET', "trade/recent?symbol={$symbol}&limit={$limit}");
        $tradeHistories = Json::decode($request->getBody()->getContents());

        return array_map(function ($item) {
            return [
                'id_time' => $item['i'],
                'time' => $item['t'],
                'price' => $item['p'],
                'quantity' => $item['q'],
                'volume' => $item['v'],
                'is_buyerMaker' => $item['b'],
            ];
        }, $tradeHistories['result']);
    }

    /**
     * Получить последние сделки по валютной паре
     * @param string $symbol
     * @param int $perSecond
     * @return array|array[]
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getTradeHistories(string $symbol, int $perSecond = 120): array
    {
        $tradeHistories = self::getTradeRecent($symbol);

        $tradeInfo = [
            'sell' => [],
            'buy' => [],
        ];

        foreach ($tradeHistories as $tradeHistory) {
            if (($tradeHistory['time'] / 1000) > (time() - $perSecond)) {
                $tradeData = [
                    'price' => $tradeHistory['price'],
                    'amount' => $tradeHistory['quantity'] * $tradeHistory['price'],
                ];

                if (!$tradeHistory['is_buyerMaker']) {
                    $tradeInfo['sell'][] = $tradeData;
                }

                if ($tradeHistory['is_buyerMaker']) {
                    $tradeInfo['buy'][] = $tradeData;
                }
            }
        }

        return $tradeInfo;
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected static function setAllTickersToUsdt(): void
    {
        $symbols = XtService::getTickers();

        self::$allTickersToUsdt = array_reduce($symbols['result'], function ($items, $item) {
            if (mb_stripos($item['s'], 'USDT')) {
                $items[] = [
                    'symbol' => mb_strtoupper($item['s']),
                    'update_time' => $item['t'],
                    'change_value' => $item['cv'],
                    'change_rate' => $item['cr'],
                    'open' => $item['o'],
                    'low' => $item['l'],
                    'high' => $item['h'],
                    'last' => $item['c'],
                    'quantity' => $item['q'],
                    'volume' => $item['v'],
                    'asks_sell_one_price' => $item['ap'],
                    'asks_sell_one_quantity' => $item['aq'],
                    'bids_buy_one_price' => $item['bp'],
                    'bids_buy_one_quantity' => $item['bq'],
                ];
            }

            return $items;
        }, []);
    }

    /**
     * @param array $profitTicker
     * @param array $orders
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected static function setOrders(array $profitTicker, array &$orders): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        if (empty($orders[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['bids']) ||
            empty($orders[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['asks'])) {
            $orderBook = XtService::getOrder($profitTicker[self::TICKER_NAME]['symbolNameOrigin']);
            $orderBook = $orderBook['result'];

            if ($profitTickerIndexKey[0]['source'] === self::TICKER_NAME) {
                $bids = $orderBook['bids'];
                arsort($bids);

                $orders['bids'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $bids;
            } elseif ($profitTickerIndexKey[1]['source'] === self::TICKER_NAME) {
                $asks = $orderBook['asks'];
                sort($asks);

                $orders['asks'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $asks;
            }
        }
    }

    /**
     * @param string $symbol
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public static function getOrdersBySymbol(string $symbol): array
    {
        $orders = [];

        if (empty($orders[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['bids']) ||
            empty($orders[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['asks'])) {
            $orderBook = XtService::getOrder($symbol);
            $orderBook = $orderBook['result'];

            $bids = $orderBook['bids'];
            arsort($bids);

            $orders['bids'][self::getOrderItemName($symbol)] = $bids;
            $asks = $orderBook['asks'];
            sort($asks);

            $orders['asks'][self::getOrderItemName($symbol)] = $asks;
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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
