<?php

namespace App\Models\src\chatBot;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Bot;
use App\Models\User;
use App\Models\Logs;
use App\Models\Config;
use App\Models\src\chatBot\Models\botStorage;

use App\Models\src\chatBot\Telegram;

use App\Models\src\chatBot\KeyBoard;

use App\Models\src\chatBot\InProcces;

use Cache;

class Main extends Model
{   
    function __construct($r) {
        $this->req = $r;
        $this->keyBoard = new KeyBoard();
    }

    public function handler() { //Точка входа всех запросов от ТГ
        if(empty($this->req->bot_id) || !is_numeric($this->req->bot_id)) {
            Logs::log('Отсутствие/Несоответствие типа bot_id, параметры: ', $this->req->all());
            return 'error';
        }

        $this->bot = Bot::find($this->req->bot_id);

        if(is_null($this->bot)) {
            Logs::log('Бота нет в базе.., параметры: ', $this->req->all());
            return 'error';
        }
        
        if($this->bot->secret != $this->req->header('X-Telegram-Bot-Api-Secret-Token')) {
            Logs::log('Неверный секретный ключ бота, параметры: ', [$this->req->header('X-Telegram-Bot-Api-Secret-Token'), $this->req->all()]);
            return 'error';
        }

        $this->tg = new Telegram($this->bot->api_key); //Загрузим настройки в класс отправки запросов тг

        // Logs::log('TG CALLBACK', $this->req->all());

        if(isset($this->req->message)) { //Если это текстовое сообщение, либо кнопки в клавиатуре
            if(isset(Config::config()->channel_id)) {
                if(isset($this->req->message['chat']['id']) == Config::config()->channel_id) {
                    $this->tg_info = $this->req->message['from'];

                    if(isset($this->req->message['new_chat_member'])) {
                        return $this->newGroupMember();
                    }
                    elseif(isset($this->req->message['left_chat_member'])) {
                        return $this->leftGroupMember();
                    }
                }
            }
            elseif($this->req->message['from']['id'] != $this->req->message['chat']['id']) { //Сообщение не в лс
                // Logs::log('Сообщение боту не в лс, параметры : ', $this->req->all());
                return 'error';
            }

            $this->tg_info = $this->req->message['from'];
        }
        elseif(isset($this->req->callback_query)) { //Если это клик по кнопке, в чате
            if(!isset($this->req->callback_query['from']['id'])) {
                // Logs::log('Отсутствие параметра callback_query->from->id, параметры: : ', $this->req->all());
                return 'error';
            }

            if(!isset($this->req->callback_query['data'])) {
                // Logs::log('Отсутствие параметра callback_query->data, параметры: : ', $this->req->all());
                return 'error';
            }

            $this->tg_info = $this->req->callback_query['from'];
        }
        else {
            // Logs::log('Отсутствие параметра (message|callback_query), параметры: : ', $this->req->all());
            return 'error';
        }

        // $this->tg->getMyName();


        // if(!in_array($this->tg_info['id'], [77267, 977492416])) {
        //     $this->tg->send_request('sendMessage', [
        //         'text' => 'Доступ временно доступен только администрации!',
        //         'chat_id' => $this->tg_info['id'],
        //         'parse_mode' => 'HTML'
        //     ]);
        //     return;
        // }

        $start = $this->checkStart(); //Фикс сообщения /start ...., и привязка рефки

        $user = User::getOrCreate(
            $this->tg_info['id'],
            isset($this->tg_info['username']) ? $this->tg_info['username'] : '',
            $this->bot,
            $start['ref'],
            $start['meta']
        );

        // if(in_array($this->tg_info['id'], [977492416])) {
        //     $this->tg->send_request('sendMessage', [
        //         'text' => json_encode($start),
        //         'chat_id' => $this->tg_info['id'],
        //         'parse_mode' => 'HTML'
        //     ]);
        // }

        $this->user = $user['user'];

        if(InProcces::check($this->tg_info['id'])) { //Защита от заспамивания, и каких-либо повторных действий. Хоть тг по очереди отправляет. Доверяй но проверяй), мы-же не хотим получить два повторных вывода по ошибке)
            $this->tg->send_request('sendMessage', [
                'text' => $this->NewMessage()->getLoc('Ваш предыдущий запрос ещё обрабатывается. Дождитесь ответа.'),
                'chat_id' => $this->tg_info['id'],
                'parse_mode' => 'HTML'
            ]);
            return false;
        }

        if(!$this->user->send_start) { //Фикс начала переписки, если пользователь ещё не отправлялся началный текст..
            $this->user->send_start = 1;
            $this->user->save();

            $message = $this->req->message;
            $message['text'] = '/start';
            $this->req->message = $message;
        }

        if($this->req->message && isset($this->req->message['caption'])) { //Если есть описание к фото.
            $message = $this->req->message;
            $message['text'] = $this->req->message['caption'];

            $this->req->message = $message;
        }
        elseif(!empty($this->req->message['photo'])) {
            $message = $this->req->message;
            $message['text'] = $this->req->message['caption'] ?? '';

            $this->req->message = $message;
        }
        elseif(!empty($this->req->message['document'])) {
            $message = $this->req->message;
            $message['text'] = $this->req->message['caption'] ?? '';

            $this->req->message = $message;
        }
        elseif(!empty($this->req->message['video'])) {
            $message = $this->req->message;
            $message['text'] = $this->req->message['caption'] ?? '';

            $this->req->message = $message;
        }
        elseif(!empty($this->req->message['voice'])) {
            $message = $this->req->message;
            $message['text'] = $this->req->message['caption'] ?? '';

            $this->req->message = $message;
        }
        elseif(!empty($this->req->message['sticker']['emoji'])) {
            $message = $this->req->message;
            $message['text'] = $this->req->message['sticker']['emoji'];

            $this->req->message = $message;
        }

        // Logs::log('tg_callback', $this->req->all());



        if(isset($this->req->message['text'])) { //Если у нас есть текст сообщения:
            $this->addEntitiesMessage(); //Выстановим тэги <b>, <code>, и тд

            $this->NewMessage()->handler();
            if($user['created']) { //Проверка и выдача подписки если подарили до захода в бота:
                $this->eventNewUser();
            }
        }
        elseif(isset($this->req->callback_query['data'])) { //Если это callback кнопка:
            $this->callback = botStorage::handlerCallback($this->req->callback_query['data']); //Подготовим данные/распакуем/получим из бд и тд.

            $this->callback_query = $this->req->callback_query;

            $this->NewMessage()->handlerCallback($this->callback);
        }
        else {
            $this->handlerOther();
        }
        InProcces::deletee($this->tg_info['id']); //Разрешим новые запросы.

        return 'ok';
    }

