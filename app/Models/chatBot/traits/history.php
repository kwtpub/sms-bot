<?php

namespace App\Models\chatBot\traits;

use App\Models\Pay;

trait history
{
    public function history_orders($edit = 1)
    {
        $this->main->keyBoard->add('Назад', 'lk');

        $text = implode("\n", [
            '<b>История заказов</b>',
            '',
            'Этот раздел подготовлен под следующий этап.',
            'После появления таблицы заказов здесь будет список с датой, номером, именем, режимом, стоимостью и статусом.',
        ]);

        return $this->sendOrEditMsg($edit, $text);
    }

    public function history_payments($edit = 1)
    {
        $this->main->keyBoard->add('Назад', 'lk');

        $payments = Pay::where('user_id', $this->main->user->id)
            ->latest()
            ->take(10)
            ->get();

        $lines = ['<b>История оплат</b>', ''];

        if ($payments->isEmpty()) {
            $lines[] = 'Оплат пока нет.';
        } else {
            foreach ($payments as $payment) {
                $lines[] = implode(' | ', [
                    $payment->created_at?->format('d.m.Y H:i') ?? '-',
                    $this->formatMoney($payment->sum),
                    e($payment->method),
                    e($payment->status),
                ]);
            }
        }

        return $this->sendOrEditMsg($edit, implode("\n", $lines));
    }
}
