<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("pays", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id");
            $table->decimal("sum", 15, 2);
            $table->string("pay_id");
            $table->string("method");
            $table
                ->enum("status", ["created", "success", "error"])
                ->default("created");
            $table->json("created_data")->nullable();
            $table->json("callback_data")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("pays");
    }
};
