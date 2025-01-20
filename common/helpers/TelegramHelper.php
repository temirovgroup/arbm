<?php
/**
 * Created by PhpStorm.
 */

namespace common\helpers;

use app\modules\admin\models\Order;
use app\modules\admin\models\Tuser;
use app\modules\admin\models\User;
use common\models\Message;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Yii;
use yii\helpers\Json;
use yii\helpers\Url;

class TelegramHelper
{
    private static $telegram;

    /**
     * @return Telegram
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public static function telegramInit(): Telegram
    {
        if (!self::$telegram) {
            self::$telegram = new Telegram(Yii::$app->params['telegramBot']['key'], Yii::$app->params['telegramBot']['username']);
        }

        return self::$telegram;
    }

    /**
     * @param int $chatId
     * @param string $text
     * @param string|null $parseMode
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public static function sendMessage(int $chatId, string $text, ?string $parseMode = 'html'): \Longman\TelegramBot\Entities\ServerResponse
    {
        self::telegramInit();

        return Request::sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ]);
    }

    /**
     * @var null|\common\models\Tuser[]
     */
    private static $tUsers = null;

    /**
     * @param string $message
     * @param $spred
     * @param $spredPercent
     * @param string $pair
     * @param array $profitTicker
     * @return void
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \yii\db\Exception
     */
    public static function sendToActiveChats(string $message, $spred, $spredPercent, string $pair, array $profitTicker)
    {
        self::telegramInit();

        if (empty(self::$tUsers)) {
            self::$tUsers = \common\models\Tuser::findAll(['is_active' => \common\models\Tuser::STATUS_ACTIVE]);

            Yii::$app->getDb()->close();
        }

        foreach (self::$tUsers as $tUser) {
            // $spredSet[0] USDT
            // $spredSet[1] % спреда
            $spredSet = explode(' ', $tUser->spred_set);

            // Проверяем соответсвие фильтрам
            if ($spred >= $spredSet[0] && $spredPercent >= $spredSet[1]) {
                // Рассылаем если еще не рассылали
                if (!Message::find()->where(['pair' => $pair, 'chat_id' => $tUser->chat_id])->exists()) {
                    $result = Request::sendMessage([
                        'chat_id' => $tUser->chat_id,
                        'text' => $message,
                        'parse_mode' => 'html',
                        'reply_markup' => Json::encode([
                            'inline_keyboard' => [
                                [
                                    [
                                        'text' => '🔄',
                                        'callback_data' => Json::encode([
                                            'pair' => $pair,
                                        ]),
                                    ],
                                ],
                            ],
                        ]),
                    ]);

                    $messageModel = new Message();
                    $messageModel->pair = $pair;
                    $messageModel->chat_id = $tUser->chat_id;
                    $messageModel->mess_id = $result->result->message_id;
                    $messageModel->ticker = Json::encode($profitTicker);
                    $messageModel->save();

                    Yii::$app->getDb()->close();

                    usleep(90000);
                }
            }
        }
    }

    /**
     * @param string $message
     * @param int $mess_id
     * @param string $pair
     * @param $spred
     * @param $spredPercent
     * @return void
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public static function updateToActiveChats(string $message, int $mess_id, string $pair, $spred = null, $spredPercent = null)
    {
        self::telegramInit();

        if (empty(self::$tUsers)) {
            self::$tUsers = \common\models\Tuser::findAll(['is_active' => \common\models\Tuser::STATUS_ACTIVE]);

            Yii::$app->getDb()->close();
        }

        foreach (self::$tUsers as $tUser) {
            // $spredSet[0] USDT
            // $spredSet[1] % спреда
            $spredSet = explode(' ', $tUser->spred_set);

            if ($spred >= $spredSet[0] && $spredPercent >= $spredSet[1]) {
                /** @var $tUser \common\models\Tuser */
                Request::editMessageText([
                    'chat_id' => $tUser->chat_id,
                    'message_id' => $mess_id,
                    'parse_mode' => null,
                    'text' => $message,
                    'reply_markup' => Json::encode([
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => '🔄',
                                    'callback_data' => Json::encode([
                                        'pair' => $pair,
                                    ]),
                                ],
                            ],
                        ],
                    ]),
                ]);
            } else {
                /** @var $tUser \common\models\Tuser */
                Request::editMessageText([
                    'chat_id' => $tUser->chat_id,
                    'message_id' => $mess_id,
                    'parse_mode' => null,
                    'text' => $message,
                    'reply_markup' => Json::encode([
                        'inline_keyboard' => [
                            [
                                [
                                    'text' => '🔄',
                                    'callback_data' => Json::encode([
                                        'pair' => $pair,
                                    ]),
                                ],
                            ],
                        ],
                    ]),
                ]);
            }
        }
    }

    /**
     * @param int $chatId
     * @param array $objectBtn
     * @param string $text
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public static function sendBtn(int $chatId, array $objectBtn, string $text = ''): \Longman\TelegramBot\Entities\ServerResponse
    {
        self::telegramInit();

        return Request::sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => null,
            'reply_markup' => Json::encode([
                'resize_keyboard' => true,
                'keyboard' => $objectBtn,
            ]),
        ]);
    }

    /**
     * @return void
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public static function sendOrder(): void
    {
        /*self::telegramInit();

        foreach (Tuser::findAll(['is_active' => Tuser::ACTIVE]) as $tUser) {
            $dateOrder = DateHelper::formatDate($order->created_at);

            $text = "
            Новый заказ №{$order->uin} от {$dateOrder}
            \n<a href='" . Url::toRoute(['cart/thanks', 'created_at' => $order->created_at, 'uin' => $order->uin], true) . "'>Перейти к заказу</a>
            ";

            Request::sendMessage([
                'parse_mode' => 'HTML',
                'chat_id' => $tUser->chat_id,
                'text' => $text,
            ]);
        }*/
    }
}
