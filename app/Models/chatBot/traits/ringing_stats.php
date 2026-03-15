<?php

namespace App\Models\chatBot\traits;

use App\Models\Order;

trait ringing_stats
{
    public function ringing_stats()
    {
        $orderId = (int) ($this->main->callback["order_id"] ?? 0);

        if (!$orderId) {
            $this->main->keyBoard->add("Назад", "start");
            return $this->editMsg("Заказ не найден.");
        }

        $order = Order::where("id", $orderId)
            ->where("user_id", $this->main->user->id)
            ->first();

        if (!$order) {
            $this->main->keyBoard->add("Назад", "start");
            return $this->editMsg("Заказ не найден.");
        }

        $statusLabel = match ($order->status) {
            "pending"    => "Ожидает",
            "processing" => "В процессе",
            "done"       => "Завершён",
            "failed"     => "Не удался",
            default      => $order->status,
        };

        $typeLabel = $order->type === "triple" ? "Тройной" : "Одиночный";

        $lines = [
            "<b>Статистика прозвона</b>",
            "",
            "Номер: <b>{$order->phone}</b>",
            "Имя: <b>" . e($order->name) . "</b>",
            "Тип: <b>{$typeLabel}</b>",
            "Стоимость: <b>{$this->formatMoney($order->price)}</b>",
            "Статус: <b>{$statusLabel}</b>",
        ];

        if ($order->total_sites !== null) {
            $lines[] = "";
            $lines[] = "Сайтов обработано: <b>{$order->good} / {$order->total_sites}</b>";
        }

        if ($order->rate !== null) {
            $lines[] = "Рейтинг: <b>{$order->rate}</b>";
        }

        if ($order->elapsed_time !== null) {
            $lines[] = "Время работы: <b>{$order->elapsed_time}</b>";
        }

        if ($order->link_report) {
            $lines[] = "";
            $lines[] = "Отчёт: {$order->link_report}";
        }

        $this->main->keyBoard->add("Назад", "history_orders");

        return $this->editMsg(implode("\n", $lines));
    }
}
