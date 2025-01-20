<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\gate;

use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\exchanges\kucoin\KucoinHelper;
use common\helpers\ArrayHelper;
use GateApi\ApiException;
use GateApi\Model\Currency;
use GateApi\Model\CurrencyPair;
use GateApi\Model\SpotFee;
use GateApi\Model\Ticker;
use GateApi\Model\Trade;
use GateApi\Model\WithdrawStatus;
use Yii;

class GateHelper extends ExchangeHelperAbstract
{
    const SERVICE = 'Gate';
    const TICKER_NAME = 'gateTickers';

    const ALTERNATIVE_CHAIN_NAME = [
        'LINEAETH' => 'LINEA',
        'ETH' => 'ERC20',
        'BSC' => 'BEP20',
        'BNB' => 'BEP20',
        'ARBEVM' => 'ARBITRUM',
        'PDEX' => 'POLKADEX',
        'NIBI' => 'NIBIRU',
        'ICX' => 'ICON',
        'BCH' => 'BCHN',
        'CSPR' => 'CASPER',
        'BB' => 'BOUNCEBIT',
        'ERG' => 'ERGO',
    ];

    /**
     * @var array|null
     */
    protected static ?array $allTickersToUsdt = null;

    /* @var $currencyListToUsdt \GateApi\Model\Currency[] */
    protected static ?array $currencyListToUsdt = null;

    /**
     * @var array
     */
    protected static array $orderBook = [];

    /**
     * @var array
     */
    protected static array $withdrawStatusList = [];

    /**
     * @return void
     * @throws ApiException
     */
    protected static function setAllTickersToUsdt(): void
    {
        $associate_array['timezone'] = 'utc0'; // string | Timezone

        self::$allTickersToUsdt = array_reduce(GateService::spot()->listTickers($associate_array), function ($items, $item) {
            /* @var $item \GateApi\Model\Ticker */
            if (mb_strpos($item->getCurrencyPair(), 'USDT')) {
                $items[] = $item;
            }

            return $items;
        }, []);
    }

    /**
     * @return array
     */
    protected static function getListWithdrawStatus(): array
    {
        if (empty(self::$withdrawStatusList)) {
            self::$withdrawStatusList = Yii::$app->getCache()->getOrSet('gateListWithdrawStatus', function () {
                return \common\helpers\ArrayHelper::index(GateService::wallet()->listWithdrawStatus([]), 'currency');
            }, 60);
        }

        return self::$withdrawStatusList;
    }

