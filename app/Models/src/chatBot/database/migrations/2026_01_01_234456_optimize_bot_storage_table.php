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
        Schema::table('bot_storages', function (Blueprint $table) {
            $table->string('type', 20)->change(); 
            $table->string('hash', 64)->change(); 
            $table->index(['type', 'hash'], 'bot_storage_type_hash_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_storages', function (Blueprint $table) {
            $table->dropIndex('bot_storage_type_hash_index');
            $table->string('type', 255)->change();
            $table->string('hash', 255)->change();
        });
    }
};