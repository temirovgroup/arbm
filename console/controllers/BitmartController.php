<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\bitmart\BitmartHelper;
use common\models\Exchange;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class BitmartController extends Controller
{
    /**
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $tickers = BitmartHelper::getTickersDataForCompare(BitmartHelper::getAllTickersToUsdt());

        if (empty($exchangeModel = Exchange::findOne(['source' => BitmartHelper::TICKER_NAME]))) {
            $exchangeModel = new Exchange();
            $exchangeModel->source = BitmartHelper::TICKER_NAME;
        }

        $exchangeModel->tickerData = Json::encode($tickers);

        $exchangeModel->save();

        return ExitCode::OK;
    }
}
