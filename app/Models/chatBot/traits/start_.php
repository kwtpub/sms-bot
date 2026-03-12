<?php
namespace App\Models\chatBot\traits;

trait start_
{
    public function start($edit = 1)
    {
        $this->main->keyBoard->add("Начать прозвон", "ringing");
        $this->main->keyBoard->add("Личный кабинет", "lk");
        $this->main->keyBoard->add("Помощь", "help");
        if ($this->main->user->is_admin) {
            $this->main->keyBoard->add("Админ-панель", "admin_lk");
        }

        $text = implode("\n", [
            "<b>Главный экран</b>",
            "",
            "Ваш ID: <code>" . $this->main->user->tg_id . "</code>",
            "Баланс: <b>" .
            $this->formatMoney($this->main->user->balance) .
            "</b>",
            "Скидка: <b>+" . $this->getDiscountPercent() . "%</b>",
            "",
            "Приглашай друзей и получай 10% от их трат себе на счет.",
            "<code>' . e($this->getReferralLink()) . '</code>",
        ]);

        return $this->sendOrEditMsg($edit, $text);
    }
}