    public function getAttachments() {
        $attachments = [];

        if(isset($this->req->message['sticker'])) {
            $attachments['sticker'] = $this->req->message['sticker'];
        }
        elseif(isset($this->req->message['photo'])) {
            $attachments['photo'] = $this->req->message['photo'];
        }
        elseif(isset($this->req->message['document'])) {
            $attachments['photo'] = $this->req->message['document'];
        }

        if(isset($this->req->message['media_group_id'])) {
            $attachments['media_group_id'] = $this->req->message['media_group_id'];
        }

        if(empty($attachments)) {
            return null;
        }

        return $attachments;
    }

    private function addEntitiesMessage() {
        if (!isset($this->req->message['entities']) && !isset($this->req->message['caption_entities'])) {
            return;
        }

        $text = $this->req->message['text'] ?? $this->req->message['caption'];
        $entities = $this->req->message['entities'] ?? $this->req->message['caption_entities'];

        // Преобразуем в массив UTF-16 code units с сохранением суррогатных пар
        $utf16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        $codeUnits = unpack('v*', $utf16); // v* = unsigned short (2 байта, little-endian)
        $codeUnits = array_values($codeUnits);

        $openTags = [];
        $closeTags = [];

        foreach ($entities as $entity) {
            $start = $entity['offset'];
            $end = $start + $entity['length'];

            $tag = match ($entity['type']) {
                'bold' => 'b',
                'italic' => 'i',
                'underline' => 'u',
                'strikethrough' => 's',
                'code' => 'code',
                'pre' => 'pre',
                'spoiler' => ['<span class="tg-spoiler">', '</span>'],
                'text_link' => [
                    '<a href="' . htmlspecialchars($entity['url'] ?? '', ENT_QUOTES, 'UTF-8') . '">',
                    '</a>'
                ],
                default => null
            };

            if ($tag !== null) {
                if (is_array($tag)) {
                    $openTags[$start][] = $tag[0];
                    $closeTags[$end][] = $tag[1];
                } else {
                    $openTags[$start][] = "<$tag>";
                    $closeTags[$end][] = "</$tag>";
                }
            }
        }

        // Собираем обратно, с учётом UTF-16 code units
        $result = '';
        $total = count($codeUnits);

        for ($i = 0; $i <= $total; $i++) {
            if (isset($closeTags[$i])) {
                foreach (array_reverse($closeTags[$i]) as $tag) {
                    $result .= $tag;
                }
            }

            if ($i < $total) {
                if (isset($openTags[$i])) {
                    foreach ($openTags[$i] as $tag) {
                        $result .= $tag;
                    }
                }

                // Текущий символ (code unit)
                $utf16char = pack('v', $codeUnits[$i]);

                // Если это суррогатная пара — склеиваем два подряд
                if ($i + 1 < $total && $codeUnits[$i] >= 0xD800 && $codeUnits[$i] <= 0xDBFF && $codeUnits[$i + 1] >= 0xDC00 && $codeUnits[$i + 1] <= 0xDFFF) {
                    $utf16char .= pack('v', $codeUnits[$i + 1]);
                    $i++; // пропускаем следующий, он часть пары
                }

                // Конвертируем обратно в UTF-8
                $result .= mb_convert_encoding($utf16char, 'UTF-8', 'UTF-16LE');
            }
        }

        $message = $this->req->message;
        $message['text'] = $result;
        $this->req->message = $message;
    }

