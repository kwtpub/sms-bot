<?php

namespace App\Models\src\chatBot;

use Cache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Logs;
use App\Models\src\chatBot\Models\botStorage;

//Jobs
use App\Models\src\chatBot\Jobs\SendCallbackAnswerJob;
use App\Models\src\chatBot\Jobs\DeleteMsgsJob;

use App\Models\src\chatBot\Models\Transalte;

class NewMessage extends Model
{
    public function __construct($main) {
        $this->main = $main;
    }

    public function handlerCallback($callback) { //Вызывается когда нажимаются кнопки в чате(Без отправки сообщения)
        if(isset($callback['btn'])) {

            if($this->checkBindingFunction($callback['btn'])) return; //Если мы куда-то забиндились, значит там уже обработалось.

            if($this->startFunc($callback['btn'])) { //Если у нас есть такая функция на кнопку:

            }
            else {
                $this->main->sendMessage($this->getLoc('Не существует метода') . '(' . $callback['btn'] . ').. ' . $this->getLoc('Нажмите') . ' /start');
            }
        }

        $this->sendCallbackAnswer(); //Отправим чтобы кнопка погасла, мы обработали запрос!
    }

    public function sendOrEditMsg($edit, $text = 'Выберите действие:', $resend = 0, $gif = '', $file = '', $save = 0, $photos = []) {
        return $this->editMsg($text, ($edit ? $resend : 3), $gif, $file, $save, $photos);
    }

    public function getMessageId() {
        if(isset($this->main->callback_query['message']['message_id'])) {
            return $this->main->callback_query['message']['message_id'];
        }

        if(isset($this->binding['from_message'])) {
            return $this->binding['from_message'];
        }

        return 0;
    }

    public function updateMessageId($newMsgId) {
        if(isset($this->main->callback_query['message']['message_id'])) { //И запишем чтобы потом его редактировать
            $callback_query = $this->main->callback_query;
            $callback_query['message']['message_id'] = $newMsgId;

            $this->main->callback_query = $callback_query;
        }
        if(isset($this->binding['from_message'])) { //Или тут
            $binding = $this->binding;
            $binding['from_message'] = $newMsgId;

            $this->binding = $binding;
        }
    }

