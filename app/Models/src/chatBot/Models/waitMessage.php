<?php

namespace App\Models\src\chatBot\Models;

use Illuminate\Database\Eloquent\Model;

use App\Models\src\chatBot\Telegram;
use App\Models\src\chatBotVK\VK;

use App\Models\src\chatBot\KeyBoard;
use App\Models\src\chatBotVK\KeyBoard as VKKeyBoard;

use App\Models\User;
use App\Models\Bot;
use App\Models\Logs;

use Carbon\Carbon;

class waitMessage extends Model
{
    protected $guarded = [];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function bot() {
        return $this->belongsTo(Bot::class);
    }

    public static function correctMsgText($messageText, $user) { //Используется для кориктировки сообщения, мне например при рассылке нужно было менять ссылку в зависимости от бота пользователя.
        return $messageText;
    }

    public static function handler() {
        $messages = self::where('send_from', '<=', Carbon::now())->orderBy('priorit', 'desc')->take(50)->get();

        if($messages->isEmpty()) {
            return ['success' => true, 'msg' => 'empty'];
        }

        $countSuccess = 0;
        $countError = 0;

        $chs = [];
        $arrays = [];

        foreach($messages as $message) {
            if(!is_null($message->ifs) && !static::checkIfs($message)) { //Если уже не актуально..
                $message->delete();
                continue;
            }

            if($message->user->is_vk) {
                $vk = new VK($message->user->bot->api_key);

                $array = [
                    'user_id' => $message->user->tg_id,
                    'random_id' => 0,
                    'message' => static::correctMsgText($message->msg, $message->user),
                    'v' => '5.199'
                ];

                if(!is_null($message->tg_params)) {
                    $vkParams = json_decode($message->tg_params, true);

                    $array = array_merge($array, $vkParams);
                }

                if(!empty($message->buttons)) { //Если отправляем клавиатуру.
                    $keyBoard = new VKKeyBoard();
                    $keyBoard->buttons = json_decode($message->buttons, true);

                    $array['keyboard'] = $keyBoard->generate(1);
                }

                $arrays[] = $array;

                $chs[] = $vk->send_request('messages.send', $array, 1); //Добавим в массив

                $message->delete(); //Удалим сообщение
            }
            else {
                if(!is_null($message->bot_id)) {
                    $tg = new Telegram($message->bot->api_key);
                }
                else {
                    $tg = new Telegram($message->user->bot->api_key);
                }

                $array = [
                    'chat_id' => $message->user->tg_id,
                    'parse_mode' => 'HTML'
                ];

                if(!is_null($message->tg_params)) {
                    $tgParams = json_decode($message->tg_params, true);

                    $array = array_merge($array, $tgParams);
                }

                $message->msg = static::correctMsgText($message->msg, $message->user);

                if(!is_null($message->photo)) { //Если сообщение с фото
                    $photos = json_decode($message->photo, true);

                    if(count($photos) > 1) { //Если несколько фото:
                        $newPhotos = [];
                        foreach($photos as $photo) {
                            $newPhotos[] = [
                                'type' => 'photo',
                                'media' => $photo
                            ];
                        }
                        $newPhotos[0]['caption'] = $message->msg;
                        $newPhotos[0]['parse_mode'] = 'HTML';

                        $method = 'sendMediaGroup';
                        $array['caption'] = $message->msg;
                        $array['media'] = json_encode($newPhotos);
                    }
                    else {
                        $method = 'sendPhoto';

                        $array['caption'] = $message->msg;
                        $array['photo'] = $photos[0];
                    }
                }
                else { //Если сообщение без фото
                    $method = 'sendMessage';

                    $array['text'] = $message->msg;
                }

                if(!empty($message->buttons)) { //Если отправляем клавиатуру.
                    $keyBoard = new KeyBoard();
                    $keyBoard->buttons = json_decode($message->buttons, true);

                    $array['reply_markup'] = $keyBoard->generate(1);
                }

                $arrays[] = $array;

                $chs[] = $tg->send_request($method, $array, 1); //Добавим в массив

                $message->delete(); //Удалим сообщение
            }
        }

        $results = static::getMultiCurl($chs);

        foreach($results as $i => $result) {
            $data = json_decode($result, true);

            if(isset($data['ok']) && $data['ok']) $countSuccess++;
            else {
                if(isset($data['description']) && ($data['description'] == 'Forbidden: bot was blocked by the user' || $data['description'] == 'Forbidden: user is deactivated')) {

                }
                else {
                    Logs::log('Ошибка в ответе, метод TG - ', [$arrays[$i], $data]);
                    $countError++;
                }
            }
        }

        return ['success' => true, 'count_success' => $countSuccess, 'count_error' => $countError];
    }

    public static function getMultiCurl($array) {
        $mchs = curl_multi_init();
        foreach($array as $ch) {
            curl_multi_add_handle($mchs, $ch);
        }

        $running = null;
        do {
            curl_multi_exec($mchs, $running);
        } while($running > 0);

        $allResponse = [];
        foreach($array as $id => $ch) {
            $allResponse[$id] = curl_multi_getcontent($ch);

            curl_multi_remove_handle($mchs, $ch);
        }

        curl_multi_close($mchs);
        return $allResponse;
    }

    public static function checkIfs($message) { //Если надо переопредели в дочернем классе и используй его.
        return true;
    }
}
