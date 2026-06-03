<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empire_proxy_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('rotation_key', 128)->nullable();
            $table->string('nhamang', 32)->default('Random');
            $table->string('tinhthanh', 8)->default('0');
            $table->string('whitelist_ip', 45)->nullable();
            $table->boolean('use_socks5')->default(false);
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empire_proxy_settings');
    }
};
