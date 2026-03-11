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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('tg_id')->index();
            $table->string('tg_username')->default('');
            $table->bigInteger('referal_id')->default(0);
            $table->string('language')->default('ru');
            $table->foreignId('first_bot_id')->constrained('bots');
            $table->foreignId('bot_id')->constrained();
            $table->json('bots_ids');
            $table->integer('send_start')->default(0);
            $table->integer('is_admin')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
