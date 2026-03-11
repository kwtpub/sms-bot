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
        Schema::disableForeignKeyConstraints();

        Schema::create('bots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->text('bot_id')->nullable();
            $table->text('bot_name')->nullable();
            $table->text('first_name')->nullable();
            $table->text('secret')->nullable();
            $table->text('api_key');
            $table->integer('enable')->default(0);
            $table->integer('send_as_main')->default(0)->index();
            $table->integer('success')->default(0);
            $table->integer('deleted')->default(0);
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
        Schema::dropIfExists('bots');
    }
};
