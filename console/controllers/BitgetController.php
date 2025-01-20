<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\bitget\BitgetHelper;
use common\models\Exchange;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class BitgetController extends Controller
{
    /**
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $tickers = BitgetHelper::getTickersDataForCompare(BitgetHelper::getAllTickersToUsdt());

        if (empty($exchangeModel = Exchange::findOne(['source' => BitgetHelper::TICKER_NAME]))) {
            $exchangeModel = new Exchange();
            $exchangeModel->source = BitgetHelper::TICKER_NAME;
        }

        $exchangeModel->tickerData = Json::encode($tickers);

        $exchangeModel->save();

        return ExitCode::OK;
    }
}
