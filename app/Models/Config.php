<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Cache;

class Config extends Model
{
    use HasFactory;
    protected $guarded = [];

    public static function config() {
        return Cache::remember('config_1', 10 * 60, function() {
            // Попытка найти конфигурацию с id = 1
            $config = self::find(1);

            if (is_null($config)) { // Если конфига не существует
                // Создаём новый конфиг
                $config = self::create([
                    'id' => 1
                ]);
            }

            return $config;
        });
    }
}
