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

            $text = implode("\n", [
                "Актуальная база сервиса:",
                "🇷🇺: 20000+ сайтов",
                "🇺🇦: 900+ сайтов",
                "🇧🇾: 500+ сайтов",
                "🇰🇿: 300+ сайтов",
                "",
                "⚡️Чтобы начать прогон отправьте боту данные по инструкции:",
                "",
                "<code>[номер] [имя]</code> — запуск прогона с именем",
                "<code>[номер]</code> — запуск прогона с рандомным именем",
                "",
                "Пример: <code>79123456789 Иван</code>",
            ]);

            return $this->editMsg($text);
        }

        $raw = (string) $this->main->callback["number"];
        $parsed = $this->parseQuickCampaignInput($raw);
        \Illuminate\Support\Facades\Log::debug('ringing', ['raw' => $raw, 'parsed' => $parsed, 'callback' => $this->main->callback]);

        if ($parsed !== null) {
            $normalizedPhone = $parsed["phone"];
            $resolvedName = $this->resolveCampaignName($parsed["name"]);
        } else {
            $normalizedPhone = $this->normalizePhone($raw);

            if ($normalizedPhone === null) {
                $this->bindingUserFunction("ringing", [], "number");
                $this->main->keyBoard->add("Отмена", "start");
                return $this->editMsg(
                    "Неверный формат номера. Введите номер повторно:",
                );
            }

            $resolvedName = $this->resolveCampaignName(
                array_key_exists("name", $this->main->callback)
                    ? (string) $this->main->callback["name"]
                    : null,
            );
        }

        if (
            !isset($this->main->callback["type-ringing"]) ||
            trim((string) $this->main->callback["type-ringing"]) === ""
        ) {
            $this->main->keyBoard->add("⚡️Одиночный - 250₽", [
                "ringing",
                "number" => $normalizedPhone,
                "name" => $resolvedName,
                "type-ringing" => "single",
            ]);
            $this->main->keyBoard->add("🧨Тройной - 500₽", [
                "ringing",
                "number" => $normalizedPhone,
                "name" => $resolvedName,
                "type-ringing" => "triple",
            ]);
            $this->main->keyBoard->add("💰Пополнить баланс", "top_up_balance");
            $this->main->keyBoard->add("Отмена", "start");

            $balance = $this->formatMoney($this->main->user->balance);
            $text = implode("\n", [
                "Выберите тип прогона:",
                "",
                "⚡️Одиночный — 250₽",
                "",
                "🧨Тройной (Сразу, через час и на следующий рабочий день) — 500₽",
                "",
                "💰Ваш баланс: <b>{$balance}</b>",
            ]);

            return $this->editMsg($text);
        }

        $typeRinging = (string) $this->main->callback["type-ringing"];
        $price = $typeRinging === "triple" ? 500 : 250;

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
        $this->main->keyBoard->add("Посмотреть статус", [
            "ringing_stats",
            "order_id" => $order->id,
        ]);
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
