<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\mexc;

use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\exchanges\kucoin\KucoinService;
use common\helpers\ArrayHelper;
use Yii;
use function PHPUnit\Framework\containsIdentical;

class MexcHelper extends ExchangeHelperAbstract
{
    const SERVICE = 'Mexc';
    const TICKER_NAME = 'MexcTickers';

    const ALTERNATIVE_CHAIN_NAME = [
        'Ethereum(ERC20)' => 'ERC20',
        'BNB Smart Chain(BEP20)' => 'BEP20',
        'Solana(SOL)' => 'SOL',
        'Klaytn(KLAY)' => 'KLAY',
        'Arbitrum One(ARB)' => 'ARBITRUM',
        'Polygon(MATIC)' => 'MATIC',
        'Chiliz Chain(CHZ2)' => 'CHZ2',
        'Optimism(OP)' => 'OPTIMISM',
        'Cardano(ADA)' => 'ADA',
        'Algorand(ALGO)' => 'ALGO',
        'Stellar(XLM)' => 'XLM',
        'Avalanche C Chain(AVAX CCHAIN)' => 'AVAX C-Chain',
        'Alephium(ALPH)' => 'ALPH',
        'APTOS(APT)' => 'APT',
        'Tron(TRC20)' => 'TRC20',
        'Toncoin(TON)' => 'TON',
        'Cosmos(ATOM)' => 'ATOM',
        'BNB Beacon Chain(BEP2)' => 'BEP2',
        'Avalanche X Chain(AVAX XCHAIN)' => 'AVAX XCHAIN',
        'Aleph Zero(AZERO)' => 'AZERO',
        'Bitcoin Cash(BCH)' => 'BCH',
        'Fantom(FTM)' => 'FTM',
        'Bitcoin SV(BSV)' => 'BSV',
        'Bitcoin(BTC)' => 'BTC',
        'XDB CHAIN (XDB)' => 'XDB',
        'Clore.ai(CLORE)' => 'CLORE',
        'Ripple(XRP)' => 'XRP',
        'Hedera(HBAR)' => 'HBAR',
        'NEAR Protocol(NEAR)' => 'NEAR',
        'Dogechain(DC)' => 'DC',
        'Dynex(DNX)' => 'DNX',
        'Dogecoin(DOGE)' => 'DOGE',
        'Polkadot(DOT)' => 'DOT',
        'Dymension(DYM)' => 'Dymension',
        'BEP20(BSC)' => 'BEP20',
        'Ethereum Classic(ETC)' => 'ETC',
        'Starknet(STARK)' => 'STARKNET',
        'Energy Web Chain(EWC)' => 'EWC',
        'f(x)Core' => 'CFXCORE',
        'VeChain(VET)' => 'VET',
        'Internet Computer(ICP)' => 'ICP',
        'Kaspa(KAS)' => 'KAS',
        'Elysium(LAVA)' => 'LAVA',
        'Litecoin(LTC)' => 'LTC',
        'Terra(LUNA)' => 'LUNA',
        'Zilliqa(ZIL)' => 'ZIL',
        'Mantle(MNT)' => 'MANTLE',
        'Meter(MTRG)' => 'METER',
        'Omega Network(OMN)' => 'OMN',
        'BNB Smart Chain(BEP20-RACAV2)' => 'BEP20-RACAV2',
        'RAP20 (Rangers Mainnet)' => 'RAP20',
        'Ravencoin(RVN)' => 'RVN',
        'Satoxcoin(SATOX)' => 'SATOX',
        'Bittensor(TAO)' => 'BITTENSOR',
        'Celestia(TIA)' => 'TIA',
        'UGAS(Ultrain)' => 'UGAS',
//        'Arbitrum One(ARB-Bridged)' => 'ARBITRUM',
//        'Polygon(MATIC-Bridged)' => 'MATIC',
//        'Optimism(OP-Bridged)' => 'OPTIMISM',
        'Vexanium(VEX)' => 'VEX',
        'Chia(XCH)' => 'XCH',
        'Monero(XMR)' => 'XMR',
        'Neurai(XNA)' => 'XNA',
    ];

