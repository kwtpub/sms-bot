<?php
namespace App\Models\chatBot\traits;
use App\Models\Pay;
trait lk
{
    public function lk($edit = 1)
    {
        $this->main->keyBoard->add("Пополнить баланс", "top_up_balance");
        $this->main->keyBoard->add("История заказов", "history_orders");
        $this->main->keyBoard->add("История оплат", "history_payments");
        $this->main->keyBoard->add("Реферальная ссылка", "referral_info");
        $this->main->keyBoard->add("Назад", "menu");

        return $this->sendOrEditMsg($edit, $this->buildCabinetText());
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

            return $this->sendOrEditMsg(
                $edit,
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

            return $this->sendOrEditMsg(
                $edit,
                "Сумма: <b>{$this->formatMoney(
                    $sum,
                )}</b>\nВыберите способ оплаты.",
            );
        }

        $pay = $this->createPaymentDraft($sum, $method);
        $paymentSystem = $this->getPaymentSystemStub($pay);

        $this->main->keyBoard->add("Проверить статус", [
            "check_payment_status",
            "pay_record_id" => $pay->id,
        ]);
        $this->main->keyBoard->add("История оплат", "history_payments");
        $this->main->keyBoard->add("Назад", "lk");

        $lines = [
            "<b>Заявка на пополнение создана</b>",
            "",
            "Сумма: <b>{$this->formatMoney($pay->sum)}</b>",
            "Способ: <b>" .
            e($this->getPaymentMethodLabel($pay->method)) .
            "</b>",
            "Статус: <b>" . e($pay->status) . "</b>",
            "Провайдер: <b>" . e($paymentSystem["provider"]) . "</b>",
            "ID платежа: <code>" . e($pay->pay_id) . "</code>",
        ];

        if (!empty($paymentSystem["payment_url"])) {
            $lines[] =
                "Ссылка на оплату: <code>" .
                e($paymentSystem["payment_url"]) .
                "</code>";
        } else {
            $lines[] =
                "Платежная система пока не подключена. Создан черновик платежа под будущую интеграцию.";
        }

        return $this->sendOrEditMsg($edit, implode("\n", $lines));
    }

    public function check_payment_status($edit = 1)
    {
        $payRecordId = (int) ($this->main->callback["pay_record_id"] ?? 0);
        $pay = Pay::where("id", $payRecordId)
            ->where("user_id", $this->main->user->id)
            ->first();

        if (is_null($pay)) {
            $this->main->keyBoard->add("Назад", "lk");

            return $this->sendOrEditMsg($edit, "Платёж не найден.");
        }

        $createdData = [];
        if (!empty($pay->created_data)) {
            $createdData = json_decode($pay->created_data, true) ?: [];
        }

        $this->main->keyBoard->add("История оплат", "history_payments");
        $this->main->keyBoard->add("Назад", "lk");

        $lines = [
            "<b>Статус платежа</b>",
            "",
            "ID платежа: <code>" . e($pay->pay_id) . "</code>",
            "Сумма: <b>{$this->formatMoney($pay->sum)}</b>",
            "Способ: <b>" .
            e($this->getPaymentMethodLabel($pay->method)) .
            "</b>",
            "Статус: <b>" . e($pay->status) . "</b>",
        ];

        if (!empty($createdData["provider"])) {
            $lines[] = "Провайдер: <b>" . e($createdData["provider"]) . "</b>";
        }

        if (!empty($createdData["message"])) {
            $lines[] = e($createdData["message"]);
        }

        return $this->sendOrEditMsg($edit, implode("\n", $lines));
    }

    private function createPaymentDraft(float $sum, string $method): Pay
    {
        $pay = Pay::create([
            "user_id" => $this->main->user->id,
            "sum" => $sum,
            "pay_id" => "pay_" . uniqid(),
            "method" => $method,
            "status" => "created",
        ]);

        $paymentSystem = $this->getPaymentSystemStub($pay);
        $pay->created_data = json_encode(
            $paymentSystem,
            JSON_UNESCAPED_UNICODE,
        );
        $pay->save();

        return $pay;
    }

    private function getPaymentSystemStub(Pay $pay): array
    {
        return [
            "enabled" => false,
            "provider" => "stub",
            "payment_url" => null,
            "external_payment_id" => null,
            "pay_record_id" => $pay->id,
            "message" => "Интеграция с платежной системой ещё не подключена.",
        ];
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
