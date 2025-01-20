<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\bitget\BitgetHelper;
use common\exchanges\bitmart\BitmartHelper;
use common\exchanges\kucoin\KucoinHelper;
use common\exchanges\mexc\MexcHelper;
use common\models\Exchange;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class KucoinController extends Controller
{
    /**
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $tickers = KucoinHelper::getTickersDataForCompare(KucoinHelper::getAllTickersToUsdt());

        if (empty($exchangeModel = Exchange::findOne(['source' => KucoinHelper::TICKER_NAME]))) {
            $exchangeModel = new Exchange();
            $exchangeModel->source = KucoinHelper::TICKER_NAME;
        }

        $exchangeModel->tickerData = Json::encode($tickers);

        $exchangeModel->save();

        return ExitCode::OK;
    }
}
