<?php

namespace App\Models\chatBot\traits;

use App\Models\Logs;
use App\Models\User;

trait order
{
    public function start_order($edit = 1)
    {
        $this->bindingUserFunction("save_order_phone", [], "phone");
        $this->main->keyBoard->add("Назад", "menu");

        return $this->sendOrEditMsg(
            $edit,
            "Введите номер телефона жертвы.\n\nМожно отправить номер сразу сообщением.",
        );
    }

    public function save_order_phone($edit = 1)
    {
        $phone = $this->normalizePhone(
            (string) ($this->main->callback["phone"] ?? ""),
        );

        if ($phone === null) {
            $this->bindingUserFunction("save_order_phone", [], "phone");
            $this->main->keyBoard->add("Назад", "menu");

            return $this->sendOrEditMsg(
                $edit,
                "Не удалось распознать номер.\nВведите номер в формате `+79990000000`.",
            );
        }

        $this->bindingUserFunction(
            "save_order_name",
            ["phone" => $phone],
            "name",
        );
        $this->main->keyBoard->add("Пропустить", [
            "skip_order_name",
            "phone" => $phone,
        ]);
        $this->main->keyBoard->add("Назад", "menu");

        return $this->sendOrEditMsg(
            $edit,
            "Введите имя жертвы.\nЕсли хотите, можно пропустить этот шаг.",
        );
    }

    public function save_order_name($edit = 1)
    {
        $phone = (string) ($this->main->callback["phone"] ?? "");
        if ($phone === "") {
            return $this->menu($edit);
        }

        $name = $this->resolveCampaignName($this->main->callback["name"] ?? "");

        $callback = $this->main->callback ?? [];
        $callback["phone"] = $phone;
        $callback["name"] = $name;
        $this->main->callback = $callback;

        return $this->select_order_mode($edit);
    }

    public function skip_order_name($edit = 1)
    {
        $phone = (string) ($this->main->callback["phone"] ?? "");
        if ($phone === "") {
            return $this->menu($edit);
        }

        $callback = $this->main->callback ?? [];
        $callback["name"] = $this->resolveCampaignName("");
        $this->main->callback = $callback;

        return $this->select_order_mode($edit);
    }

    public function select_order_mode($edit = 1)
    {
        $phone = (string) ($this->main->callback["phone"] ?? "");
        $name = (string) ($this->main->callback["name"] ?? "");

        if ($phone === "" || $name === "") {
            return $this->start_order($edit);
        }

        if (!empty($this->main->callback["mode"])) {
            return $this->order_summary($edit);
        }

        $this->main->saveParams(["phone", "name"]);
        $this->main->keyBoard->add("Одиночный запуск", [
            "select_order_mode",
            "mode" => "single",
        ]);
        $this->main->keyBoard->add("Тройной запуск", [
            "select_order_mode",
            "mode" => "triple",
        ]);
        $this->main->keyBoard->add("Назад", "menu");

        return $this->sendOrEditMsg(
            $edit,
            "Выберите режим запуска для номера <code>{$phone}</code>.",
        );
    }

    public function order_summary($edit = 1)
    {
        $phone = (string) ($this->main->callback["phone"] ?? "");
        $name = $this->resolveCampaignName($this->main->callback["name"] ?? "");
        $mode = (string) ($this->main->callback["mode"] ?? "");

        if ($phone === "" || $mode === "") {
            return $this->start_order($edit);
        }

        $price = $this->getCampaignModePrice($mode);

        $this->main->saveParams(["phone", "name", "mode"]);
        if ((float) $this->main->user->balance >= $price) {
            $this->main->keyBoard->add("Подтвердить запуск", "submit_order");
        } else {
            $this->main->keyBoard->add("Пополнить баланс", "top_up_balance");
        }
        $this->main->keyBoard->add("Назад", "menu");

        $text = $this->buildCampaignSummaryText($phone, $name, $mode);
        if ((float) $this->main->user->balance < $price) {
            $text .=
                "\n\n<b>Недостаточно средств на балансе для подтверждения.</b>";
        }

        return $this->sendOrEditMsg($edit, $text);
    }

    public function submit_order($edit = 1)
    {
        $phone = (string) ($this->main->callback["phone"] ?? "");
        $name = $this->resolveCampaignName($this->main->callback["name"] ?? "");
        $mode = (string) ($this->main->callback["mode"] ?? "single");
        $price = $this->getCampaignModePrice($mode);

        if ((float) $this->main->user->balance < $price) {
            return $this->order_summary($edit);
        }

        $payload = [
            "user_id" => $this->main->user->id,
            "tg_id" => $this->main->user->tg_id,
            "phone" => $phone,
            "name" => $name,
            "mode" => $mode,
            "price" => $price,
        ];

        Logs::log("campaign_request_created", $payload);

        User::addAdminsWaitSendMessage(
            implode("\n", [
                "<b>Новая заявка</b>",
                "Пользователь: <code>" . $this->main->user->tg_id . "</code>",
                "Номер: <code>" . e($phone) . "</code>",
                "Имя: <b>" . e($name) . "</b>",
                "Режим: <b>" . $this->getCampaignModeLabel($mode) . "</b>",
                "Стоимость: <b>" . $this->formatMoney($price) . "</b>",
            ]),
            botId: $this->main->bot->id,
        );

        $this->main->keyBoard->add("В меню", "menu");
        $this->main->keyBoard->add("Мой кабинет", "lk");

        return $this->sendOrEditMsg(
            $edit,
            implode("\n", [
                "<b>Заказ принят в работу</b>",
                "",
                "Номер: <code>" . e($phone) . "</code>",
                "Имя: <b>" . e($name) . "</b>",
                "Режим: <b>" . $this->getCampaignModeLabel($mode) . "</b>",
            ]),
        );
    }

    public function quick_order_entry()
    {
        $parsed = $this->parseQuickCampaignInput(
            (string) ($this->main->req->message["text"] ?? ""),
        );
        if ($parsed === null) {
            return false;
        }

        $this->main->callback = [
            "phone" => $parsed["phone"],
        ];

        if ($parsed["name"] !== "") {
            $this->main->callback["name"] = $this->resolveCampaignName(
                $parsed["name"],
            );

            $this->select_order_mode(0);

            return true;
        }

        $this->bindingUserFunction(
            "save_order_name",
            ["phone" => $parsed["phone"]],
            "name",
        );
        $this->main->keyBoard->add("Пропустить", [
            "skip_order_name",
            "phone" => $parsed["phone"],
        ]);
        $this->main->keyBoard->add("Назад", "menu");
        $this->sendOrEditMsg(
            0,
            "Номер <code>{$parsed["phone"]}</code> получен.\nТеперь введите имя клиента или нажмите «Пропустить».",
        );

        return true;
    }
}