    public function editMsg($text = 'Выберите действие:', $resend = 0, $gif = '', $file = '', $save = 0, $photos = [], $tgParams = ['link_preview_options' => '{"is_disabled":true}'], $deleteUserMsg = 0) {
        if(str_contains($text, '[bot_start]')) {
            $botLink = 't.me/' . $this->main->bot->bot_name . '?start=';
            $text = str_replace('[bot_start]', $botLink, $text);
        }

        if($text == 'Выберите действие:') {
            $text = $this->getLoc('Выберите действие:');
        }

        if(is_string($photos)) {
            $photos = [$photos];
        }

        // Флаг: содержит ли текст заглушку для ID
        $hasIdPlaceholder = str_contains($text, '[msg_id]');

        if($hasIdPlaceholder) {
            $this->main->not_clear_keyboard = true;
        }

        // --- БЛОК НОВОЙ ОТПРАВКИ (resend == 3) ---
        if($resend == 3) { 
            if(!empty($file)) {
                $res = $this->main->sendFile($this, $text, $file, 1, $save, tgParams: $tgParams);
            } elseif(!empty($gif)) {
                $res = $this->main->sendAnimation($text, $gif, 1, tgParams: $tgParams);
            } elseif(!empty($photos)) {
                $res = $this->main->sendPhotos($text, $photos, 1, tgParams: $tgParams);
            } else {
                $res = $this->main->sendMessage($text, 1, tgParams: $tgParams);
            }

            // Если отправили текст с плейсхолдером — редактируем его сразу после отправки
            if($res && $hasIdPlaceholder && empty($file) && empty($gif) && empty($photos)) {
                $finalText = str_replace('[msg_id]', $res, $text);
                $this->main->editMessage($finalText, $res, 1, tgParams: $tgParams);
            }
            return $res;
        }

        if($deleteUserMsg && isset($this->main->req->message['message_id'])) {
            $this->main->tg->addDelete($this->main->req->message['message_id']);
        }

        $hasMedia = isset($this->main->callback_query['message']['document']) || isset($this->main->callback_query['message']['animation']) || isset($this->main->callback_query['message']['video']);

        if($hasMedia || !$this->getMessageId()) {
            $resend = 2;
        }

        if(isset($this->binding['from_message'])) {
            if($resend == 2 || $resend) {
                $this->main->tg->addDelete($this->binding['from_message']);
            }
            if(isset($this->main->req->message['message_id'])) {
                $this->main->tg->addDelete($this->main->req->message['message_id']);
            }
        }

        $shouldResend = ($resend == 2) || ($resend && isset($this->binding['from_message'])) || !empty($file) || !empty($gif) || (count($photos) > 1) || (count($photos) == 1 && !isset($this->main->callback_query['message']['photo'])) || ((empty($file) && empty($gif) && empty($photos)) && isset($this->main->callback_query['message']['photo']));

        if($shouldResend) {
            $newMsgId = $this->editMsg($text, 3, $gif, $file, $save, $photos, $tgParams);

            if($newMsgId) {
                $this->main->tg->addDelete($this->getMessageId());
                $this->updateMessageId($newMsgId);

                if(!empty($this->setBinding)) {
                    Cache::put('binding.function.user.' . $this->main->user->id, json_encode($this->binding), 600);
                }
                DeleteMsgsJob::dispatch($this->main->bot->api_key, $this->main->user->tg_id, $this->main->tg->deleteMessages, $text);
            }
            return $newMsgId;
        }

        // Попытка редактирования
        if(count($photos)) {
            $newMsgId = $this->main->editMessagePhotos($text, $photos[0], $this->getMessageId(), 1, tgParams: $tgParams);
        } else {
            // Заменяем плейсхолдер перед редактированием, если это просто текст
            $editText = $hasIdPlaceholder ? str_replace('[msg_id]', $this->getMessageId(), $text) : $text;
            $newMsgId = $this->main->editMessage($editText, $this->getMessageId(), 1, tgParams: $tgParams);
        }

        if(!$newMsgId) {
            return $this->editMsg($text, 2, $gif, $file, $save, $photos, $tgParams);
        }

        DeleteMsgsJob::dispatch($this->main->bot->api_key, $this->main->user->tg_id, $this->main->tg->deleteMessages, $text);
        return $newMsgId;
    }

    public function sendUserMsg($text) { //Отправка простого сообщения, чтобы не писать каждый раз юзера.
        $this->main->sendMessage($text, 1);
    }

    public function sendCallbackAnswer($text = '') { //Отправка в тг что мы обработали callback кнопки.
        if(!isset($this->main->callback_query['id'])) {
            Logs::log('Ошибка отправки sendCallbackAnswer, отсутствует $this->main->callback_query[\'id\']');
            return;
        }

        SendCallbackAnswerJob::dispatch($this->main->bot->api_key, $this->main->callback_query['id'], $text); //Поставим отправку в очередь.
    }

    public function bindingUserFunction($function, $callback = [], $var = '') { //Биндинг пользователя за функцией чтобы в случаи отправки сообщения вызвать эту функцию, продолжение в след функции
        $this->setBinding = true;
        $from_message = 0;
        if(isset($this->main->callback_query['message']['message_id'])) $from_message = $this->main->callback_query['message']['message_id'];
        elseif($this->binding['from_message']) $from_message = $this->binding['from_message'];
        else {
            // Logs::log('Ошибка определения id редактируемого сообщения..');
        }

        if(!empty($this->main->keyBoard->saving)) { //Если мы что-то сохраняем
            foreach($this->main->keyBoard->saving as $param) {
                if(!isset($callback[$param])) { //Если уже не установлено
                    if(isset($this->main->callback[$param])) {
                        $callback[$param] = $this->main->callback[$param];
                    }
                }
            }
        }

        $array = [
            'from_message' => $from_message,
            'callback' => $callback,
            'function' => $function,
            'var' => $var
        ];

        $this->binding = $array;

        Cache::put('binding.function.user.' . $this->main->user->id, json_encode($array), (60*60)); //Будем ожидать сообщение 60 минут.
    }

