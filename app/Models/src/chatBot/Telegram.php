<?php

namespace App\Models\src\chatBot;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Logs; //temp

class Telegram extends Model
{
    public $deleteMessages = [];
    public $useProxy = false;

    function __construct($secret_key) {
        $this->apiKey = $secret_key;
    }

    public function send_request($method, $data_req = [], $get_curl = 0, $tgParams = []) { //Отправка в тг.
        if(!empty($tgParams)) {
            $data_req = array_merge($data_req, $tgParams);
        }

        $ch = curl_init();
        $url = "https://api.telegram.org/bot$this->apiKey/$method";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_req);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if($this->useProxy) {
            curl_setopt($ch, CURLOPT_PROXY, "84.201.176.182:51330");
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "QFBBhcujqn:yNdWOyZYWW");
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Общий тайм-аут выполнения
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // Тайм-аут подключения

        if($get_curl) return $ch;

        $result = curl_exec($ch);

        $data = json_decode($result, true);

        if(!isset($data['ok']) || !$data['ok']) { //Если-что-то пошла не так - логируем.
            if(($method == 'editMessageText' && isset($data['description']) && $data['description'] == 'Bad Request: message is not modified: specified new message content and reply markup are exactly the same as a current content and reply markup of the message') || ($method == 'getChatMember' && isset($data['description']) && $data['description'] == 'Bad Request: PARTICIPANT_ID_INVALID') || (isset($data['description']) && $data['description'] == 'Forbidden: bot was blocked by the user')) {
                
            }
            else {
                Logs::log('Ошибка в ответе, метод TG - ' . $method, [$data_req, $data]);
            }
        }
        // else Logs::log('Вызван метод TG - ' . $method, [$data_req, $data]);

