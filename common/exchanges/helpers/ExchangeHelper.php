<?php
/**
 * Created by PhpStorm.
 */

namespace common\exchanges\helpers;

use common\exchanges\bitmart\BitmartHelper;
use common\exchanges\helpers\abstracts\ExchangeHelperAbstract;
use common\helpers\ArrayHelper;
use common\helpers\NumberHelper;
use common\helpers\StringHelper;
use common\helpers\TelegramHelper;
use common\models\Exchange;
use common\models\Ticker;
use Longman\TelegramBot\Request;
use Yii;
use yii\helpers\Json;

class ExchangeHelper
{
    /**
     * @return array
     */
    public static function setProfitTickers(): array
    {
        // Получаем массивы с тикерами
        $allTickers = self::getExchange();

        // Валютные пары из бирж
        /**
         *
         *
         * [BOTAI-USDT] => Array
         * (
         * [bitmartTickers] => Array
         * (
         * [class] => common\exchanges\bitmart\BitmartHelper
         * [service] => Bitmart
         * [source] => bitmartTickers
         * [symbolName] => BOTAI-USDT
         * [symbolNameOrigin] => BOTAI_USDT
         * [last] => 0.000009
         * [symbol] => BOTAI
         * [chainsName] => Array
         * (
         * [0] => BEP20
         * )
         *
         * [chains] => Array
         * (
         * [name] => BEP20
         * [currency] => BOTAI
         * [contract_address] => 0x20b2d9F094b7561A4AbBcf8a2b88938B779C986d
         * [withdraw_enabled] => 1
         * [deposit_enabled] => 1
         * [withdraw_minsize] => 15.4
         * [withdraw_minfee] => 1
         * )
         *
         * [item] => Array
         * (
         * [symbol] => BOTAI_USDT
         * [last] => 0.000009
         * [v_24h] => 13084654.26
         * [qv_24h] => 82.222690
         * [open_24h] => 0.000009
         * [high_24h] => 0.000009
         * [low_24h] => 0.000006
         * [fluctuation] => 0.00000
         * [bid_px] => 0.000006
         * [bid_sz] => 5177944.35
         * [ask_px] => 0.000009
         * [ask_sz] => 7955342.86
         * [ts] => 1720033780205
         * )
         *
         * )
         *
         * [MexcTickers] => Array
         * (
         * [class] => common\exchanges\mexc\MexcHelper
         * [service] => Mexc
         * [source] => MexcTickers
         * [symbolName] => BOTAI-USDT
         * [symbolNameOrigin] => BOTAI_USDT
         * [last] => 0.000007659
         * [symbol] => BOTAI
         * [chains] => Array
         * (
         * [0] => Array
         * (
         * [name] => BEP20
         * [precision] => 18
         * [fee] => 20000
         * [isWithdrawEnabled] => 1
         * [isDepositEnabled] => 1
         * [depositMinConfirm] => 16
         * [withdrawLimitMax] => 20000000000
         * [withdrawLimitMin] => 40000
         * )
         *
         * )
         *
         * [chainsName] => Array
         * (
         * [0] => BEP20
         * )
         *
         * [hasFeeData] => 1
         * [item] => Array
         * (
         * [symbol] => BOTAI_USDT
         * [volume] => 5519247.26
         * [amount] => 43.810598115
         * [high] => 0.000011994
         * [low] => 0.000007659
         * [bid] => 0.000007659
         * [ask] => 0.000010999
         * [open] => 0.000009977
         * [last] => 0.000007659
         * [time] => 1720033803359
         * [change_rate] => -0.2323
         * )
         *
         * [takerFeeRate] => 0
         * [makerFeeRate] => 0
         * )
         *
         * )
         * */
        $symbolPairs = [];

        // Основной цикл бирж с тикерами
        foreach ($allTickers as $tickerSource => $tickers) {
            // Проходим тикеры проверяемой биржи
            foreach ($tickers as $symbolName => $symbol) {
                // Сравниваем текущую биржку с основным массивом бирж
                foreach ($allTickers as $tickerSourceCompare => $tickersCompare) {
                    // Пропускаем если биржа одна и та же
                    if ($tickerSource === $tickerSourceCompare || empty($tickersCompare[$symbolName])) {
                        continue;
                    }

                    // Если монета есть в массиве, её цена больше чем на X% - забираем
                    if ($symbol['last'] > $tickersCompare[$symbolName]['last'] &&
                        NumberHelper::getPercentManyMore($symbol['last'], $tickersCompare[$symbolName]['last'])) {
                        // Проверяем сеть и контракт

                        if (self::isNetworksMatch($symbol['chainsName'], $tickersCompare[$symbolName]['chainsName'])) {
                            $symbolPairs[$symbolName] = [
                                $tickerSource => $symbol,
                                $tickerSourceCompare => $tickersCompare[$symbolName],
                            ];
                        }
                    }
                }
            }
        }

        return $symbolPairs;
    }

