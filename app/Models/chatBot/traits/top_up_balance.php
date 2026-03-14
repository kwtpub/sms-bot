<?php
namespace App\Models\chatBot\traits;
trait top_up_balance
{
    public function top_up_balance()
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

        return $this->editMsg(implode("\n", $lines));
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
