<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\bitget\BitgetHelper;
use common\exchanges\bitmart\BitmartHelper;
use common\exchanges\gate\GateHelper;
use common\exchanges\helpers\ExchangeHelper;
use common\models\Ticker;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class ExchangeController extends Controller
{
    /**
     * Установить комиссию для валютных пар для тикеров
     * @return int
     * @throws \GateApi\ApiException
     */
    public function actionSetfee(): int
    {
        $symbolPairs = ExchangeHelper::setProfitTickers();

        // Установить комиссию для валютных пар для тикеров
        GateHelper::setFeeDataToProfitSymbolPairs($symbolPairs);
        BitgetHelper::setFeeDataToProfitSymbolPairs($symbolPairs);
        BitmartHelper::setFeeDataToProfitSymbolPairs($symbolPairs);

        if (empty($tickersModel = Ticker::findOne(['id' => 1]))) {
            $tickersModel = new Ticker();
        }

        $tickersModel->tickerData = Json::encode($symbolPairs);

        $tickersModel->save();

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionAll()
    {
        $this->run('kucoin/index');
        $this->run('bingx/index');
        $this->run('bitget/index');
        $this->run('bitmart/index');
        $this->run('gate/index');
        $this->run('mexc/index');
        $this->run('xt/index');

        return ExitCode::OK;
    }

    /**
     * @return int
     */
    public function actionSpred(): int
    {
        ExchangeHelper::getSpred();

        return ExitCode::OK;
    }
}
