<?php

namespace App\Models\chatBot\traits;

trait help
{
    public function help($edit = 1)
    {
        $this->main->keyBoard->add("Как считается скидка?", "discount_help");
        $this->main->keyBoard->add("Назад", "start");

        $text = implode("\n", [
            "<b>Помощь</b>",
            "",
            "Менеджер: " . e($this->getSupportManagerName()),
            "",
        ]);

        return $this->sendOrEditMsg($edit, $text);
    }

    public function discount_help($edit = 1)
    {
        $this->main->keyBoard->add("Назад", "help");

        $text = implode("\n", ["<b>Как считается скидка</b>", "НЭТ"]);

        return $this->sendOrEditMsg($edit, $text);
    }
}