    public function checkBindingFunction($check_funcion = '') { //Проверка биндинга пользователя, и вызов функции.
        $cacheName = 'binding.function.user.' . $this->main->user->id;

        if(!Cache::has($cacheName)) return false; //Если нет в кэше.
        $this->binding = json_decode(Cache::get($cacheName), true); //Получим из кэша.

        if(!empty($check_funcion) && $check_funcion != $this->binding['function'] || isset($this->main->callback['b']) && $this->main->callback['b'] == 'b') return false; //Проверка соотвецтвия привязки к функции при callback(Кнопки, не текст!)


        if(isset($this->binding['callback'])) { //Если есть сохранёные данные:

            if(isset($this->binding['var']) && !empty($this->binding['var'])) { //Если биндим текст в переменную
                if(isset($this->main->req->message['text'])) { //Если отправлен текст
                    $binding = $this->binding;

                    $binding['callback'][$this->binding['var']] = $this->main->req->message['text']; //Запишем в переменную.
                    $this->binding = $binding;
                }
            }


            if(isset($this->main->callback)) { //Добавим данные к callback чтобы оттуда брать данные.
                $this->main->callback = array_merge($this->binding['callback'], $this->main->callback);
            }
            else { //Или присвоим по новой.
                $this->main->callback = $this->binding['callback'];
            }

            $newVars = []; //Новые переменые
            foreach ($this->main->callback as $i => $var) { //Удаление пустых переменных
                $newVars[$i] = $var;
            }
            $this->main->callback = $newVars;
        }

        $this->startFunc($this->binding['function']); //Вызов привязаной функции.

        return true;
    }

