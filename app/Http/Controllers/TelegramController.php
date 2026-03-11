<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\chatBot\Main;

use App\Models\src\chatBot\Models\waitMessage;

// use App\Models\src\chatBot\WebHook;
// use App\Models\Bot;

class TelegramController extends Controller
{
    public function tg_callback(Request $r) { //Точка входа всех запросов от ТГ
        return (new Main($r))->handler();
    }

    public function sendWaitMessages() { //Обработка отложки сообщений
        return waitMessage::handler();
    }

    // public function setWebHook() {
    //     $bots = Bot::where('enable', 1)->get();

    //     foreach($bots as $bot) {
    //         $data = WebHook::setWebHook($bot, [
    //             [
    //                 'command' => '/menu',
    //                 'description' => 'Главное меню'
    //             ]
    //         ]);
    //     }
    // }
}