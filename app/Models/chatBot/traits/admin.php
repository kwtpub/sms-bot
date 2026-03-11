<?php
namespace App\Models\chatBot\traits;

trait admin {
    public function admin_lk($edit = 1) {
    	if(!$this->ckeckAdmin($edit)) return;

        $text = "Админ-панель";
        $this->main->keyBoard->add('Назад', 'menu');

        return $this->sendOrEditMsg($edit, $text);
    }

    private function ckeckAdmin($edit = 1) { //Проверка прав админа
    	if(!$this->main->user->is_admin) {
    		$this->main->keyBoard->add('↩️ Меню', 'menu');

	        if($edit) $this->editMsg('У вас нет прав!');
	        else $this->main->sendMessage('У вас нет прав!', 1);

	        return false;
    	}

    	return true;
    }
}