    /**
     * @return array
     */
    public static function getTickersSol(): array
    {
        $allTickers = self::getExchange();

        $solTickers = [];

        foreach ($allTickers as $tickers) {
            foreach ($tickers as $ticker) {
                if (in_array('SOL', $ticker['chainsName'])) {
                    $solTickers[] = $ticker;
                }
            }
        }

        return $solTickers;
    }

    /**
     * @return mixed|null
     */
    public static function getProfitTickers()
    {
        $ticker = Ticker::findOne(['id' => 1]);

        Yii::$app->getDb()->close();

        return Json::decode($ticker->tickerData);
    }

    /**
     * @param $chains1
     * @param $chains2
     * @return bool
     */
    public static function isNetworksMatch(array $chains1, array $chains2): bool
    {
        return !empty(array_filter(array_intersect($chains1, $chains2)));
    }

    /**
     * @param array $profitTicker
     * @return array
     */
    public static function getNetworkIntersect(array $profitTicker): array
    {
        $profitTickerIndexKey = array_values($profitTicker);

        return array_values(array_filter(array_intersect($profitTickerIndexKey[0]['chainsName'], $profitTickerIndexKey[1]['chainsName'])));
    }

    /**
     * @param array $asks
     * @param array $bids
     * @return array
     */
    public static function getPositiveCoinInOrdersAndWindowSpred(array $asks, array $bids): array
    {
        $positiveCoinsAsk = 0;
        $positiveCoinsBid = 0;
        $windowSpred = [];
        $minBidsPrice = [];

        /*for ($i = 0; $i < 30; $i++) {
            unset($bids[$i]);
        }*/

        $cloneBids1 = $bids;
        $cloneBids2 = $bids;

        // Собираем максимальное кол-во монет в плюс
        foreach ($asks as $aKey => $ask) {
            // $ask[0] цена монеты в USDT
            // $ask[1] кол-во монет
            $askUsdt = $ask[0];

            foreach ($cloneBids1 as $bKey => $bid) {
                // $bid[0] цена монеты в USDT
                // $bid[1] кол-во монет
                $bidUsdt = $bid[0];
                $bidCurr = $bid[1];

                // Включаем в выборку если ордер в плюс и кол-во монет для покупки не превышает кол-во на продажу
                if ($askUsdt < $bidUsdt) {
                    $positiveCoinsBid += $bidCurr;
                    $minBidsPrice[] = $bidUsdt;

                    unset($cloneBids1[$bKey]);
                }
            }
        }

        if (empty($minBidsPrice)) {
            // Если нет ордера в плюс возвращаем пустой массив и пропускаем итерацию
            return [];
        }

        $minBidsPrice = min($minBidsPrice);

        // Собираем сколько заказов нужно купить
        foreach ($asks as $aKey => $ask) {
            // $ask[0] цена монеты в USDT
            // $ask[1] кол-во монет
            $askUsdt = $ask[0];
            $askCurr = $ask[1];

            foreach ($bids as $bKey => $bid) {
                // $bid[0] цена монеты в USDT
                // $bid[1] кол-во монет
                $bidUsdt = $bid[0];
                $bidCurr = $bid[1];

                // Включаем в выборку если ордер в плюс и кол-во монет для покупки не превышает кол-во на продажу
                if ($askUsdt < $bidUsdt && $askUsdt < $minBidsPrice && $positiveCoinsAsk <= $positiveCoinsBid) {
                    $positiveCoinsAsk += $askCurr;
                    $windowSpred['asks'][] = $askUsdt;

                    unset($bids[$bKey]);

                    break;
                }
            }
        }

        // Считаем сколько заказов можем продать
        $positiveCoinsBid = 0;
        $positiveUsdtBid = 0;

        foreach ($asks as $aKey => $ask) {

            // $ask[0] цена монеты в USDT
            // $ask[1] кол-во монет
            $askUsdt = $ask[0];
            $askCurr = $ask[1];

            foreach ($cloneBids2 as $bKey => $bid) {
                // $bid[0] цена монеты в USDT
                // $bid[1] кол-во монет
                $bidUsdt = $bid[0];
                $bidCurr = $bid[1];

                if ($askUsdt < $bidUsdt) {
                    $positiveUsdtBid += $bidCurr * $bidUsdt;
                }

                // Включаем в выборку если ордер в плюс и кол-во монет для покупки не превышает кол-во на продажу
                if ($askUsdt < $bidUsdt && $positiveCoinsBid <= $positiveCoinsAsk) {
                    $positiveCoinsBid += $bidCurr;
                    $positiveUsdtBid += $bidCurr * $bidUsdt;
                    $windowSpred['bids'][] = $bidUsdt;

                    unset($cloneBids2[$bKey]);
                }
            }
        }

        return [
            'positiveCoins' => $positiveCoinsAsk,
            'positiveUsdtBid' => $positiveUsdtBid,
            'windowSpred' => $windowSpred,
        ];
    }

