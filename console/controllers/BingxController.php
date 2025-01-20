<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\bingx\BingxHelper;
use common\models\Exchange;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class BingxController extends Controller
{
    /**
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $tickers = BingxHelper::getTickersDataForCompare(BingxHelper::getAllTickersToUsdt());

        if (empty($exchangeModel = Exchange::findOne(['source' => BingxHelper::TICKER_NAME]))) {
            $exchangeModel = new Exchange();
            $exchangeModel->source = BingxHelper::TICKER_NAME;
        }

        $exchangeModel->tickerData = Json::encode($tickers);

        $exchangeModel->save();

        return ExitCode::OK;
    }
}
