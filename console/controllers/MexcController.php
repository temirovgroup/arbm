<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\mexc\MexcHelper;
use common\models\Exchange;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class MexcController extends Controller
{
    /**
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $tickers = MexcHelper::getTickersDataForCompare(MexcHelper::getAllTickersToUsdt());

        if (empty($exchangeModel = Exchange::findOne(['source' => MexcHelper::TICKER_NAME]))) {
            $exchangeModel = new Exchange();
            $exchangeModel->source = MexcHelper::TICKER_NAME;
        }

        $exchangeModel->tickerData = Json::encode($tickers);

        $exchangeModel->save();

        return ExitCode::OK;
    }
}