    public function eventNewUser() {}
    public function newGroupMember() {}
    public function leftGroupMember() {}
    public function handlerOther() {}

    public function saveParams($params) {
        $this->keyBoard->saveParams($params, $this->callback);
    }

    public function addParamscallback($params) {
        $callback = $this->callback;

        foreach($params as $key => $param) {
            $callback[$key] = $param;
        }

        $this->callback = $callback;
    }

    public function sendMessage($text, $inline = 0, $tgParams = []) { //Отправка сообщений с клавиатурой и тд.
        $this->keyBoard->is_admin = $this->user->is_admin;

        $data = $this->tg->send_request('sendMessage', [
            'text' => $text,
            'chat_id' => $this->user->tg_id,
            'parse_mode' => 'HTML',
            'reply_markup' => $this->keyBoard->generate($inline)
        ], tgParams: $tgParams);

        if(isset($data['result']['message_id'])) {
            if(!isset($this->not_clear_keyboard) && !$this->not_clear_keyboard) {
                $this->keyBoard->clear();
            }
            return $data['result']['message_id'];
        }

        return 0;
    }

    public function sendFile($NewMessage, $text, $file, $inline = 1, $save = 0, $retry = 0, $tgParams = []) { //Отправка сообщений с клавиатурой и тд.
        $newDownload = 0;

        $document = 0;
        if($file instanceof \CURLFile) {
            $newDownload = 1;
            $document = $file;
        }
        else {
            $cacheName = 'cache.file.' . $file . '.bot.' . $this->bot->id;

            if ($save && Cache::has($cacheName)) {
                $document = Cache::get($cacheName);
            }
            else {
                $file = str_replace((config('app.url') ?? env('APP_URL')), 'http://localhost', $file); //Замена с домена, на локальный адрес.

                $newDownload = 1;

                $fileExtension = pathinfo($file, PATHINFO_EXTENSION);

                // Для zip оставляем оригинальный путь, для остальных создаем CURLFile
                $document = $fileExtension === 'zip' ? $file : new \CURLFile($file);
            }
        }

        if($newDownload) { //Если файл будет загружать заново
            $this->not_clear_keyboard = 1;
            $NewMessage->editMsg(($NewMessage->getLoc('Выполняется загрузка файла ') . '(' . ($retry + 1). ')...') . "\n\n" . $text);
            $this->not_clear_keyboard = 0;
        }

        $this->keyBoard->is_admin = $this->user->is_admin;

        $data = $this->tg->send_request('sendDocument', [
            'caption' => $text,
            'document' => $document,
            'chat_id' => $this->user->tg_id,
            'parse_mode' => 'HTML',
            'reply_markup' => $this->keyBoard->generate($inline)
        ], tgParams: $tgParams);

        if(isset($data['result']['message_id'])) {
            if($save && isset($cacheName)) {
                Cache::forever($cacheName, $data['result']['document']['file_id']);
            }

            if(!isset($this->not_clear_keyboard) && !$this->not_clear_keyboard) {
                $this->keyBoard->clear();
            }
            return $data['result']['message_id'];
        }
        elseif($retry < 4) {
            // Logs::log('Переотправляем файл..');
            $this->sendFile($NewMessage, $text, $file, $inline, $save, ($retry + 1));
        }
        else {
            $NewMessage->editMsg(($NewMessage->getLoc('Ошибка загрузки файла... Свяжитесь с администратором!') . "\n\n") . $text);
        }

        return 0;
    }

    public function sendAnimation($text, $animation, $inline = 1, $tgParams = []) { //Отправка сообщений с клавиатурой и тд.
        $this->keyBoard->is_admin = $this->user->is_admin;

        $data = $this->tg->send_request('sendAnimation', [
            'caption' => $text,
            'animation' => $animation,
            'chat_id' => $this->user->tg_id,
            'parse_mode' => 'HTML',
            'reply_markup' => $this->keyBoard->generate($inline)
        ], tgParams: $tgParams);

        if(isset($data['result']['message_id'])) {
            if(!isset($this->not_clear_keyboard) && !$this->not_clear_keyboard) {
                $this->keyBoard->clear();
            }
            return $data['result']['message_id'];
        }

        return 0;
    }

