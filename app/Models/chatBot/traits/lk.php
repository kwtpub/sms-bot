<?php
namespace App\Models\chatBot\traits;
use App\Models\Pay;
trait lk
{
    public function lk()
    {
        $this->main->keyBoard->add("Назад", "menu");
        $this->main->keyBoard->add("Пополнить баланс", "top_up_balance");
        $this->editMsg("Ваш баланс: " . $this->main->user->balance);
    }

    public function top_up_balance()
    {
        if (
            empty($this->main->callback["sum"]) ||
            !intval($this->main->callback["sum"])
        ) {
            $this->bindingUserFunction("top_up_balance", [], "sum");
            $this->main->keyBoard->add("Отмена", "lk");
            return $this->editMsg("Введите сумму: ");
        }
        $pay = Pay::create([
            "user_id" => $this->main->user->id,
        ]);
        $this->editMsg("Вы ввели сумму:");
    }
}
