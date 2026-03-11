<?php
namespace App\Models\chatBot\traits;

trait start_
{
    public function start($edit = 1)
    {
        $this->main->keyBoard->add("Пополнить баланс", "top_up_balance");
        $this->main->keyBoard->add("Реферальная ссылка", "referral_info");
        $this->main->keyBoard->add("Открыть меню", "menu");

        return $this->sendOrEditMsg($edit, $this->buildStartText());
    }
}
