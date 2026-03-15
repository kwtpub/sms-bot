<?php

namespace App\Models\chatBot;

use App\Models\src\chatBot\NewMessage as _NewMessage;

//Трейты кнопок:
use App\Models\chatBot\traits\admin;
use App\Models\chatBot\traits\lk;
use App\Models\chatBot\traits\top_up_balance;
use App\Models\chatBot\traits\start_;
use App\Models\chatBot\traits\help;
use App\Models\chatBot\traits\history;
use App\Models\chatBot\traits\referral;
use App\Models\chatBot\traits\shared;
use App\Models\chatBot\traits\ringing;
use App\Models\chatBot\traits\ringing_stats;

class NewMessage extends _NewMessage
{
    use shared, start_, admin, lk, top_up_balance, help, history, referral, ringing, ringing_stats;

    public function handler()
    {
        //Вызывается при нажатии кнопок в клавиатуре, или отправке текста
        switch ($this->main->req->message["text"]) {
            case "/start":
                $this->start(0);
                break;
            case "Мой кабинет":
            case "Кабинет":
                $this->lk(0);
                break;
            case "Помощь":
                $this->help(0);
                break;
            case "Старт":
                $this->start(0);
                break;
            case "Админ-панель":
                $this->admin_lk(0);
                break;
            case "Начать прогон":
                $this->ringing(0);
                break;
            default:
                if ($this->checkBindingFunction()) {
                    return;
                } //Если мы куда-то забиндились, значит там уже обработалось.

                $this->start(0);
                break;
        }
    }
}
