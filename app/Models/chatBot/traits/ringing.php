<?php

namespace App\Models\chatBot\traits;

trait ringing
{
    public function ringing()
    {
        $this->main->keyBoard->add("Как считается скидка?", "discount_help");
        // $this->main->keyBoard->add("Назад", "start");

        // $text = implode("\n", [
        //     "<b>Помощь</b>",
        //     "",
        //     "Менеджер: " . e($this->getSupportManagerName()),
        //     "",
        // ]);
        //

        return $this->sendOrEditMsg($edit, "Введите номер жертвы");
    }
}