    public function sendPhotos($text, $photos, $inline = 1, $tgParams = []) { //Отправка сообщений с клавиатурой и тд.
        $this->keyBoard->is_admin = $this->user->is_admin;

        $newPhotos = [];
        if(count($photos) > 1) { //Если несколько фото значит - sendMediaGroup
            foreach($photos as $photo) {
                $newPhotos[] = [
                    'type' => 'photo',
                    'media' => $photo
                ];
            }
            $newPhotos[0]['caption'] = $text;
            $newPhotos[0]['parse_mode'] = 'HTML';

            $data = $this->tg->send_request('sendMediaGroup', [
                'caption' => $text,
                'media' => json_encode($newPhotos),
                'chat_id' => $this->user->tg_id
            ], tgParams: $tgParams);

            if(isset($data['result'][0]['message_id'])) {
                $messageIds = [];

                foreach($data['result'] as $msg) {
                    if(isset($msg['message_id'])) {
                        $messageIds[] = $msg['message_id'];
                    }
                }
                $data['result']['message_id'] = $messageIds;
            }
        }
        else { //Если одно фото значит - sendPhoto
            $data = $this->tg->send_request('sendPhoto', [
                'caption' => $text,
                'photo' => $photos[0],
                'chat_id' => $this->user->tg_id,
                'parse_mode' => 'HTML',
                'reply_markup' => $this->keyBoard->generate($inline)
            ], tgParams: $tgParams);
        }

        if(isset($data['result']['message_id'])) {
            if(!isset($this->not_clear_keyboard) && !$this->not_clear_keyboard) {
                $this->keyBoard->clear();
            }
            return $data['result']['message_id'];
        }

        return 0;
    }

    public function editMessagePhotos($text, $photo, $messageId, $inline = 1, $tgParams = []) {
        $data = $this->tg->send_request('editMessageMedia', [
            'message_id' => $messageId,
            'media' => json_encode([
                'caption' => $text,
                'parse_mode' => 'HTML',
                'type' => 'photo',
                'media' => $photo
            ]),
            'chat_id' => $this->user->tg_id,
            'reply_markup' => $this->keyBoard->generate($inline)
        ], tgParams: $tgParams);

        if(isset($data['result']['message_id'])) {
            if(!isset($this->not_clear_keyboard) && !$this->not_clear_keyboard) {
                $this->keyBoard->clear();
            }
            return $data['result']['message_id'];
        }

        return 0;
    }

    public function editMessage($text, $messageId, $inline = 0, $tgParams = []) { //Редактирование сообщения
        $this->keyBoard->is_admin = $this->user->is_admin;

        $data = $this->tg->send_request('editMessageText', [
            'text' => $text,
            'chat_id' => $this->user->tg_id,
            'message_id' => $messageId,
            'parse_mode' => 'HTML',
            'reply_markup' => $this->keyBoard->generate($inline)
        ], tgParams: $tgParams);

        if(isset($data['result']['message_id'])) {
            if(!isset($this->not_clear_keyboard) && !$this->not_clear_keyboard) {
                $this->keyBoard->clear();
            }
            return $data['result']['message_id'];
        }
        elseif(isset($data['description']) && $data['description'] == 'Bad Request: message is not modified: specified new message content and reply markup are exactly the same as a current content and reply markup of the message') { //В сообщении нечего не поменялось
            $buttons = $this->keyBoard->buttons;
            $this->keyBoard->buttons = [];
            $this->keyBoard->add('👍', ['x' => 'x']);
            $this->editMessage($text, $messageId, $inline);

            usleep(500000);

            $this->keyBoard->buttons = $buttons;
            return $this->editMessage($text, $messageId, $inline);
        }

        return 0;
    }

    public function getUserName() { //Получение имини пользователя в тг, в зависимости от того какой метод использовался
        if(isset($this->tg_info['first_name'])) return htmlspecialchars($this->tg_info['first_name']);

        return 'undefined';
    }

    public function checkStart() { //Фикс сообщения, и отдача второй части /start xxx.
        if(!isset($this->req->message['text'])) {
            return [
                'ref' => 0,
                'meta' => ''
            ];
        }

        $expMessage = explode('/start ', $this->req->message['text']);

        if(!isset($expMessage[1])) {
            return [
                'ref' => 0,
                'meta' => ''
            ];
        }


        $message = $this->req->message;
        $message['text'] = '/start';
        $message['text_start'] =  $expMessage[1];
        $this->req->message = $message;

        $explodeWords = explode(' ', $expMessage[1]);

        $ref = 0;
        $meta = '';

        foreach($explodeWords as $word) {
            if(is_numeric($word)) {
                $ref = $word;
            }
            else {
                $explodeMeta = explode('m_', $word);

                if(isset($explodeMeta[1])) {
                    $meta = $explodeMeta[1];
                }
            }
        }

        return [
            'ref' => $ref,
            'meta' => $meta
        ];
    }
}
