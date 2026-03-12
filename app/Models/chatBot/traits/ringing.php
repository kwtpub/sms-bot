<?php

namespace App\Models\chatBot\traits;

trait ringing
{
    public function ringing()
    {
        // $this->main->keyBoard->add("Назад", "start");

        // $text = implode("\n", [
        //     "<b>Помощь</b>",
        //     "",
        //     "Менеджер: " . e($this->getSupportManagerName()),
        //     "",
        // ]);
        //
        if (
            empty($this->main->callback["number"]) ||
            !intval($this->main->callback["number"])
        ) {
            $this->bindingUserFunction("ringing", [], "number");
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Введите номер жертвы");
        }

        $this->editMsg("Вы ввели номер: " . $this->main->callback["number"]);
    }
}
