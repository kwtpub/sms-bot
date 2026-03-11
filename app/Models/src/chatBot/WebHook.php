<?php

namespace App\Models\src\chatBot;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\src\chatBot\Telegram;
use App\Models\Logs;

class WebHook extends Model
{
    public static function setWebHook($bot, $commands = [], $domain = '') {
        $tg = new Telegram($bot->api_key);

        //BOT ID---------------------------------------
        $botId = $tg->getBotTgId($bot->api_key);

        if(!$botId) { //Видимо неверный ключ..
            Logs::log('Не смогли определить ID телеграм бота по api ключю:', ['id' => $bot->id, 'api_key' => $bot->api_key]);
            return false;
        }

        $bot->bot_id = $botId;
        //---------------------------------------------

        //BOT NAME-------------------------------------
        $myName = $tg->getMyName();

        if(!$myName['success']) {
            Logs::log('Ошибка получения имени бота:', ['id' => $bot->id, 'api_key' => $bot->api_key, 'data' => $myName]);
            return false;
        }
        $bot->bot_name = $myName['name'];
        $bot->first_name = $myName['first_name'];
        //---------------------------------------------

        //set hook-------------------------------------
        $bot->secret = md5(mt_rand(1000000000, 9000000000)); //Секретный ключ. Для проверки что запросы отправляет тг.

        if(empty($domain)) {
            $domain = config('app.url');
        }

        $callBackUrl = $domain . '/tg_callback?bot_id=' . $bot->id;
        $data = $tg->setWebHooks($callBackUrl, $bot->secret);

        if(!$data['success']) {
            Logs::log('Не смогли привязать api ключ:', ['id' => $bot->id, 'api_key' => $bot->api_key, 'data' => $data]);
            // $bot->enable = 0;
            // $bot->success = 0;
            // $bot->save();
            return false;
        }
        //---------------------------------------------

        if(!empty($commands)) {
            $data = $tg->setMyCommands($commands);

            if(!$data['success']) {
                Logs::log('Не смогли установить команды бота:', ['id' => $bot->id, 'data' => $data]);

                return false;
            }
        }

        // Logs::log('Успешно привязали WebHooks:', ['id' => $bot->id, 'url' => $callBackUrl, 'bot_name' => $bot->bot_name]);

        $bot->enable = 1;
        $bot->success = 1;
        $bot->save();

        return true;
    }
}
