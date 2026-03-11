<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TelegramController;

// Route::any('/set_web_hook', [TelegramController::class, 'setWebHook']); //Установка хука тг
Route::any('/tg_callback', [TelegramController::class, 'tg_callback']); //Основная ссылка для обращения тг бота

Route::prefix('api')->group(function () { //Внутренее api
    if(!isset($_SERVER['REMOTE_ADDR']) || $_SERVER['REMOTE_ADDR'] != '127.0.0.1') return 'no localhost'; //Если не локал хост - нахуй.

    Route::any('/send_wait_messages', [TelegramController::class, 'sendWaitMessages']); //Отправка отложеных сообщений
});