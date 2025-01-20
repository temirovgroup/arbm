<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\gate\GateHelper;
use common\models\Exchange;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class GateController extends Controller
{
    /**
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $tickers = GateHelper::getTickersDataForCompare(GateHelper::getAllTickersToUsdt());

        if (empty($exchangeModel = Exchange::findOne(['source' => GateHelper::TICKER_NAME]))) {
            $exchangeModel = new Exchange();
            $exchangeModel->source = GateHelper::TICKER_NAME;
        }

        $exchangeModel->tickerData = Json::encode($tickers);

        $exchangeModel->save();

        return ExitCode::OK;
    }
}
