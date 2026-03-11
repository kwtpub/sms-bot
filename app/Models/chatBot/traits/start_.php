<?php
namespace App\Models\chatBot\traits;

trait start_ {
    public function start($edit = 1) {
        $this->main->keyBoard->add('Открыть меню', 'menu');

        return $this->sendOrEditMsg($edit, 'Тут у нас start - привет)');
    }
}