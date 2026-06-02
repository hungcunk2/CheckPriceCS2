<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_catalog_images', function (Blueprint $table) {
            $table->id();
            $table->string('market_hash_name', 500)->unique();
            $table->string('image_url', 2000)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index(['fetched_at'], 'item_catalog_images_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_catalog_images');
    }
};

