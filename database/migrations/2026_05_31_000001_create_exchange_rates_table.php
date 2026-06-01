<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('cny_to_vnd', 12, 4);
            $table->decimal('vnd_to_usd', 12, 4);
            $table->decimal('empire_coin_to_vnd', 14, 4);
            $table->decimal('empire_coin_to_usd', 10, 6)->nullable();
            $table->timestamps();
        });

        $vndPerUsd = (float) env('VND_TO_USD', 26700);
        $coinToUsd = (float) env('EMPIRE_COIN_TO_USD', 0.6143);
        $coinToVnd = (float) env('EMPIRE_COIN_TO_VND', $coinToUsd * $vndPerUsd);

        \App\Models\ExchangeRate::query()->create([
            'cny_to_vnd' => (float) env('CNY_TO_VND', 3750),
            'vnd_to_usd' => $vndPerUsd,
            'empire_coin_to_vnd' => $coinToVnd,
            'empire_coin_to_usd' => $coinToUsd,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
