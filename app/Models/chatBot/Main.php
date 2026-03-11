<?php

namespace App\Models\chatBot;

use App\Models\src\chatBot\Main as _Main;
use App\Models\chatBot\NewMessage as NewMessage;

class Main extends _Main
{
    public function NewMessage() {
        return (new NewMessage($this));
    }
}