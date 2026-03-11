<?php
namespace App\Models\chatBot\traits;

use App\Models\Pay;
use App\Models\User;

trait admin
{
    public function admin_lk($edit = 1)
    {
        if (!$this->ckeckAdmin($edit)) {
            return;
        }

        $usersCount = User::count();
        $adminsCount = User::where("is_admin", 1)->count();
        $paymentsCount = Pay::count();
        $successPaymentsCount = Pay::where("status", "success")->count();

        $text = implode("\n", [
            "<b>Админ-панель</b>",
            "",
            "Пользователей: <b>" . $usersCount . "</b>",
            "Админов: <b>" . $adminsCount . "</b>",
            "Всего оплат: <b>" . $paymentsCount . "</b>",
            "Успешных оплат: <b>" . $successPaymentsCount . "</b>",
        ]);

        $this->main->keyBoard->add("История оплат", "history_payments");
        $this->main->keyBoard->add("Назад", "menu");

        return $this->sendOrEditMsg($edit, $text);
    }

    private function ckeckAdmin($edit = 1)
    {
        //Проверка прав админа
        if (!$this->main->user->is_admin) {
            $this->main->keyBoard->add("↩️ Меню", "menu");

            if ($edit) {
                $this->editMsg("У вас нет прав!");
            } else {
                $this->main->sendMessage("У вас нет прав!", 1);
            }

            return false;
        }

        return true;
    }
}