        return $data;
    }

    public function setWebHooks($url, $secret_token, $retry = 0) { //Установка webhook
        $data = $this->send_request('setWebhook', [
            'url' => $url,
            'secret_token' => $secret_token,
            'allowed_updates' => [
                'message',
                'chat_member',
                'new_chat_members',
                'edited_message',
                'channel_post',
                'edited_channel_post'
            ]
        ]);

        if(!isset($data['ok']) || !$data['ok']) {
            if($retry < 5) {
                sleep(1); //Возможно ошибка из-за большого кол-ва запросов.
                return $this->setWebHooks($url, $secret_token, ($retry + 1));
            }
            return ['success' => false, 'error' => 'error set webhook', 'data' => $data];
        }

        return ['success' => true];
    }

    public function deleteWebhook() { //Удаление webhook
        $data = $this->send_request('deleteWebhook', [
            'drop_pending_updates' => true
        ]);

        if(!isset($data['ok']) || !$data['ok']) {
            return ['success' => false, 'error' => 'error set webhook', 'data' => $data];
        }

        return ['success' => true];
    }

    public function getMyName($retry = 0) { //Получение имени бота.
        $data = $this->send_request('getMe');
        if(!isset($data['ok']) || !$data['ok'] || !isset($data['result']['username'])) {
            if($retry < 5 && isset($data['description']) && $data['description'] != 'Unauthorized') {
                sleep(1); //Возможно ошибка из-за большого кол-ва запросов.
                return $this->getMyName(($retry + 1));
            }
            return ['success' => false, 'data' => $data];
        }

        Logs::log('getMyName: ', $data);

        return ['success' => true, 'id' => $data['result']['id'], 'name' => $data['result']['username'], 'first_name' => $data['result']['first_name']];
    }

    public function setMyName($name) { //Изменение имини бота
        $data = $this->send_request('setMyName', [
            'name' => $name
        ]);

        if(!isset($data['ok']) || !$data['ok']) return ['success' => false, 'data' => $data];

        return true;
    }

    public function setMyDescription($desc) { //Изменение описания
        $data = $this->send_request('setMyDescription', [
            'description' => $desc
        ]);

        if(!isset($data['ok']) || !$data['ok']) return ['success' => false, 'data' => $data];

        return true;
    }

    public function setMyShortDescription($desc) { //Изменение краткого описания
        $data = $this->send_request('setMyShortDescription', [
            'description' => $desc
        ]);

        if(!isset($data['ok']) || !$data['ok']) return ['success' => false, 'data' => $data];

        return true;
    }

    public function getBotTgId() { //Получения id бота из апи ключа
        $exp = explode(':', $this->apiKey);
        if(!is_numeric($exp[0])) return false; //Если не число

        return $exp[0];
    }

    public function deleteMsg($chatId, $msgsID) {
        if(!is_array($msgsID)) $msgsID = [$msgsID]; //Если не массив, сделаем массив.

        $results = [];
        $chunks = array_chunk($msgsID, 100);

        foreach($chunks as $chunk) {
            $results[] = $this->send_request('deleteMessages', [
                'chat_id' => $chatId,
                'message_ids' => json_encode($chunk)
            ]);
        }

        return $results;
    }

    public function addDelete($msgsIDS) {
        if(!is_array($msgsIDS)) {
            $msgsIDS = [$msgsIDS];
        }

        foreach($msgsIDS as $msgsID) {
            if(!$msgsID) continue;
            if(!in_array($msgsID, $this->deleteMessages)) {
                $this->deleteMessages[] = $msgsID;
            }
        }

        return true;
    }

    public function deleteMsgs($chatId) {
        if (empty($this->deleteMessages)) return false;

        $result = [];

        $result = $this->deleteMsg($chatId, $this->deleteMessages);

        $this->deleteMessages = [];
        return $result;
    }


    public function setMyCommands($commands, $retry = 0) {
        $data = $this->send_request('setMyCommands', [
            'commands' => json_encode($commands)
        ]);

        if(!isset($data['ok']) || !$data['ok']) {
            if($retry < 5) {
                return $this->setMyCommands($commands, ($retry + 1));
            }

            return ['success' => false, 'error' => 'error set commands', 'data' => $data];
        }

        return ['success' => true];
    }

    public function exportChatInviteLink($chatId, $member_limit = 0) {
        $array = [
            'chat_id' => $chatId
        ];

        if($member_limit) {
            $array['member_limit'] = $member_limit;
        }

        $data = $this->send_request('exportChatInviteLink', $array);

        if(!isset($data['ok']) || !$data['ok']) return ['success' => false, 'data' => $data];

        return [
            'success' => true,
            'url' => $data['result']
        ];
    }

    public function banChatMember($chatId, $userId, $until_date = 0, $retry = 0) {
        $array = [
            'chat_id' => $chatId,
            'user_id' => $userId
        ];

        if($until_date) {
            $array['until_date'] = $until_date;
        }

        $data = $this->send_request('banChatMember', $array);

        if(!isset($data['ok']) || !$data['ok']) {
            if($retry < 5) {
                return $this->banChatMember($chatId, $userId, $until_date, ($retry + 1));
            }

            return ['success' => false, 'data' => $data];
        }

        return [
            'success' => true
        ];
    }

    public function unbanChatMember($chatId, $userId, $retry = 0) {
        $data = $this->send_request('unbanChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
            'only_if_banned' => true
        ]);

        if(!isset($data['ok']) || !$data['ok']) {
            if($retry < 5) {
                return $this->unbanChatMember($chatId, $userId, ($retry + 1));
            }

            return $data;
        }

        return [
            'success' => true
        ];
    }

    public function kickChatMember($chatId, $userId, $retry = 0) {
        date_default_timezone_set('UTC'); // Для работы в UTC

        $data = $this->banChatMember($chatId, $userId, (time() + 60));

        if(!$data['success']) {
            return ['success' => false, 'data' => $data];
        }
        //Если успешно заблокирован - разблокируем
        return $this->unbanChatMember($chatId, $userId);
    }

    public function getChat($chatId, $retry = 0) {
        $data = $this->send_request('getChat', [
            'chat_id' => $chatId
        ]);

        if(!isset($data['ok']) || !$data['ok']) {
            if($retry < 5) {
                return $this->getChat($chatId, ($retry+1));
            }

            return ['success' => false, 'data' => $data];
        }

        return ['success' => true, 'data' => $data];
    }

    public function checkMemberHasChat($chatId, $userId, $retry = 0) {
        $data = $this->send_request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);

        if(!isset($data['ok']) || !$data['ok'] && (isset($data['error_code']) && $data['error_code'] != 400)) {
            if($retry < 5) {
                return $this->checkMemberHasChat($chatId, $userId, ($retry+1));
            }

            return ['success' => false, 'data' => $data];
        }

        if(isset($data['result']['status']) && in_array($data['result']['status'], ['creator', 'administrator', 'member', 'restricted'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function getTelegramIp() {
        $data = $this->send_request('getWebhookInfo');

        if(!isset($data['ok']) || !$data['ok'] || !isset($data['result']['ip_address'])) {
            if($retry < 5) {
                return $this->getTelegramIp();
            }

            return ['success' => false, 'data' => $data];
        }

        return ['success' => true, 'ip' => $data['result']['ip_address']];
    }

    public function getChatInfo($chatId, $retry = 0) {
        $array = [
            'chat_id' => $chatId
        ];

        $data = $this->send_request('getChat', $array);

        if(!isset($data['ok']) || !$data['ok']) {
            if($retry < 3) {
                return $this->getChatInfo($chatId, ($retry + 1));
            }

            return ['success' => false, 'data' => $data];
        }

        return [
            'success' => true,
            'data' => $data['result']
        ];
    }

    public function getFileUrl($fileId) {
        $filePath = $this->getFilePath($fileId);
        if (!$filePath) return null;
        return "https://api.telegram.org/file/bot{$this->apiKey}/{$filePath}";
    }

    public function getFilePath($fileId) {
        $file = $this->send_request('getFile', ['file_id' => $fileId]);
        if (!isset($file['ok']) || !$file['ok']) {
            return null;
        }
        return $file['result']['file_path'] ?? null;
    }

    public function getFile($filePath) {
        $url = "https://api.telegram.org/file/bot{$this->apiKey}/{$filePath}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function detectMime($filePath) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }

    public function getFileType($file) {
        $mime = $file->getMimeType();
        if (str_contains($mime, 'image')) return 'photo';
        if (str_contains($mime, 'video')) return 'video';
        return 'document';
    }

    public function sendMediaGroup($chatId, $media, $files = []) {
        $params = ['chat_id' => $chatId, 'media' => json_encode($media)];
        
        foreach ($files as $key => $file) {
            if ($key !== 'media') {
                $params[$key] = $file;
            }
        }
        
        return $this->send_request('sendMediaGroup', $params);
    }

    public function sendPhoto($chatId, $photo, $caption = null) {
        $params = ['chat_id' => $chatId, 'photo' => $photo];
        if ($caption) {
            $params['caption'] = $caption;
            $params['parse_mode'] = 'HTML';
        }
        return $this->send_request('sendPhoto', $params);
    }

    public function sendVideo($chatId, $video, $caption = null) {
        $params = ['chat_id' => $chatId, 'video' => $video];
        if ($caption) {
            $params['caption'] = $caption;
            $params['parse_mode'] = 'HTML';
        }
        return $this->send_request('sendVideo', $params);
    }

    public function sendDocument($chatId, $document, $caption = null) {
        $params = ['chat_id' => $chatId, 'document' => $document];
        if ($caption) {
            $params['caption'] = $caption;
            $params['parse_mode'] = 'HTML';
        }
        return $this->send_request('sendDocument', $params);
    }

    public function sendMessage($chatId, $text, $parseMode = 'HTML') {
        return $this->send_request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'link_preview_options' => json_encode(['is_disabled' => true])
        ]);
    }

    public function deleteMessage($chatId, $messageId) {
        return $this->send_request('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    public function getUserProfilePhotos($userId, $limit = 1) {
        return $this->send_request('getUserProfilePhotos', [
            'user_id' => $userId,
            'limit' => $limit
        ]);
    }

    public static function addEntitiesMessage($text, $entities = null) {
        if (!$entities) {
            return $text;
        }

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

        return $result;
    }

}
