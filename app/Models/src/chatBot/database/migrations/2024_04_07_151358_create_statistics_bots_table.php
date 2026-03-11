<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statistics_bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained();
            $table->integer('count_new_users')->default(0);
            $table->integer('count_lost_users')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics_bots');
    }
};
