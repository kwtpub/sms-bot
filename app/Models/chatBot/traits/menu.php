<?php
namespace App\Models\chatBot\traits;

trait menu {
	public function menu($edit = 1) {
        $this->main->keyBoard->add('Старт', 'start');
        $this->main->keyBoard->add('Личный кабинет', 'lk');
        if($this->main->user->is_admin) {
            $this->main->keyBoard->add('Админочка', 'admin_lk');
        }

	    return $this->sendOrEditMsg($edit, 'А тут у нас меню..)');
	}

}