    /**
     * Получить валюты в паре USDT (с инф. о сети и прочими данными)
     * @param $allTickersToUsdt
     * @return Currency[]|mixed|null
     */
    public static function getCurrencyListToUsdt($allTickersToUsdt = null)
    {
        if (is_null(self::$currencyListToUsdt)) {
            if (is_null($allTickersToUsdt)) {
                $allTickersToUsdt = self::getAllTickersToUsdt();
            }

            self::$currencyListToUsdt = Yii::$app->getCache()->getOrSet('gateCurrencyListToUsdt', function () use ($allTickersToUsdt) {
                return array_reduce(GateService::spot()->listCurrencies(), function ($items, $item) use ($allTickersToUsdt) {
                    /* @var $item Currency */
                    foreach ($allTickersToUsdt as $ticker) {
                        /* @var $ticker Ticker */
                        $symbolName = explode('_', $ticker->getCurrencyPair());

                        if ($symbolName[0] === $item->getCurrency()) {
                            $items[$item->getCurrency()] = $item;
                        }
                    }

                    return $items;
                }, []);
            }, 60);
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
            /* @var $item \GateApi\Model\Ticker */

            $symbol = str_replace('_', '-', $item->getCurrencyPair());
            $symbolName = explode('-', $symbol);
            $chains = self::getChainsByCurrency($symbolName[0]);

            if (!empty($chains)) {
                $items[$symbol] = [
                    'class' => self::class,
                    'service' => self::SERVICE,
                    'source' => self::TICKER_NAME,
                    'symbolName' => $symbol,
                    'symbolNameOrigin' => $item->getCurrencyPair(),
                    'last' => $item->getLast(),
                    self::QV_24 => $item->getQuoteVolume(),
                    'symbol' => $symbolName[0],
                    'chainsName' => self::getChainsNameByCurrency($symbolName[0]),
                    'chains' => $chains,
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
        $chains = \common\helpers\ArrayHelper::index($chains, 'name');

        return $chains[$chainName][ExchangeHelperAbstract::WITHDRAW_FEE_KEY];
    }

    /**
     * Пока отложено
     *
     * @param array $chains
     * @param string $chainName
     * @return mixed
     */
    public static function getFeePercent(array $chains, string $chainName)
    {
        $chains = \common\helpers\ArrayHelper::index($chains, 'name');

        return $chains[$chainName][ExchangeHelperAbstract::WITHDRAW_FEE_PERCENT_KEY];
    }

    /**
     * Получить информацию о сетях по названию валюты
     * @param string $symbolName
     * @return array
     */
    public static function getChainsByCurrency(string $symbolName)
    {
        /** @var $currencyListToUsdt Currency[] */
        $currencyListToUsdt = self::getCurrencyListToUsdt();

        /** @var $withdrawList WithdrawStatus[] */
        $withdrawList = self::getListWithdrawStatus();

        $chains = [];

        if (isset($withdrawList[$symbolName]) && isset($currencyListToUsdt[$symbolName]) &&
            empty($currencyListToUsdt[$symbolName]->getWithdrawDisabled())) {
            if (!empty($withdrawFixOnChains = $withdrawList[$symbolName]->getWithdrawFixOnChains()) && is_array($withdrawFixOnChains)) {
                foreach ($withdrawList[$symbolName]->getWithdrawFixOnChains() as $chainName => $withdrawValue) {
                    $chains[self::getAlternativeChainName($chainName)] = [
                        parent::WITHDRAW_FEE_KEY => $withdrawValue,
                        parent::WITHDRAW_FEE_PERCENT_KEY => $withdrawList[$symbolName]->getWithdrawPercent(),
                        'name' => self::getAlternativeChainName($chainName),
                    ];
                }
            }
        }

        return $chains;
    }

    /**
     * Получить названия сетей по названию валюты
     * @param string $symbolName
     * @return array
     */
    public static function getChainsNameByCurrency(string $symbolName)
    {
        $currencyListToUsdt = self::getCurrencyListToUsdt();

        if (isset($currencyListToUsdt[$symbolName]) && empty($currencyListToUsdt[$symbolName]->getWithdrawDisabled())) {
            return [self::getAlternativeChainName($currencyListToUsdt[$symbolName]->getChain())];
        }

        return [];
    }

    /**ОПТИМИЗИРОВАТЬ
     * Установить данные комиссии для валютных пар
     * @param array $symbolPairs
     * @return void
     * @throws ApiException
     */
    public static function setFeeDataToProfitSymbolPairs(array &$symbolPairs): void
    {
        if (!empty($gateTickersSymbolName = self::getBatchSpotFeeByTickers(ArrayHelper::getColumn($symbolPairs, self::TICKER_NAME)))) {
            foreach ($symbolPairs as $key => $symbolPair) {
                if (!empty($symbolPairs[$key][self::TICKER_NAME])) {
                    /* @var $spotFee SpotFee */
                    $spotFee = $gateTickersSymbolName[$symbolPairs[$key][self::TICKER_NAME]['symbolNameOrigin']];
                    $symbolPairs[$key][self::TICKER_NAME]['fee'] = $gateTickersSymbolName[$symbolPairs[$key][self::TICKER_NAME]['symbolNameOrigin']];
                    $symbolPairs[$key][self::TICKER_NAME]['takerFeeRate'] = $spotFee->getTakerFee();
                    $symbolPairs[$key][self::TICKER_NAME]['makerFeeRate'] = $spotFee->getMakerFee();

                    /* @var $currencyPair CurrencyPair */
                    $currencyPair = GateService::spot()->getCurrencyPair($symbolPairs[$key][self::TICKER_NAME]['symbolNameOrigin']);
                    $symbolPairs[$key][self::TICKER_NAME]['withdrawFee'] = $currencyPair->getFee();
                }
            }
        }
    }

    /**
     * Получить последние сделки по валютной паре
     * @param string $symbol
     * @param int $limit
     * @return array|array[]
     * @throws ApiException
     */
    public static function getTradeHistories(string $symbol, int $perSecond = 120): array
    {
        $associate_array['currency_pair'] = $symbol; // string | Currency pair
        $associate_array['limit'] = 100; // int | Maximum number of records to be returned in a single list.  Default: 100, Minimum: 1, Maximum: 1000
        $associate_array['page'] = 1; // int | Page number
        $tradeHistories = GateService::spot()->listTrades($associate_array);

        $tradeInfo = [
            'sell' => [],
            'buy' => [],
        ];

        foreach ($tradeHistories as $tradeHistory) {
            if ($tradeHistory->getCreateTime() > (time() - $perSecond)) {
            $tradeData = [
                'price' => $tradeHistory->getPrice(),
                'amount' => $tradeHistory->getAmount() * $tradeHistory->getPrice(),
            ];

            /* @var $tradeHistory Trade */
            if ($tradeHistory->getSide() === 'sell') {
                $tradeInfo['sell'][] = $tradeData;
            }

            if ($tradeHistory->getSide() === 'buy') {
                $tradeInfo['buy'][] = $tradeData;
            }
            }
        }

        return $tradeInfo;
    }

    /**
     * @param string $chainName
     * @return array|false|string|string[]|null
     */
    protected static function getAlternativeChainName(string $chainName)
    {
        return mb_strtoupper(self::ALTERNATIVE_CHAIN_NAME[$chainName] ?? $chainName);
    }

    /**
     * Получить данные по комиссиям валютрых пар
     * @param $gateTickers
     * @return false|\GateApi\Api\map
     * @throws ApiException
     */
    private static function getBatchSpotFeeByTickers($gateTickers)
    {
        // Получить все валюты из тикеров Gate.IO
        if (!empty($gateTickers = array_filter($gateTickers))) {
            return Yii::$app->getCache()->getOrSet('gateBatchSpotFeeByTickers', function () use ($gateTickers) {
                if (!empty($symbolNameOrigin = array_filter(ArrayHelper::getColumn($gateTickers, 'symbolNameOrigin')))) {
                    $symbolNamesOrigin = [];

                    // Разбиваем по 50 элементов (ограничение на кол-во запрашиваемых валют)
                    foreach (array_chunk($symbolNameOrigin, 50) as $item) {
                        $symbolNamesOrigin = array_merge($symbolNamesOrigin, GateService::spot()->getBatchSpotFee(implode(', ', $item)));
                    }

                    // Приводим в нужный вид
                    return $symbolNamesOrigin;
                }
            }, 60);
        }

        return false;
    }

    /**
     * @param $profitTicker
     * @param array $orders
     * @return void
     * @throws ApiException
     */
    protected static function setOrders($profitTicker, array &$orders): void
    {
        $profitTickerIndexKey = array_values($profitTicker);

        if (empty(self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']])) {
            $associate_array['currency_pair'] = $profitTicker[self::TICKER_NAME]['symbolNameOrigin']; // string | Currency pair
            $associate_array['interval'] = '0'; // string | Order depth. 0 means no aggregation is applied. default to 0
            $associate_array['limit'] = 100; // int | Maximum number of order depth data in asks or bids
            $associate_array['with_id'] = false; // bool | Return order book ID

            self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']] = GateService::spot()->listOrderBook($associate_array);
        }

        if ($profitTickerIndexKey[0]['source'] === self::TICKER_NAME) {
            $bids = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]->getBids();
            arsort($bids);

            $orders['bids'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $bids;
        } elseif ($profitTickerIndexKey[1]['source'] === self::TICKER_NAME) {
            $asks = self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']]->getAsks();
            sort($asks);

            $orders['asks'][self::getOrderItemName($profitTicker[self::TICKER_NAME]['symbolNameOrigin'])] = $asks;
        }
    }

    /**
     * @param string $symbol
     * @return array
     * @throws ApiException
     */
    public static function getOrdersBySymbol(string $symbol): array
    {
        $orders = [];

        if (empty(self::$orderBook[$symbol])) {
            $associate_array['currency_pair'] = $symbol; // string | Currency pair
            $associate_array['interval'] = '0'; // string | Order depth. 0 means no aggregation is applied. default to 0
            $associate_array['limit'] = 100; // int | Maximum number of order depth data in asks or bids
            $associate_array['with_id'] = false; // bool | Return order book ID

            self::$orderBook[$symbol] = GateService::spot()->listOrderBook($associate_array);
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
     * @return voide
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
     * @throws ApiException
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
