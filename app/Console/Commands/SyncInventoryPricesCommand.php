<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InventorySyncService;
use App\Services\TrackedInventoryStore;
use App\Support\Buff163AccountPool;
use App\Support\Cs2CapApiPool;
use App\Support\InventorySyncDispatch;
use Illuminate\Console\Command;
class SyncInventoryPricesCommand extends Command
{
    protected $signature = 'cs2price:sync-prices';

    protected $description = 'Quét kho Steam + giá Buff theo chu kỳ gói (Pro 8h, Plus 4h, Max 2h, Shop/Admin 1h)';

    public function handle(
        TrackedInventoryStore $store,
        InventorySyncService $sync,
    ): int {
        if (! config('cs2price.price_auto_sync_enabled', true)) {
            $this->warn('Tự động lấy giá đang tắt (BUFF_PRICE_AUTO_SYNC=false).');

            return self::SUCCESS;
        }

        $hasBuff = Buff163AccountPool::isConfigured();
        $hasCs2Cap = Cs2CapApiPool::isConfigured()
            && filter_var(config('cs2price.cs2cap_enabled', false), FILTER_VALIDATE_BOOL);

        if (! $hasBuff && ! $hasCs2Cap) {
            $this->error('Chưa cấu hình nguồn giá — cần BUFF163_SESSION hoặc CS2Cap (CS2CAP_ENABLED + API key).');

            return self::FAILURE;
        }

        $due = $store->dueForAutoSync();
        if ($due->isEmpty()) {
            $this->info('Không có kho nào đến hạn đồng bộ.');

            return self::SUCCESS;
        }

        $usersById = User::query()
            ->whereIn('id', $due->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        $groups = $sync->groupDueRows($due, $usersById);
        $queued = 0;
        $ok = 0;
        $failed = 0;

        foreach ($groups as $groupRows) {
            $ids = array_values(array_map(fn (object $row) => (int) $row->id, $groupRows));
            $label = $groupRows[0]->label ?? $groupRows[0]->url ?? ('#'.($groupRows[0]->id ?? '?'));
            $this->line('→ '.$label.(count($ids) > 1 ? ' (+'.(count($ids) - 1).' kho cùng Steam)' : ''));

            if (InventorySyncDispatch::shouldQueue()) {
                InventorySyncDispatch::dispatch($ids, isManualRefresh: false);
                $queued++;
                $this->info('  Đã xếp hàng job ('.count($ids).' kho)');

                continue;
            }

            $result = $sync->syncByInventoryIds($ids, isManualRefresh: false);
            $ok += $result['ok'];
            $failed += $result['failed'];
            foreach ($result['messages'] as $message) {
                if ($result['failed'] > 0) {
                    $this->warn('  '.$message);
                } else {
                    $this->info('  '.$message);
                }
            }
        }

        if (InventorySyncDispatch::shouldQueue()) {
            $this->info("Đã xếp hàng {$queued} job cho ".$due->count().' kho due.');

            return self::SUCCESS;
        }

        $this->info("Xong: {$ok} thành công, {$failed} lỗi.");

        return $failed > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
