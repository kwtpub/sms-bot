<?php
namespace App\Models\chatBot\traits;

trait menu
{
    public function menu($edit = 1)
    {
        $this->main->keyBoard->add("Старт", "start");
        $this->main->keyBoard->add("Личный кабинет", "lk");
        $this->main->keyBoard->add("Помощь", "help");
        if ($this->main->user->is_admin) {
            $this->main->keyBoard->add("Админ-панель", "admin_lk");
        }

        return $this->sendOrEditMsg($edit, "менюшка");
    }
}
