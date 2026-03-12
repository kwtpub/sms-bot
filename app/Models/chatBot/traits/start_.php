<?php
namespace App\Models\chatBot\traits;

trait start_
{
    public function start($edit = 1)
    {
        $this->main->keyBoard->add("Начать прозвон", "ringing");
        $this->main->keyBoard->add("Реферальная ссылка", "referral_info");
        $this->main->keyBoard->add("Личный кабинет", "lk");
        $this->main->keyBoard->add("Помощь", "help");
        if ($this->main->user->is_admin) {
            $this->main->keyBoard->add("Админ-панель", "admin_lk");
        }

        return $this->sendOrEditMsg($edit, $this->buildStartText());
    }
}
