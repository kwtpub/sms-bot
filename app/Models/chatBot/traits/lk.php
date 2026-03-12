<?php
namespace App\Models\chatBot\traits;
trait lk
{
    public function lk($edit = 1)
    {
        $this->main->keyBoard->add("Пополнить баланс", "top_up_balance");
        $this->main->keyBoard->add("История заказов", "history_orders");
        $this->main->keyBoard->add("История оплат", "history_payments");
        $this->main->keyBoard->add("Реферальная ссылка", "referral_info");
        $this->main->keyBoard->add("Назад", "start");

        return $this->editMsg($this->buildCabinetText(), $edit ? 0 : 3);
    }

    public function top_up_balance($edit = 1)
    {
        $sum = (float) ($this->main->callback["sum"] ?? 0);
        $method = (string) ($this->main->callback["method"] ?? "");

        if ($sum <= 0) {
            $this->bindingUserFunction("top_up_balance", [], "sum");
            $this->main->keyBoard->add("300₽", [
                "top_up_balance",
                "sum" => 300,
            ]);
            $this->main->keyBoard->add("500₽", [
                "top_up_balance",
                "sum" => 500,
            ]);
            $this->main->keyBoard->add("1000₽", [
                "top_up_balance",
                "sum" => 1000,
            ]);
            $this->main->keyBoard->add("Отмена", "lk");

            return $this->editMsg(
                "Введите сумму пополнения или выберите готовый вариант.",
                $edit ? 0 : 3,
            );
        }

        if ($method === "") {
            $this->main->saveParams(["sum"]);
            $this->main->keyBoard->add("Карта", [
                "top_up_balance",
                "method" => "card",
            ]);
            $this->main->keyBoard->add("Ручная", [
                "top_up_balance",
                "method" => "manual",
            ]);
            $this->main->keyBoard->add("Отмена", "lk");

            return $this->editMsg(
                "Сумма: <b>{$this->formatMoney(
                    $sum,
                )}</b>\nВыберите способ оплаты.",
                $edit ? 0 : 3,
            );
        }

        $this->main->keyBoard->add("История оплат", "history_payments");
        $this->main->keyBoard->add("Назад", "lk");

        $lines = [
            "<b>Пополнение баланса</b>",
            "",
            "Сумма: <b>{$this->formatMoney($sum)}</b>",
            "Способ: <b>" . e($this->getPaymentMethodLabel($method)) . "</b>",
            "",
            "Платежка умэр....",
        ];

        return $this->editMsg(implode("\n", $lines), $edit ? 0 : 3);
    }

    private function getPaymentMethodLabel(string $method): string
    {
        return match ($method) {
            "card" => "Карта",
            "manual" => "Ручная",
            default => $method,
        };
    }
}
