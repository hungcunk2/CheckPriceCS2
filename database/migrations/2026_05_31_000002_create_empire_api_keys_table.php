<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empire_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->text('api_key');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        if (! \App\Models\EmpireApiKey::query()->exists()) {
            $this->importFromEnv();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empire_api_keys');
    }

    private function importFromEnv(): void
    {
        $keys = [];
        $primary = trim((string) env('CSGOEMPIRE_API_KEY', ''));
        if ($primary !== '') {
            $keys[] = [
                'label' => env('CSGOEMPIRE_ACCOUNT_LABEL', 'empire-1'),
                'api_key' => $primary,
                'sort_order' => 1,
            ];
        }

        foreach (range(2, 6) as $index) {
            $key = trim((string) env('CSGOEMPIRE_API_KEY_'.$index, ''));
            if ($key === '') {
                continue;
            }
            $keys[] = [
                'label' => env('CSGOEMPIRE_ACCOUNT_LABEL_'.$index, 'empire-'.$index),
                'api_key' => $key,
                'sort_order' => $index,
            ];
        }

        foreach ($keys as $row) {
            \App\Models\EmpireApiKey::query()->create([
                'label' => $row['label'],
                'api_key' => $row['api_key'],
                'is_active' => true,
                'sort_order' => $row['sort_order'],
            ]);
        }
    }
};