    /**
     * @return void
     */
    public static function getSpred()
    {
        $profitTickers = ExchangeHelper::getProfitTickers();

        $spredCount = 0;

        $allOrders = [];

        // Устанавливаем спрос - предложение
        foreach ($profitTickers as $source => $profitTicker) {
            ExchangeHelperAbstract::setOrdersTheir($profitTicker, $allOrders);
        }

        foreach ($profitTickers as $source => $profitTicker) {
            $profitTickerIndexKey = array_values($profitTicker);

            $bidTakerFee = $profitTickerIndexKey[0]['takerFeeRate'];
            $bidMakerFee = $profitTickerIndexKey[0]['makerFeeRate'];
            $askTakerFee = $profitTickerIndexKey[1]['takerFeeRate'];
            $askMakerFee = $profitTickerIndexKey[1]['makerFeeRate'];

            // Устанавливаем спрос - предложение
            $orders = [];
            ExchangeHelperAbstract::setOrderTheir($profitTicker, $allOrders, $orders);

            $askSym = 0;
            $askSymAm = 0;
            $bidSymAm = 0;
            $positiveCoinInOrdersAndWindowSpred = ExchangeHelper::getPositiveCoinInOrdersAndWindowSpred($orders['asks'], $orders['bids']);
            $maxSum = 2000;

            // Пропускаем итерацию если нет ордера в плюс
            if (empty($positiveCoinInOrdersAndWindowSpred)) {
                continue;
            }

            foreach ($orders['asks'] as $aKey => $ask) {
                // $ask[0] цена монеты в USDT
                // $ask[1] кол-во монет
                $askUsdt = $ask[0];
                $askCurr = $ask[1];

                foreach ($orders['bids'] as $bKey => $bid) {
                    // $bid[0] цена монеты в USDT
                    // $bid[1] кол-во монет
                    $bidUsdt = $bid[0];
                    $bidCurr = $bid[1];

                    // Включаем в выборку если ордер в плюс и кол-во монет для покупки не превышает кол-во на продажу
                    if ($askUsdt < $bidUsdt && $askSym < $positiveCoinInOrdersAndWindowSpred['positiveCoins']) {
                        $askSym += $askCurr;

                        $askSymAm += $askCurr * $askUsdt;
                        $bidSymAm += $askCurr * $bidUsdt;

                        // Не выходим за рамки максимальной суммы закупки для спреда
                        if ($askSymAm > $maxSum) {
                            $askSymTemp = $askSymAm - $maxSum;
                            $askSymAm -= $askSymTemp;
                            $bidSymAm -= ($askSymTemp / $askUsdt) * $bidUsdt;
                            $askSym -= ($askSymTemp / $askUsdt);

                            break;
                        }

                        unset($orders['bids'][$bKey]);
                    }
                }
            }

            // комса
            $askFee = $askSymAm * $askTakerFee;
            $bidFee = $bidSymAm * $bidMakerFee;

            $askSymAm += $askFee;
            $bidSymAm -= $bidFee;
            $spotFee = $askFee + $bidFee;

            $networks = ExchangeHelper::getNetworkIntersect($profitTicker);

            $currencyFee = $profitTickerIndexKey[1]['class']::getFee($profitTickerIndexKey[1]['chains'], $networks[0]);

            if ($profitTickerIndexKey[1]['service'] === BitmartHelper::SERVICE) {
                $currencyFee = NumberHelper::round(bcdiv($currencyFee, $profitTickerIndexKey[1]['last'], 15));
                $withdrawalFee = bcmul($currencyFee, $profitTickerIndexKey[1]['last'], 15);
            } else {
                $withdrawalFee = bcmul(NumberHelper::round($currencyFee), $profitTickerIndexKey[1]['last'], 15);
            }

            $bidSymAm -= $withdrawalFee;
            $spred = round($bidSymAm - $askSymAm);

//            if ($spred > 0 && NumberHelper::getPercentManyMore($bidSymAm, $askSymAm, 0.1)) {
            if ($spred > 0) {
                $spredCount++;

                $sourceName = [
                    'bids' => '',
                    'asks' => '',
                ];
                ExchangeHelperAbstract::setSourceNameTheir($profitTicker, $sourceName);

                $qv_24 = [
                    'bids' => '',
                    'asks' => '',
                ];
                ExchangeHelperAbstract::setQV24Their($profitTicker, $qv_24);

                // Список последних сделок
                $tradeHistories = [
                    'sell' => [],
                    'buy' => [],
                ];
                ExchangeHelperAbstract::setTradeHistories($profitTicker, $tradeHistories);

                // Расчеты
                $windowSpred = $positiveCoinInOrdersAndWindowSpred['windowSpred'];
                $windowSpredAsksMin = min($windowSpred['asks']);
                $windowSpredAsksMax = max($windowSpred['asks']);
                $windowSpredAsksAvg = NumberHelper::round(ArrayHelper::arraySumAvg($windowSpred['asks']));
                $windowSpredBidsMin = min($windowSpred['bids']);
                $windowSpredBidsMax = max($windowSpred['bids']);
                $windowSpredBidsAvg = ArrayHelper::arraySumAvg($windowSpred['bids']);
                $buyOrder = count($windowSpred['asks']);
                $soldOrder = count($windowSpred['bids']);

                // Кол-во монет на продажу с комсой
                $bidSym = $askSym;

                $bidSym -= $currencyFee;

                $spredPercent = 100 - ($askSymAm / $bidSymAm * 100);

                $message = "";

                $message .= "{$source}: {$sourceName['asks']} -> {$sourceName['bids']} $" . round($askSymAm) . " +" . round($spred) . "$ (" . round($spredPercent, 2) . "%)";
                $message .= "\n";
                $message .= "\n";
                $message .= "📕 | {$sourceName['asks']} | вывод";
                $message .= "\n";
                $message .= "Цена: " . NumberHelper::round($windowSpredAsksAvg) . " [" . NumberHelper::round($windowSpredAsksMin) . "-" . NumberHelper::round($windowSpredAsksMax) . "]";
                $message .= "\n";
                $message .= "Объем: $" . round($askSymAm) . ", " . NumberHelper::numFormatSymbol($askSym) . ", {$buyOrder} " . StringHelper::declineWord($buyOrder, 'ордер', 'ордера', 'ордеров');
                $message .= "\n";
                $message .= "За 24ч (USDT) $" . round($qv_24['asks']);
                $message .= "\n";
                $message .= "Куплено (за последние 2 мин.): " . NumberHelper::round($tradeHistories['sell']['avgPrice']) . " - $" . round($tradeHistories['sell']['amount']);
                $message .= "\n";
                $message .= "\n";
                $message .= "📗 | {$sourceName['bids']} | ввод";
                $message .= "\n";
                $message .= "Цена: " . NumberHelper::round($windowSpredBidsAvg) . " [" . NumberHelper::round($windowSpredBidsMin) . "-" . NumberHelper::round($windowSpredBidsMax) . "]";
                $message .= "\n";
                $message .= "Объем: $" . round($bidSymAm) . ", " . NumberHelper::numFormatSymbol($bidSym) . ", {$soldOrder} " . StringHelper::declineWord($soldOrder, 'ордер', 'ордера', 'ордеров');

                $message .= "\n";
                $message .= "За 24ч (USDT) $" . round($qv_24['asks']);
                $message .= "\n";
                $message .= "Запас (объем): $" . round($positiveCoinInOrdersAndWindowSpred['positiveUsdtBid']);
                $message .= "\n";
                $message .= "Продано (за последние 2 мин.): " . NumberHelper::round($tradeHistories['buy']['avgPrice']) . " - $" . round($tradeHistories['buy']['amount']);
                $message .= "\n";
                $message .= "\n";
                $message .= "Комиссия: спот $" . round($spotFee) . " / перевод $" . round($withdrawalFee) . " (" . NumberHelper::numFormatSymbol($currencyFee) . ")";
                $message .= "\n";
                $message .= 'Сеть: ' . implode(', ', $networks);
                $message .= "\n";
                $message .= "\n";
                $message .= "💰 Чистый спред \${$spred}";

                // Отправляем спред всем активным
                TelegramHelper::sendToActiveChats($message, $spred, $spredPercent, $source, $profitTicker);
            }
        }

        echo "Кол-во: {$spredCount}";
    }

