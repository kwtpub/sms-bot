<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('good')->nullable();
            $table->unsignedInteger('total_sites')->nullable();
            $table->float('rate')->nullable();
            $table->string('link_report')->nullable();
            $table->string('token_report')->nullable();
            $table->string('elapsed_time')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['good', 'total_sites', 'rate', 'link_report', 'token_report', 'elapsed_time']);
        });
    }
};
