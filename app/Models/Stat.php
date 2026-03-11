<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Stat extends Model
{
    use HasFactory;
    protected $table = 'statistics_bots';
    protected $guarded = [];

    public static function get($bot_id) {
        $stat = self::where('bot_id', $bot_id)->whereDate('created_at', Carbon::today()->toDateString())->first();

        if(is_null($stat)) {
            $stat = self::create([
                'bot_id' => $bot_id,
                'count_new_users' => 0
            ]);
        }

        return $stat;
    }

    public static function addUser($bot, $meta) { //Добавление нового пользователя
        $stat = self::get($bot->id);

        $stat->count_new_users++;
        $stat->save();

        $bot->count_new_users++;
        $bot->save();
    }

    public static function addUserLost($bot, $meta) { //Добавление пользователя который уже был в бд
        $stat = self::get($bot->id);

        $stat->count_lost_users++;
        $stat->save();

        $bot->count_lost_users++;
        $bot->save();
    }
}
