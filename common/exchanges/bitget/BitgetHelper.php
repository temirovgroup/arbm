<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\bitget;

use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\helpers\ArrayHelper;
use KuCoin\SDK\Exceptions\BusinessException;
use KuCoin\SDK\Exceptions\HttpException;
use KuCoin\SDK\Exceptions\InvalidApiUriException;
use Yii;

class BitgetHelper extends ExchangeHelperAbstract
{
    const SERVICE = 'Bitget';
    const TICKER_NAME = 'bitgetTickers';

    protected static ?array $allTickersToUsdt = null;
    protected static ?array $allCurrencyList = null;
    protected static ?array $currencyListToUsdt = null;
    /**
     * @var array
     */
    protected static array $orderBook = [];

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
                self::$allCurrencyList = Yii::$app->getCache()->getOrSet('bitgetCurrencyList', function () {
//                    $symbols = BiеgetService::spotV2()->publics()->getSymbols();
                    $symbols = BitgetService::spotV2()->publics()->getCoins();
                    return $symbols['data'];
                }, 60);
            }

            self::$currencyListToUsdt = array_reduce(self::$allCurrencyList, function ($items, $item) use ($allTickersToUsdt) {
                foreach ($allTickersToUsdt as $ticker) {
                    $symbolName = self::getSymbolNameMatch($ticker['symbol']);

                    if ($symbolName === $item['coin']) {
                        $items[$item['coin']] = $item;
                    }
                }

                return $items;
            }, []);
        }

        return self::$currencyListToUsdt;
    }

    /**
     * @param string $symbolName
     * @return array|mixed
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    public static function getChainsByCurrency(string $symbolName)
    {
        self::$currencyListToUsdt = self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['chains'], function ($items, $item) {
                if ($item['withdrawable']) {
                    $items[] = [
                        parent::WITHDRAW_FEE_KEY => $item['withdrawFee'],
                        'name' => mb_strtoupper($item['chain']),
                        'isWithdrawEnabled' => $item['withdrawable'] ?? null,
                        'needTag' => $item['needTag'] ?? null,
                        'rechargeable' => $item['rechargeable'] ?? null,
                        'extraWithdrawFee' => $item['extraWithdrawFee'] ?? null,
                        'depositConfirm' => $item['depositConfirm'] ?? null,
                        'withdrawConfirm' => $item['withdrawConfirm'] ?? null,
                        'minDepositAmount' => $item['minDepositAmount'] ?? null,
                        'minWithdrawAmount' => $item['minWithdrawAmount'] ?? null,
                        'browserUrl' => $item['browserUrl'] ?? null,
                        'contractAddress' => $item['contractAddress'] ?? null,
                        'withdrawStep' => $item['withdrawStep'] ?? null,
                        'withdrawMinScale' => $item['withdrawMinScale'] ?? null,
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
     * @throws BusinessException
     * @throws HttpException
     * @throws InvalidApiUriException
     */
    public static function getChainsNameByCurrency(string $symbolName)
    {
        self::$currencyListToUsdt = self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['chains'], function ($items, $item) {
                if ($item['withdrawable']) {
                    $items[] = mb_strtoupper($item['chain']);
                }

                return $items;
            }, []);
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
            $symbol = self::getSymbolNameMatch($item['symbol']) . '-USDT';
            $symbolName = explode('-', $symbol);

            if (!empty($chainsName = self::getChainsNameByCurrency($symbolName[0]))) {
                $chains = self::getChainsByCurrency($symbolName[0]);

                $items[$symbol] = [
                    'class' => self::class,
                    'service' => self::SERVICE,
                    'source' => self::TICKER_NAME,
                    'symbolName' => $symbol,
                    'symbolNameOrigin' => $item['symbol'],
                    'last' => $item['lastPr'],
                    self::QV_24 => $item['usdtVolume'],
                    'symbol' => $symbolName[0],
                    'chainsName' => $chainsName,
                    'chains' => $chains,
//                    'detail' => $currencyListToUsdt[$symbolName[0]],
                    'hasFeeData' => true,
                    'item' => $item,
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
     * @param array $symbolPairs
     * @return void
     */
    public static function setFeeDataToProfitSymbolPairs(array &$symbolPairs)
    {
        $symbols = Yii::$app->getCache()->getOrSet('bitgetSymbol', function () {
            $symbols = BitgetService::spotV2()->publics()->getSymbols();

            return $symbols['data'];
        }, 60);

        foreach ($symbols as $symbol) {
            foreach ($symbolPairs as $key => $symbolPair) {
                if (!empty($symbolPairs[$key][self::TICKER_NAME]) && $symbolPairs[$key][self::TICKER_NAME]['symbolNameOrigin'] === $symbol['symbol']) {
                    $symbolPairs[$key][self::TICKER_NAME]['takerFeeRate'] = $symbol['takerFeeRate'];
                    $symbolPairs[$key][self::TICKER_NAME]['makerFeeRate'] = $symbol['makerFeeRate'];
                }
            }
        }
    }

    /**
     * Получить последние сделки по валютной паре
     * @param string $symbol
     * @param int $perSecond
     * @return array|array[]
     */
    public static function getTradeHistories(string $symbol, int $perSecond = 120): array
    {
        $tradeHistories = BitgetService::spotV2()->market()->getFills([
            'symbol' => $symbol,
        ]);

        $tradeInfo = [
            'sell' => [],
            'buy' => [],
        ];

        foreach ($tradeHistories['data'] as $tradeHistory) {
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
        $symbols = BitgetService::spotV2()->market()->getTickers();

        self::$allTickersToUsdt = array_reduce($symbols['data'], function ($items, $item) {
            if (self::getSymbolNameMatch($item['symbol'])) {
                $items[] = $item;
            }

            return $items;
        }, []);
    }

    /**
     * @param array $profitTicker
     * @param array $orders
     * @return void
     */
    protected static function setOrders(array $profitTicker, array &$orders): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        if (empty(self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']])) {
            self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']] = BitgetService::spotV2()->market()->getOrderBook([
                'symbol' => $profitTicker[self::TICKER_NAME]['symbolNameOrigin'],
            ]);
        }

        if ($profitTickerIndexKey[0]['source'] === self::TICKER_NAME) {
            $bids = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['data']['bids'];
            arsort($bids);

            $orders['bids'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $bids;
        } elseif ($profitTickerIndexKey[1]['source'] === self::TICKER_NAME) {
            $asks = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]['data']['asks'];
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
            self::$orderBook[$symbol] = BitgetService::spotV2()->market()->getOrderBook([
                'symbol' => $symbol,
            ]);
        }

        $bids = self::$orderBook[$symbol]['data']['bids'];
        arsort($bids);

        $asks = self::$orderBook[$symbol]['data']['asks'];
        sort($asks);


        $orders['asks'] = $asks;
        $orders['bids'] = $bids;

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

    /**
     * @param $symbol
     * @return false|string
     */
    private static function getSymbolNameMatch($symbol)
    {
        if (preg_match('/USDT\z/i', $symbol)) {
            preg_match("/^([^_]*)USDT(.*)$/i", $symbol, $matchRes);
        }

        return $matchRes[1] ?? false;
    }
}
