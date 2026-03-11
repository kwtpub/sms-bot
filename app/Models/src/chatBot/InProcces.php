<?php

namespace App\Models\src\chatBot;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Cache;

class InProcces extends Model
{
    public static function check($userId) {
        $timeCache = 'in.proccess.u' . $userId;

        if(Cache::has($timeCache) && ((time() - Cache::get($timeCache)) < 10)) {
            return true;
        }

        Cache::put($timeCache, time(), (6*10));
        return false;
    }

    public static function deletee($userId) {
        $timeCache = 'in.proccess.u' . $userId;

        Cache::forget($timeCache);

        return true;
    }
}
