<?php

namespace App\Models\chatBot\traits;

use App\Models\Pay;
use App\Models\User;

trait shared
{
    private function formatMoney($amount): string
    {
        $amount = round((float) $amount, 2);
        $formatted = number_format($amount, 2, ".", " ");

        if (str_ends_with($formatted, ".00")) {
            $formatted = substr($formatted, 0, -3);
        }

        return $formatted . "₽";
    }

    private function getDiscountPercent(): int
    {
        // TODO: поле discount отсутствует в актуальных миграциях.
        return 0;
    }

    private function getReferralCount(): int
    {
        return User::where("referal_id", $this->main->user->id)->count();
    }

    private function getReferralBonusAmount(): float
    {
        // TODO: поле referral_bonus отсутствует в актуальных миграциях.
        return 0;
    }

    private function getReferralLink(): string
    {
        if (!empty($this->main->bot->bot_name)) {
            return "https://t.me/" .
                $this->main->bot->bot_name .
                "?start=" .
                $this->main->user->tg_id;
        }

        return "Ссылка станет доступна после настройки username бота";
    }

    private function getSupportManagerName(): string
    {
        // TODO: поля manager_username / support_manager отсутствуют в актуальных миграциях.
        return "@manager";
    }

    private function getCampaignModeLabel(string $mode): string
    {
        return match ($mode) {
            "triple" => "Тройной запуск",
            default => "Одиночный запуск",
        };
    }

    private function getCampaignModePrice(string $mode): float
    {
        return match ($mode) {
            "triple" => 250,
            default => 100,
        };
    }

    private function buildCampaignSummaryText(
        string $phone,
        string $name,
        string $mode,
    ): string {
        $price = $this->getCampaignModePrice($mode);
        $balance = (float) $this->main->user->balance;
        $balanceAfter = $balance - $price;

        return implode("\n", [
            "<b>Суммаризация заказа</b>",
            "",
            "Номер: <code>" . e($phone) . "</code>",
            "Имя: <b>" . e($name) . "</b>",
            "Режим: <b>" . $this->getCampaignModeLabel($mode) . "</b>",
            "Стоимость: <b>" . $this->formatMoney($price) . "</b>",
            "Текущий баланс: <b>" . $this->formatMoney($balance) . "</b>",
            "Баланс после списания: <b>" .
            $this->formatMoney($balanceAfter) .
            "</b>",
        ]);
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace("/\D+/u", "", $phone);

        if (strlen($digits) === 10) {
            $digits = "7" . $digits;
        } elseif (strlen($digits) === 11 && $digits[0] === "8") {
            $digits = "7" . substr($digits, 1);
        }

        if (strlen($digits) !== 11 || $digits[0] !== "7") {
            return null;
        }

        return "+" . $digits;
    }

    private function parseQuickCampaignInput(string $text): ?array
    {
        $text = trim(preg_replace("/\s+/u", " ", $text));
        if ($text === "") {
            return null;
        }

        $parts = explode(" ", $text);

        for ($i = 1; $i <= min(count($parts), 5); $i++) {
            $phoneCandidate = implode("", array_slice($parts, 0, $i));
            $phone = $this->normalizePhone($phoneCandidate);

            if ($phone === null) {
                continue;
            }

            $name = trim(implode(" ", array_slice($parts, $i)));

            return [
                "phone" => $phone,
                "name" => $name,
            ];
        }

        return null;
    }

    private function buildStartText(): string
    {
        return implode("\n", [
            "<b>Главный экран</b>",
            "",
            "Ваш ID: <code>" . $this->main->user->tg_id . "</code>",
            "Баланс: <b>" .
            $this->formatMoney($this->main->user->balance) .
            "</b>",
            "Скидка: <b>+" . $this->getDiscountPercent() . "%</b>",
            "",
            "Приглашай друзей и получай 10% от их трат себе на счет.",
            "Реферальная ссылка доступна по кнопке ниже.",
        ]);
    }

    private function buildCabinetText(): string
    {
        $lastPaymentsCount = Pay::where(
            "user_id",
            $this->main->user->id,
        )->count();

        return implode("\n", [
            "<b>Мой кабинет</b>",
            "",
            "Ваш ID: <code>" . $this->main->user->tg_id . "</code>",
            "Баланс: <b>" .
            $this->formatMoney($this->main->user->balance) .
            "</b>",
            "Скидка: <b>+" . $this->getDiscountPercent() . "%</b>",
            "Рефералов: <b>" . $this->getReferralCount() . "</b>",
            "Начислено по рефке: <b>" .
            $this->formatMoney($this->getReferralBonusAmount()) .
            "</b>",
            "Оплат в истории: <b>" . $lastPaymentsCount . "</b>",
        ]);
    }
}
