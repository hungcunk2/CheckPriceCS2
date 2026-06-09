<?php

namespace App\Console\Commands;

use App\Models\PriceHistoryPoint;
use App\Models\TrackedInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportJsonToDatabaseCommand extends Command
{
    protected $signature = 'cs2price:import-json
                            {--force : Xóa dữ liệu trong MySQL trước khi import}';

    protected $description = 'Import kho + lịch sử giá từ file JSON (storage/app) sang MySQL';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('Không kết nối được MySQL. Kiểm tra DB_* trong .env: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $this->laravel->runningUnitTests() && ! $this->tablesExist()) {
            $this->error('Chưa có bảng. Chạy: php artisan migrate');
            $this->line('Hoặc import database/sql/schema.sql trong phpMyAdmin.');

            return self::FAILURE;
        }

        if ($this->option('force')) {
            PriceHistoryPoint::query()->delete();
            TrackedInventory::query()->delete();
            $this->warn('Đã xóa dữ liệu MySQL cũ.');
        }

        $invCount = $this->importInventories();
        $histCount = $this->importPriceHistory();

        $this->info("Import xong: {$invCount} kho, {$histCount} điểm lịch sử giá.");

        return self::SUCCESS;
    }

    private function tablesExist(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('tracked_inventories')
            && \Illuminate\Support\Facades\Schema::hasTable('price_history_points');
    }

    private function importInventories(): int
    {
        $path = 'tracked_inventories.json';

        if (! Storage::disk('local')->exists($path)) {
            $this->warn('Không có storage/app/tracked_inventories.json');

            return 0;
        }

        $rows = json_decode(Storage::disk('local')->get($path), true);

        if (! is_array($rows)) {
            $this->warn('File kho JSON không hợp lệ.');

            return 0;
        }

        $count = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['url'])) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $payload = [
                'label' => $row['label'] ?? null,
                'url' => $row['url'],
                'steam_id' => $row['steam_id'] ?? null,
                'steam_persona_name' => $row['steam_persona_name'] ?? null,
                'steam_avatar_url' => $row['steam_avatar_url'] ?? null,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'last_checked_at' => ! empty($row['last_checked_at'])
                    ? \Carbon\Carbon::parse($row['last_checked_at'])
                    : null,
                'last_total_cny' => $row['last_total_cny'] ?? null,
                'last_total_vnd' => $row['last_total_vnd'] ?? null,
                'item_count' => (int) ($row['item_count'] ?? 0),
                'priced_count' => (int) ($row['priced_count'] ?? 0),
                'failed_count' => (int) ($row['failed_count'] ?? 0),
                'last_snapshot' => $row['last_snapshot'] ?? null,
                'created_at' => ! empty($row['created_at'])
                    ? \Carbon\Carbon::parse($row['created_at'])
                    : now(),
                'updated_at' => ! empty($row['updated_at'])
                    ? \Carbon\Carbon::parse($row['updated_at'])
                    : now(),
            ];

            if ($id > 0) {
                TrackedInventory::query()->updateOrCreate(['id' => $id], $payload);
            } else {
                TrackedInventory::query()->create($payload);
            }

            $count++;
        }

        $maxId = TrackedInventory::query()->max('id');
        if ($maxId) {
            DB::statement('ALTER TABLE tracked_inventories AUTO_INCREMENT = '.((int) $maxId + 1));
        }

        return $count;
    }

    private function importPriceHistory(): int
    {
        $dir = storage_path('app/price_history/items');

        if (! is_dir($dir)) {
            $this->warn('Không có thư mục price_history/items');

            return 0;
        }

        $count = 0;
        $files = glob($dir.'/*.json') ?: [];

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);

            if (! is_array($data)) {
                continue;
            }

            $name = (string) ($data['market_hash_name'] ?? '');
            $points = $data['points'] ?? [];

            if ($name === '' || ! is_array($points)) {
                continue;
            }

            $hash = md5($name);

            foreach ($points as $point) {
                if (! is_array($point)) {
                    continue;
                }

                $at = \Carbon\Carbon::parse($point['recorded_at'] ?? now());
                $price = round((float) ($point['price_cny'] ?? 0), 2);
                $sellNum = isset($point['sell_num']) ? (int) $point['sell_num'] : null;

                PriceHistoryPoint::query()->firstOrCreate(
                    [
                        'item_hash' => $hash,
                        'recorded_at' => $at,
                        'price_cny' => $price,
                        'sell_num' => $sellNum,
                    ],
                    [
                        'market_hash_name' => $name,
                    ]
                );

                $count++;
            }
        }

        return $count;
    }
}