    /**
     * @param int $mess_id
     * @param string $pair
     * @param array $profitTicker
     * @return void
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public static function getSpredUpdate(int $mess_id, string $pair, array $profitTicker)
    {
        $allOrders = [];

        // Устанавливаем спрос - предложение
        ExchangeHelperAbstract::setOrdersTheir($profitTicker, $allOrders);
        $profitTickerIndexKey = array_values($profitTicker);

        $bidTakerFee = $profitTickerIndexKey[0]['takerFeeRate'];
        $bidMakerFee = $profitTickerIndexKey[0]['makerFeeRate'];
        $askTakerFee = $profitTickerIndexKey[1]['takerFeeRate'];
        $askMakerFee = $profitTickerIndexKey[1]['makerFeeRate'];

        // Устанавливаем спрос - предложение
        $orders = [];

        try {
            ExchangeHelperAbstract::setOrderTheir($profitTicker, $allOrders, $orders);
        } catch (\Exception $exception) {
            TelegramHelper::updateToActiveChats('Спред недоступен!', $mess_id, $pair);
        }

        $askSym = 0;
        $askSymAm = 0;
        $bidSymAm = 0;
        $positiveCoinInOrdersAndWindowSpred = ExchangeHelper::getPositiveCoinInOrdersAndWindowSpred($orders['asks'], $orders['bids']);
        $maxSum = 2000;

        // Пропускаем итерацию если нет ордера в плюс
        // Отправляем сообщение что спред больше недоступен
        if (empty($positiveCoinInOrdersAndWindowSpred)) {
            TelegramHelper::updateToActiveChats('Спред недоступен!', $mess_id, $pair);
        }

        foreach ($orders['asks'] as $aKey => $ask) {
            // $ask[0] цена монеты в USDT
            // $ask[1] кол-во монет
            $askUsdt = $ask[0];
            $askCurr = $ask[1];

            foreach ($orders['bids'] as $bKey => $bid) {
                // $bid[0] цена монеты в USDT
                // $bid[1] кол-во монет
                $bidUsdt = $bid[0];
                $bidCurr = $bid[1];

                // Включаем в выборку если ордер в плюс и кол-во монет для покупки не превышает кол-во на продажу
                if ($askUsdt < $bidUsdt && $askSym < $positiveCoinInOrdersAndWindowSpred['positiveCoins']) {
                    $askSym += $askCurr;

                    $askSymAm += $askCurr * $askUsdt;
                    $bidSymAm += $askCurr * $bidUsdt;

                    // Не выходим за рамки максимальной суммы закупки для спреда
                    if ($askSymAm > $maxSum) {
                        $askSymTemp = $askSymAm - $maxSum;
                        $askSymAm -= $askSymTemp;
                        $bidSymAm -= ($askSymTemp / $askUsdt) * $bidUsdt;
                        $askSym -= ($askSymTemp / $askUsdt);

                        break;
                    }

                    unset($orders['bids'][$bKey]);
                }
            }
        }

        // комса
        $askFee = $askSymAm * $askTakerFee;
        $bidFee = $bidSymAm * $bidMakerFee;

        $askSymAm += $askFee;
        $bidSymAm -= $bidFee;
        $spotFee = $askFee + $bidFee;

        $networks = ExchangeHelper::getNetworkIntersect($profitTicker);

        $currencyFee = $profitTickerIndexKey[1]['class']::getFee($profitTickerIndexKey[1]['chains'], $networks[0]);

        if ($profitTickerIndexKey[1]['service'] === BitmartHelper::SERVICE) {
            $currencyFee = NumberHelper::round(bcdiv($currencyFee, $profitTickerIndexKey[1]['last'], 15));
            $withdrawalFee = bcmul($currencyFee, $profitTickerIndexKey[1]['last'], 15);
        } else {
            $withdrawalFee = bcmul(NumberHelper::round($currencyFee), $profitTickerIndexKey[1]['last'], 15);
        }

        $bidSymAm -= $withdrawalFee;
        $spred = round($bidSymAm - $askSymAm);

//            if ($spred > 0 && NumberHelper::getPercentManyMore($bidSymAm, $askSymAm, 0.1)) {
        if ($spred > 0) {
            $sourceName = [
                'bids' => '',
                'asks' => '',
            ];
            ExchangeHelperAbstract::setSourceNameTheir($profitTicker, $sourceName);

            $qv_24 = [
                'bids' => '',
                'asks' => '',
            ];
            ExchangeHelperAbstract::setQV24Their($profitTicker, $qv_24);

            // Список последних сделок
            $tradeHistories = [
                'sell' => [],
                'buy' => [],
            ];
            ExchangeHelperAbstract::setTradeHistories($profitTicker, $tradeHistories);

            // Расчеты
            $windowSpred = $positiveCoinInOrdersAndWindowSpred['windowSpred'];
            $windowSpredAsksMin = min($windowSpred['asks']);
            $windowSpredAsksMax = max($windowSpred['asks']);
            $windowSpredAsksAvg = NumberHelper::round(ArrayHelper::arraySumAvg($windowSpred['asks']));
            $windowSpredBidsMin = min($windowSpred['bids']);
            $windowSpredBidsMax = max($windowSpred['bids']);
            $windowSpredBidsAvg = ArrayHelper::arraySumAvg($windowSpred['bids']);
            $buyOrder = count($windowSpred['asks']);
            $soldOrder = count($windowSpred['bids']);

            // Кол-во монет на продажу с комсой
            $bidSym = $askSym;

            $bidSym -= $currencyFee;

            $spredPercent = 100 - ($askSymAm / $bidSymAm * 100);

            $message = "";

            $message .= "{$pair}: {$sourceName['asks']} -> {$sourceName['bids']} $" . round($askSymAm) . " +" . round($spred) . "$ (" . round($spredPercent, 2) . "%)";
            $message .= "\n";
            $message .= "\n";
            $message .= "📕 | {$sourceName['asks']} | вывод";
            $message .= "\n";
            $message .= "Цена: " . NumberHelper::round($windowSpredAsksAvg) . " [" . NumberHelper::round($windowSpredAsksMin) . "-" . NumberHelper::round($windowSpredAsksMax) . "]";
            $message .= "\n";
            $message .= "Объем: $" . round($askSymAm) . ", " . NumberHelper::numFormatSymbol($askSym) . ", {$buyOrder} " . StringHelper::declineWord($buyOrder, 'ордер', 'ордера', 'ордеров');
            $message .= "\n";
            $message .= "За 24ч (USDT) $" . round($qv_24['asks']);
            $message .= "\n";
            $message .= "Куплено (за последние 2 мин.): " . NumberHelper::round($tradeHistories['sell']['avgPrice']) . " - $" . round($tradeHistories['sell']['amount']);
            $message .= "\n";
            $message .= "\n";
            $message .= "📗 | {$sourceName['bids']} | ввод";
            $message .= "\n";
            $message .= "Цена: " . NumberHelper::round($windowSpredBidsAvg) . " [" . NumberHelper::round($windowSpredBidsMin) . "-" . NumberHelper::round($windowSpredBidsMax) . "]";
            $message .= "\n";
            $message .= "Объем: $" . round($bidSymAm) . ", " . NumberHelper::numFormatSymbol($bidSym) . ", {$soldOrder} " . StringHelper::declineWord($soldOrder, 'ордер', 'ордера', 'ордеров');

            $message .= "\n";
            $message .= "За 24ч (USDT) $" . round($qv_24['asks']);
            $message .= "\n";
            $message .= "Запас (объем): $" . round($positiveCoinInOrdersAndWindowSpred['positiveUsdtBid']);
            $message .= "\n";
            $message .= "Продано (за последние 2 мин.): " . NumberHelper::round($tradeHistories['buy']['avgPrice']) . " - $" . round($tradeHistories['buy']['amount']);
            $message .= "\n";
            $message .= "\n";
            $message .= "Комиссия: спот $" . round($spotFee) . " / перевод $" . round($withdrawalFee) . " (" . NumberHelper::numFormatSymbol($currencyFee) . ")";
            $message .= "\n";
            $message .= 'Сеть: ' . implode(', ', $networks);
            $message .= "\n";
            $message .= "\n";
            $message .= "💰 Чистый спред \${$spred}";

            // Отправляем спред всем активным
            TelegramHelper::updateToActiveChats($message, $mess_id, $pair, $spred, $spredPercent);
        }

        echo "Спред обновлен!";
    }

    public static function getSpredJup()
    {

    }

    /**
     * @return array|null[]
     */
    private static function getExchange(): array
    {
        // Получаем все биржи
        $exchanges = Exchange::find()
            ->indexBy('source')
            ->all();

        Yii::$app->getDb()->close();

        // Извлекаем данные тикеров
        $allTickers = ArrayHelper::getColumn($exchanges, 'tickerData');

        // Получаем массивы с тикерами
        return array_map(function ($item) {
            return Json::decode($item);
        }, $allTickers);
    }
}
