<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Config;
use App\Models\Stat;
use App\Models\HistoryBalance;

use App\Models\src\chatBot\Telegram;
use App\Models\src\chatBot\Models\waitMessage;

use Cache;
use Carbon\Carbon;

class User extends Model
{
    protected $guarded = [];

    public static function getOrCreate($tgId, $tg_username, $bot, $referalTGId = 0, $meta = '', $is_vk = 0) {
        $user = self::where('tg_id', $tgId)->where('is_vk', $is_vk)->first();
        if(!is_null($user)) {
            if($user->bot_id != $bot->id) { //Обновление последнего бота
                $bots_ids = json_decode($user->bots_ids, true);
                if(!in_array($bot->id, $bots_ids)) { //Пользователь уже был, но в боте новый
                    $bots_ids[] = $bot->id;
                    $user->bots_ids = json_encode($bots_ids);
                    Stat::addUserLost($bot, $meta);
                }

                $user->save();
            }

            if($user->tg_username != $tg_username) { //Если обновилось имя пользователь в ТГ
                $user->tg_username = $tg_username;
                $user->save();
            }

            return ['created' => 0, 'user' => $user];
        }

        $user = self::create([
            'tg_id' => $tgId,
            'is_vk' => $is_vk,
            'tg_username' => $tg_username,
            'first_bot_id' => $bot->id,
            'bot_id' => $bot->id,
            'referal_id' => self::getRefUser($referalTGId, $bot),
            'bots_ids' => json_encode([$bot->id])
        ]);

        Stat::addUser($bot, $meta);

        return ['created' => 1, 'user' => $user];
    }

    public function historyBalance() {
        return $this->hasMany(HistoryBalance::class);
    }

    private static function getRefUser($tg_id, $bot) {
        if(!empty($tg_id) && $bot->referal_system) { //Если в /start передан реферал
            $refUser = self::where('tg_id', $tg_id)->first();
        }

        if((!isset($refUser) || is_null($refUser)) && !empty($bot->user_id) && !is_null($bot->user_id)) { //Если есть владелец бота - он реферал
            $refUser = self::find($bot->user_id);
        }

        if(isset($refUser)) {
            $refUser->count_ref++;
            $refUser->save();

            return $refUser->id;
        }

        return 0;
    }

    public function bot() {
        return $this->belongsTo(Bot::class);
    }

    public static function addAdminsWaitSendMessage($msg, $buttons = [], $photo = '', $botId = null) { //Сообщение всем админам
        $admins = self::where('is_admin', 1)->get();

        foreach($admins as $admin) {
            $admin->addWaitSendMessage($msg, $buttons, $photo, botId: $botId);
        }
    }

    public function addWaitSendMessage($msg, $buttons = [], $photos = [], $send_from = 0, $ifs = [], $tgParams = [], $botId = null) { //Добавление в отложеные сообщения
        if(!empty($photos) && !is_array($photos)) {
            $photos = [$photos];
        }

        $array = [
            'msg' => $msg,
            'buttons' => json_encode($buttons),
            'user_id' => $this->id,
            'bot_id' => $botId
        ];
        if(!empty($photos)) {
            $array['photo'] = json_encode($photos);
        }

        if(!empty($send_from)) {
            if(is_integer($send_from)) {
                $array['send_from'] = Carbon::parse($send_from);
            }
            else {
                $array['send_from'] = $send_from;
            }
        }
        if(!empty($ifs)) {
            $array['ifs'] = json_encode($ifs);
        }

        if(!empty($tgParams)) {
            $array['tg_params'] = json_encode($tgParams);
        }

        waitMessage::create($array);
    }

    public function getTgName() {
        return Cache::remember("tg_name_{$this->tg_id}", 30 * 60, function () {
            $tg = new Telegram($this->bot->api_key);

            $data = $tg->getChatInfo($this->tg_id);
            if ($data['success'] && !empty($data['data']['first_name'])) {
                return $data['data']['first_name'];
            }

            if (!empty($this->tg_username)) {
                return $this->tg_username;
            }

            return false;
        });
    }
}
