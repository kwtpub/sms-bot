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

        if (
            !isset($this->main->callback["type-ringing"]) ||
            trim((string) $this->main->callback["type-ringing"]) === ""
        ) {
            $this->main->keyBoard->add("Один прозвон", ["ringing", "number" => $this->main->callback["number"], "name" => $this->main->callback["name"], "type-ringing" => "single"]);
            $this->main->keyBoard->add("Тройной прозвон", ["ringing", "number" => $this->main->callback["number"], "name" => $this->main->callback["name"], "type-ringing" => "triple"]);
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Выберите тип прозвона:");
        }
        $this->editMsg(
            "Вы ввели номер: " .
                $this->main->callback["number"] .
                "\nВы ввели имя: " .
                $this->main->callback["name"],
            "\nВы ввели тип прозвона: " . $this->main->callback["type-ringing"],
        );
    }
}
