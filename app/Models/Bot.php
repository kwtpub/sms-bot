<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    protected $guarded = [];

    public function users() {
        return $this->hasMany(User::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function getName() {
        if(!is_null($this->first_name)) {
            return trim($this->first_name);
        }

        return 'SMS BOT';
    }
}
