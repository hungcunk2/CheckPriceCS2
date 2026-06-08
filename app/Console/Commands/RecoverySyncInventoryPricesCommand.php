<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InventorySyncService;
use App\Services\TrackedInventoryStore;
use App\Support\Buff163AccountPool;
use App\Support\Cs2CapApiPool;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RecoverySyncInventoryPricesCommand extends Command
{
    protected $signature = 'cs2price:recovery-sync
                            {--delay=180 : Nghỉ N giây giữa mỗi nhóm kho (mặc định 180 = 3 phút)}
                            {--id= : Chỉ sync một kho theo ID}
                            {--force : Bỏ qua xác nhận trước khi chạy}';

    protected $description = 'Khôi phục giá: quét lại TẤT CẢ kho (fresh), nghỉ giữa mỗi nhóm — dùng khi mất giá hàng loạt';

    public function handle(
        TrackedInventoryStore $store,
        InventorySyncService $sync,
    ): int {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');

        $hasBuff = Buff163AccountPool::isConfigured();
        $hasCs2Cap = Cs2CapApiPool::isConfigured()
            && filter_var(config('cs2price.cs2cap_enabled', false), FILTER_VALIDATE_BOOL);

        if (! $hasBuff && ! $hasCs2Cap) {
            $this->error('Chưa cấu hình nguồn giá — cần BUFF163_SESSION hoặc CS2Cap (CS2CAP_ENABLED + API key).');

            return self::FAILURE;
        }

        $delaySeconds = max(0, (int) $this->option('delay'));
        $onlyId = $this->option('id');
        $onlyId = is_numeric($onlyId) ? (int) $onlyId : null;

        $rows = $this->resolveRows($store, $onlyId);
        if ($rows->isEmpty()) {
            $this->warn($onlyId !== null
                ? "Không tìm thấy kho #{$onlyId}."
                : 'Không có kho nào trong hệ thống.');

            return self::FAILURE;
        }

        $usersById = User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        $groups = $sync->groupDueRows($rows, $usersById);
        $groupCount = count($groups);
        $rowCount = $rows->count();

        $this->info("Recovery sync: {$rowCount} kho → {$groupCount} nhóm (cùng Steam gộp chung).");
        $this->line("Nghỉ {$delaySeconds}s giữa mỗi nhóm · chạy đồng bộ (không queue) · fresh kho + giá.");

        if (! $this->option('force') && ! $this->option('no-interaction')) {
            if (! $this->confirm('Tiếp tục?', false)) {
                $this->comment('Đã hủy.');

                return self::SUCCESS;
            }
        }

        $ok = 0;
        $failed = 0;

        foreach ($groups as $index => $groupRows) {
            $position = $index + 1;
            $ids = array_values(array_map(fn (object $row) => (int) $row->id, $groupRows));
            $label = $groupRows[0]->label ?? $groupRows[0]->url ?? ('#'.($groupRows[0]->id ?? '?'));
            $suffix = count($ids) > 1 ? ' (+'.(count($ids) - 1).' kho cùng Steam)' : '';

            $this->newLine();
            $this->line("[{$position}/{$groupCount}] → {$label}{$suffix}");

            $result = $sync->syncByInventoryIds($ids, isManualRefresh: true);
            $ok += $result['ok'];
            $failed += $result['failed'];

            foreach ($result['messages'] as $message) {
                if ($result['failed'] > 0) {
                    $this->warn('  '.$message);
                } else {
                    $this->info('  '.$message);
                }
            }

            if ($position < $groupCount && $delaySeconds > 0) {
                $this->pause($delaySeconds, $position, $groupCount);
            }
        }

        $this->newLine();
        $this->info("Recovery xong: {$ok} thành công, {$failed} lỗi ({$rowCount} kho, {$groupCount} nhóm).");

        return $failed > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, object>
     */
    private function resolveRows(TrackedInventoryStore $store, ?int $onlyId): Collection
    {
        if ($onlyId !== null) {
            $row = $store->find($onlyId);

            return $row ? collect([$row]) : collect();
        }

        return $store->all();
    }

    private function pause(int $seconds, int $current, int $total): void
    {
        $this->comment("Nghỉ {$seconds}s trước nhóm tiếp theo ({$current}/{$total})…");

        $remaining = $seconds;
        while ($remaining > 0) {
            $chunk = min(30, $remaining);
            sleep($chunk);
            $remaining -= $chunk;

            if ($remaining > 0) {
                $this->line("  … còn {$remaining}s");
            }
        }
    }
}
