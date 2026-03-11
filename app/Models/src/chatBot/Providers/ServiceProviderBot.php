<?php

namespace App\Models\src\chatBot\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Migrations\Migrator;

class ServiceProviderBot extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(base_path('app/Models/src/chatBot/database/migrations'));
    }

    public function register()
    {
        //
    }
}