<?php

namespace App\Console\Commands;

use App\Services\InventoryPriceChecker;
use App\Services\TrackedInventoryStore;
use App\Support\InventoryResultPersister;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SyncInventoryPricesCommand extends Command
{
    protected $signature = 'cs2price:sync-prices';

    protected $description = 'Đồng bộ giá Buff cho các kho (ưu tiên chưa có giá / giá > 2h)';

    public function handle(
        TrackedInventoryStore $store,
        InventoryPriceChecker $checker,
        InventoryResultPersister $persister,
    ): int {
        if (! config('cs2price.price_auto_sync_enabled', true)) {
            $this->warn('Tự động lấy giá đang tắt (BUFF_PRICE_AUTO_SYNC=false).');

            return self::SUCCESS;
        }

        if (! filled(config('cs2price.buff_session'))) {
            $this->error('Thiếu BUFF163_SESSION — bỏ qua đồng bộ.');

            return self::FAILURE;
        }

        $seconds = (int) config('cs2price.check_max_execution_seconds', 600);
        if ($seconds > 0) {
            @set_time_limit($seconds);
            @ini_set('max_execution_time', (string) $seconds);
        }

        $inventories = $store->all();
        if ($inventories->isEmpty()) {
            $this->info('Không có kho nào để đồng bộ.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        foreach ($inventories as $row) {
            $label = $row->label ?? $row->url ?? ('#'.$row->id);
            $this->line("→ {$label}");

            try {
                $result = $checker->checkUrl($row->url, $row->label ?? null);
                $persister->persist($result, (int) $row->id, (bool) ($row->is_public ?? true));
                $ok++;
                $this->info("  OK — {$result['priced_count']}/{$result['item_count']} có giá");
            } catch (RuntimeException $e) {
                $failed++;
                $this->warn('  '.$e->getMessage());
                Log::warning('cs2price:sync-prices', ['inventory_id' => $row->id, 'error' => $e->getMessage()]);
            } catch (Throwable $e) {
                $failed++;
                $this->error('  '.$e->getMessage());
                Log::error('cs2price:sync-prices', ['inventory_id' => $row->id, 'error' => $e->getMessage()]);
            }
        }

        $this->info("Xong: {$ok} thành công, {$failed} lỗi.");

        return $failed > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
