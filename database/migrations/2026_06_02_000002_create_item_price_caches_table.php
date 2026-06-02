<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_price_caches', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20); // buff | empire
            $table->string('market_hash_name', 500);
            $table->string('phase', 50)->nullable();
            $table->string('currency', 10)->nullable(); // CNY | USD | etc
            $table->decimal('price', 16, 4)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'market_hash_name', 'phase'], 'item_price_caches_unique_key');
            $table->index(['source', 'fetched_at'], 'item_price_caches_source_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_price_caches');
    }
};

