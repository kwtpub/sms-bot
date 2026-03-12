<?php

namespace App\Models\chatBot\traits;

trait ringing
{
    private function resolveCampaignName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name !== "") {
            return mb_substr($name, 0, 64);
        }

        $names = ["Алексей", "Мария", "Ирина", "Дмитрий", "Анна", "Егор"];

        return $names[array_rand($names)];
    }

    public function ringing()
    {
        if (empty($this->main->callback["number"])) {
            $this->bindingUserFunction("ringing", [], "number");
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Введите номер жертвы");
        }

        $normalizedPhone = $this->normalizePhone((string) $this->main->callback["number"]);
        if ($normalizedPhone === null) {
            $this->bindingUserFunction("ringing", [], "number");
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Неверный формат номера. Введите номер повторно:");
        }

        if (
            !isset($this->main->callback["name"]) ||
            trim((string) $this->main->callback["name"]) === ""
        ) {
            $this->bindingUserFunction(
                "ringing",
                [
                    "number" => $normalizedPhone,
                ],
                "name",
            );
            $this->main->keyBoard->add("Пропустить", ["ringing", "number" => $normalizedPhone, "name" => " "]);
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Введите имя жертвы");
        }

        $resolvedName = $this->resolveCampaignName($this->main->callback["name"]);

        if (
            !isset($this->main->callback["type-ringing"]) ||
            trim((string) $this->main->callback["type-ringing"]) === ""
        ) {
            $this->main->keyBoard->add("Один прозвон", ["ringing", "number" => $normalizedPhone, "name" => $resolvedName, "type-ringing" => "single"]);
            $this->main->keyBoard->add("Тройной прозвон", ["ringing", "number" => $normalizedPhone, "name" => $resolvedName, "type-ringing" => "triple"]);
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Выберите тип прозвона:");
        }
        $this->editMsg(
            "Вы ввели номер: " .
                $normalizedPhone .
                "\nВы ввели имя: " .
                $resolvedName .
                "\nВы ввели тип прозвона: " . $this->main->callback["type-ringing"],
        );
    }
}
