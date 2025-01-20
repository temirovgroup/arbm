<?php
/**
 * Created by PhpStorm.
 */

namespace console\controllers;

use common\exchanges\xt\XtHelper;
use common\models\Exchange;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Json;

class XtController extends Controller
{
    /**
     * @return int
     * @throws \yii\db\Exception
     */
    public function actionIndex(): int
    {
        $tickers = XtHelper::getTickersDataForCompare(XtHelper::getAllTickersToUsdt());

        if (empty($exchangeModel = Exchange::findOne(['source' => XtHelper::TICKER_NAME]))) {
            $exchangeModel = new Exchange();
            $exchangeModel->source = XtHelper::TICKER_NAME;
        }

        $exchangeModel->tickerData = Json::encode($tickers);

        $exchangeModel->save();

        return ExitCode::OK;
    }
}
