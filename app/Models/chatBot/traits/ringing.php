<?php

namespace App\Models\chatBot\traits;

trait ringing
{
    public function ringing()
    {
        if (
            empty($this->main->callback["number"]) ||
            !intval($this->main->callback["number"])
        ) {
            $this->bindingUserFunction("ringing", [], "number");
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Введите номер жертвы");
        }

        $this->editMsg("Вы ввели номер: " . $this->main->callback["number"]);

        if (
            !isset($this->main->callback["name"]) ||
            trim((string) $this->main->callback["name"]) === ""
        ) {
            $this->bindingUserFunction(
                "ringing",
                [
                    "number" => $this->main->callback["number"],
                ],
                "name",
            );
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Введите имя жертвы");
        }
        $this->editMsg(
            "Вы ввели номер: " .
                $this->main->callback["number"] .
                "\nВы ввели имя: " .
                $this->main->callback["name"],
        );
    }
}