    protected static ?array $allTickersToUsdt = null;
    protected static ?array $allCurrencyList = null;
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
                self::$allCurrencyList = Yii::$app->getCache()->getOrSet('mexcCurrencyList', function () {
                    return MexcService::spot()->market()->getList();
                }, 60);
            }

            self::$currencyListToUsdt = array_reduce(self::$allCurrencyList['data'], function ($items, $item) use ($allTickersToUsdt) {
                foreach ($allTickersToUsdt as $ticker) {
                    $symbolName = explode('_', $ticker['symbol']);
                    $itemSymbolName = $item['currency'];

                    if ($symbolName[0] === $itemSymbolName) {
                        $items[$itemSymbolName] = $item;
                    }
                }

                return $items;
            }, []);
        }

        return self::$currencyListToUsdt;
    }

    /**
     * Получить последние сделки по валютной паре
     * @param string $symbol
     * @param int $perSecond
     * @return array|array[]
     */
    public static function getTradeHistories(string $symbol, int $perSecond = 120): array
    {
        $tradeHistories = MexcService::mxcSpotV3()->publics()->getTrades([
            'symbol' => self::getNameApiV3($symbol),
            'limit' => 100,
        ]);

        $tradeInfo = [
            'sell' => [],
            'buy' => [],
        ];

        foreach ($tradeHistories as $tradeHistory) {
            if (($tradeHistory['time'] / 1000) > (time() - $perSecond)) {
                $tradeData = [
                    'price' => $tradeHistory['price'],
                    'amount' => $tradeHistory['qty'] * $tradeHistory['price'],
                ];

                if ($tradeHistory['tradeType'] === 'BID') {
                    $tradeInfo['sell'][] = $tradeData;
                }

                if ($tradeHistory['tradeType'] === 'ASK') {
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
        $symbols = MexcService::spot()->market()->getTicker();

        self::$allTickersToUsdt = array_reduce($symbols['data'], function ($items, $item) {
            if (mb_stripos($item['symbol'], 'USDT')) {
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
            self::$orderBook[$profitTicker[self::TICKER_NAME]['symbolNameOrigin']] = MexcService::mxcSpotV3()->publics()->getDepth([
                'symbol' => str_ireplace('_', '', $profitTicker[self::TICKER_NAME]['symbolNameOrigin']),
            ]);
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
            self::$orderBook[$symbol] = MexcService::mxcSpotV3()->publics()->getDepth([
                'symbol' => str_ireplace('_', '', $symbol),
            ]);
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
        self::$currencyListToUsdt = self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['coins'], function ($items, $item) {
                if ($item['is_withdraw_enabled']) {
                    $items[] = [
                        parent::WITHDRAW_FEE_KEY => $item['fee'] ?? 0,
                        'name' => self::getAlternativeChainName($item['chain']),
                        'precision' => $item['precision'] ?? null,
                        'isWithdrawEnabled' => $item['is_withdraw_enabled'] ?? null,
                        'isDepositEnabled' => $item['is_deposit_enabled'] ?? null,
                        'depositMinConfirm' => $item['deposit_min_confirm'] ?? null,
                        'withdrawLimitMax' => $item['withdraw_limit_max'] ?? null,
                        'withdrawLimitMin' => $item['withdraw_limit_min'] ?? null,
                    ];
                }

                return $items;
            }, []);
        }

        return [];
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
     * Получить названия сетей по названию валюты
     * @param string $symbolName
     * @return array|mixed
     */
    public static function getChainsNameByCurrency(string $symbolName)
    {
        self::$currencyListToUsdt = self::getCurrencyListToUsdt();

        if (isset(self::$currencyListToUsdt[$symbolName])) {
            return array_reduce(self::$currencyListToUsdt[$symbolName]['coins'], function ($items, $item) {
                if ($item['is_withdraw_enabled']) {
                    $items[] = self::getAlternativeChainName($item['chain']);
                }

                return $items;
            }, []);
        }

        return [];
    }

    /**
     * @return mixed
     */
    public static function getSymbols()
    {
        return Yii::$app->getCache()->getOrSet('mexcSymbols', function () {
            $symbols = MexcService::spot()->market()->getSymbols();

            return ArrayHelper::index($symbols['data'], 'symbol');
        }, 60);
    }

    /**
     * @param array $tickers
     * @return array
     */
    public static function getTickersDataForCompare(array $tickers): array
    {
        $symbols = self::getSymbols();

        return array_reduce($tickers, function ($items, $item) use ($symbols) {
            if (!empty($symbols[$item['symbol']])) {
                $symbol = str_replace('_', '-', $item['symbol']);
                $symbolName = explode('_', $item['symbol']);

                if (!empty($chainsName = self::getChainsNameByCurrency($symbolName[0]))) {
                    $chains = self::getChainsByCurrency($symbolName[0]);

                    $symbolData = $symbols[$item['symbol']];

                    $items[$symbol] = [
                        'class' => self::class,
                        'service' => self::SERVICE,
                        'source' => self::TICKER_NAME,
                        'symbolName' => $symbol,
                        'symbolNameOrigin' => $item['symbol'],
                        'last' => $item['last'],
                        self::QV_24 => $item['amount'],
                        'symbol' => $symbolName[0],
                        'chains' => $chains,
                        'chainsName' => $chainsName,
//                    'detail' => $currencyListToUsdt[$symbolName[0]],
                        'hasFeeData' => true,
                        'item' => $item,
                        'takerFeeRate' => $symbolData['taker_fee_rate'],
                        'makerFeeRate' => $symbolData['maker_fee_rate'],
                    ];
                }
            }

            return $items;
        }, []);
    }

    /**
     * @param $symbolName
     * @return array|string|string[]
     */
    private static function getNameApiV3($symbolName)
    {
        return preg_replace('/[^0-9a-zA-Z]/', '', $symbolName);
    }
}