    public function showSelectList($list, $functionName, $showSeach = 0, $showCountPages = 1, $seachBtnText = '🔍 Найти', $seachText = 'Введите запрос для поиска:', $clearText = '🧹 Очистить', $canselBtn = '⬅️ Отмена', $perPage = 10, $chunkButtons = 1) {
        $page = $this->main->callback['p'] ?? 1;

        if(!empty($this->main->callback['s'])) { //Если нажат поиск
            if($this->main->callback['s'] == 'c') {
                $callback = $this->main->callback;
                $callback['si'] = 0;
                $this->main->callback = $callback;
            }
            elseif(in_array($this->main->callback['s'], ['s', 'h'])) {
                if(!empty($this->main->callback['search_text'])) { //Если текст уже передан
                    $seachStorage = botStorage::where('value', mb_strtolower($this->main->callback['search_text']))->where('type', 'search')->first();
                    if(is_null($seachStorage)) {
                        $seachStorage = botStorage::create([
                            'value' => mb_strtolower($this->main->callback['search_text']),
                            'type' => 'search'
                        ]);
                    }

                    $callback = $this->main->callback;
                    unset($callback['search_text']);
                    unset($callback['s']);
                    $this->main->callback = $callback;

                    $page = 1;
                }
                elseif($this->main->callback['s'] == 's') { //Если текст ещё не передан, и ищем не скрытно
                    $this->bindingUserFunction($functionName, ['p' => $page, 's' => 's'], 'search_text');

                    $this->main->keyBoard->add($this->getLoc($canselBtn), [$functionName, 'p' => $page, 's' => '', 'si' => ($this->main->callback['si'] ?? 0)]);

                    $this->editMsg($this->getLoc($seachText));

                    if(isset($this->main->callback_query['data'])) { //Если было нажатие по кнопке - отправим что обработали
                        $this->sendCallbackAnswer();
                    }
                    InProcces::deletee($this->main->tg_info['id']); //Разрешим новые запросы.
                    die('ok');
                }
            }
        }
        if(!isset($seachStorage) && isset($this->main->callback['si']) && is_numeric($this->main->callback['si'])) {
            $seachStorage = botStorage::find($this->main->callback['si']);
        }

        $tList = $list;

        if(isset($seachStorage) && !is_null($seachStorage)) {
            $callback = $this->main->callback;
            $callback['si'] = $seachStorage->id;
            $this->main->callback = $callback;

            $this->main->keyBoard->callback_data = $this->main->callback;
            $this->main->keyBoard->saving[] = 'si';

            $seachString = $seachStorage->value;
            $tList = [];

            foreach($list as $key => $l) {
                $localized = $this->getLoc($l);
                if(mb_stripos($localized, $seachString) !== false) {
                    $tList[$key] = $localized; // Сохраняем ключ
                }
            }
        }

        $selectedList = [];

        $cacheName = 'selected_list.' . $functionName . '.user.' . $this->main->user->id;
        if(Cache::has($cacheName)) {
            $selectedList = json_decode(Cache::get($cacheName), true);
        }

        $totalItems = count($tList);
        $countPages = ceil($totalItems / $perPage);

        if(isset($this->main->callback['i']) && isset($list[$this->main->callback['i']])) {
            if(in_array($this->main->callback['i'], $selectedList)) {
                unset($selectedList[array_search($this->main->callback['i'], $selectedList)]);
            } else {
                $selectedList[] = $this->main->callback['i'];
            }
            Cache::put($cacheName, json_encode($selectedList), (60 * 60 * 24));

            $callback = $this->main->callback;
            unset($callback['i']);
            $this->main->callback = $callback;
        }

        // Обрезаем список для текущей страницы
        $tList = array_slice($tList, ($page - 1) * $perPage, $perPage, true);

        $listChunk = array_chunk($tList, $chunkButtons, true); //Разделим по строчкам

        foreach($listChunk as $i => $tList) {
            foreach ($tList as $key => $btn) {
                $this->main->keyBoard->add(
                    ((in_array($key, $selectedList) ? '✅ ' : '') . $this->getLoc($list[$key])),
                    [$functionName, 'i' => $key, 'p' => $page],
                    $i
                );
            }
        }

        if($showSeach) { //Если отображаем поиск
            $this->main->keyBoard->add(
                $this->getLoc($seachBtnText),
                [$functionName, 's' => 's', 'p' => $page],
                99
            );

            if(!empty($this->main->callback['si'])) {
            $this->main->keyBoard->add(
                $this->getLoc($clearText),
                [$functionName, 's' => 'c', 'p' => 1],
                99
            );
            }
        }

        // Добавляем кнопки пагинации
        if($page > 1) {
            $this->main->keyBoard->add('<<', [$functionName, 'p' => ($page - 1)], 100);
        }
        if($showCountPages && $countPages > 1) {
            $this->main->keyBoard->add($page . '/' . $countPages, array_merge([$functionName], $this->main->callback), 100);
        }
        if($page < $countPages) {
            $this->main->keyBoard->add('>>', [$functionName, 'p' => ($page + 1)], 100);
        }

        $this->bindingUserFunction($functionName, ['p' => $page, 's' => 'h'], 'search_text'); //Ловим строку всегда

        return $selectedList;
    }

    public function startFunc($func_name) { //Запуск нужного меню(В том числе биндинг)
        $cacheName = 'binding.function.user.' . $this->main->user->id;

        if(Cache::has($cacheName)) {
            Cache::forget($cacheName); //Удалим из кэша
        }

        if(method_exists($this, $func_name)) { //Если у нас есть такая функция на кнопку:
            $this->$func_name();

            return true;
        }
        Logs::log('Не существует метода..', $func_name);

        return false;
    }

    public function getLoc($text, $language = '') {
        if($this->main->user->language == 'ru') { //Если ру - не меняем
            return $text;
        }

        return Transalte::translate($text, !empty($language) ? $language : $this->main->user->language);
    }
}
