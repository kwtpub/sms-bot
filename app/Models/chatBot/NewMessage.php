<?php

namespace App\Models\chatBot;

use App\Models\src\chatBot\NewMessage as _NewMessage;

//Трейты кнопок:
use App\Models\chatBot\traits\menu;
use App\Models\chatBot\traits\admin;
use App\Models\chatBot\traits\lk;
use App\Models\chatBot\traits\start_;
use App\Models\Logs;

class NewMessage extends _NewMessage
{
    use start_, menu, admin, lk;

    public function handler() { //Вызывается при нажатии кнопок в клавиатуре, или отправке текста
        switch ($this->main->req->message['text']) {
            case '/start':
                $this->start(0);
                break;
            case '/menu':
                $this->menu(0);
                break;
            case 'Админ-панель':
                $this->admin_lk(0);
                break;
            default:
                if($this->checkBindingFunction()) return; //Если мы куда-то забиндились, значит там уже обработалось.

                $this->menu(0);
                break;
        }
    }
}
