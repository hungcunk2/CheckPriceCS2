<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('bank_bin', 6)->nullable();
            $table->string('bank_code', 16)->nullable();
            $table->string('bank_display_name', 120)->nullable();
            $table->string('account_number', 32)->nullable();
            $table->string('account_holder', 80)->nullable();
            $table->string('qr_template', 16)->default('compact');
            $table->string('vietqr_client_id', 64)->nullable();
            $table->string('vietqr_api_key', 128)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
