<?php

namespace App\Models\chatBot\traits;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Http;

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

        $normalizedPhone = $this->normalizePhone(
            (string) $this->main->callback["number"],
        );
        if ($normalizedPhone === null) {
            $this->bindingUserFunction("ringing", [], "number");
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg(
                "Неверный формат номера. Введите номер повторно:",
            );
        }

        if (!array_key_exists("name", $this->main->callback)) {
            $this->bindingUserFunction(
                "ringing",
                [
                    "number" => $normalizedPhone,
                ],
                "name",
            );
            $this->main->keyBoard->add("Пропустить", [
                "ringing",
                "number" => $normalizedPhone,
                "name" => "",
            ]);
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Введите имя жертвы");
        }

        $resolvedName = $this->resolveCampaignName(
            $this->main->callback["name"],
        );

        if (
            !isset($this->main->callback["type-ringing"]) ||
            trim((string) $this->main->callback["type-ringing"]) === ""
        ) {
            $this->main->keyBoard->add("Один прозвон", [
                "ringing",
                "number" => $normalizedPhone,
                "name" => $resolvedName,
                "type-ringing" => "single",
            ]);
            $this->main->keyBoard->add("Тройной прозвон", [
                "ringing",
                "number" => $normalizedPhone,
                "name" => $resolvedName,
                "type-ringing" => "triple",
            ]);
            $this->main->keyBoard->add("Отмена", "start");
            return $this->editMsg("Выберите тип прозвона:");
        }

        $typeRinging = (string) $this->main->callback["type-ringing"];
        $price = $typeRinging === "triple" ? 250 : 100;

        $affected = User::where("id", $this->main->user->id)
            ->where("balance", ">=", $price)
            ->decrement("balance", $price);

        if (!$affected) {
            $this->main->keyBoard->add("Пополнить баланс", "top_up_balance");
            $this->main->keyBoard->add("Отмена", "start");

            return $this->editMsg(
                "Недостаточно средств для запуска.\nНужно: " .
                    $this->formatMoney($price),
            );
        }

        $order = Order::create([
            "user_id" => $this->main->user->id,
            "phone" => $normalizedPhone,
            "name" => $resolvedName,
            "type" => $typeRinging,
            "price" => $price,
            "status" => "pending",
        ]);

        $response = Http::get(env("RINGING_API_URL") . "/example", [
            "phone"         => $normalizedPhone,
            "name"          => $resolvedName,
            "token"         => env("TOKEN"),
            "threads"       => 1,
            "triple_launch" => $typeRinging === "triple",
        ]);

        $apiId = $response->json("id");
        $order->update([
            "status" => "processing",
            "api_id" => $apiId,
        ]);

        \App\Jobs\CheckRingingStatus::dispatch($order->id)
            ->delay(now()->addSeconds(30));

        $this->main->user->refresh();
        $this->main->keyBoard->add("На главную", "start");

        $this->editMsg(
            "Запущен прозвон!\nНомер: " .
                $normalizedPhone .
                "\nИмя: " .
                $resolvedName .
                "\nТип: " .
                $typeRinging .
                "\nСписано: " .
                $this->formatMoney($price) .
                "\nОстаток: " .
                $this->formatMoney($this->main->user->balance),
        );
    }
}
