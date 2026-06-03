<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 16);
            $table->unsignedTinyInteger('months');
            $table->unsignedInteger('amount_vnd');
            $table->string('reference', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->text('member_note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_orders');
    }
};
