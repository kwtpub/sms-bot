<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    protected $guarded = [];

    public static function log($message, $data = []) {
        self::create([
            'message' => $message,
            'data' => json_encode($data)
        ]);

        return true;
    }
}
