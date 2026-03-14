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
}
