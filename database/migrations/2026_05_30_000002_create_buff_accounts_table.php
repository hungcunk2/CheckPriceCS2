<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buff_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->text('session');
            $table->text('csrf_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $this->importFromEnv();
    }

    public function down(): void
    {
        Schema::dropIfExists('buff_accounts');
    }

    private function importFromEnv(): void
    {
        $accounts = [];

        $primary = trim((string) env('BUFF163_SESSION', ''));
        if ($primary !== '') {
            $accounts[] = [
                'label' => env('BUFF163_ACCOUNT_LABEL', 'acc-1'),
                'session' => $primary,
                'csrf_token' => env('BUFF163_CSRF_TOKEN'),
                'sort_order' => 1,
            ];
        }

        foreach (range(2, 10) as $index) {
            $session = trim((string) env('BUFF163_SESSION_'.$index, ''));
            if ($session === '') {
                continue;
            }

            $accounts[] = [
                'label' => env('BUFF163_ACCOUNT_LABEL_'.$index, 'acc-'.$index),
                'session' => $session,
                'csrf_token' => env('BUFF163_CSRF_TOKEN_'.$index),
                'sort_order' => $index,
            ];
        }

        foreach ($accounts as $account) {
            \App\Models\BuffAccount::query()->create([
                'label' => $account['label'],
                'session' => $account['session'],
                'csrf_token' => filled($account['csrf_token'] ?? null) ? $account['csrf_token'] : null,
                'is_active' => true,
                'sort_order' => $account['sort_order'],
            ]);
        }
    }
};
