<?php

namespace App\Console\Commands;

use App\Services\PriceHistoryService;
use App\Services\TrackedInventoryStore;
use App\Support\InventorySnapshotReader;
use Illuminate\Console\Command;

class BackfillPriceHistoryCommand extends Command
{
    protected $signature = 'cs2price:backfill-price-history';

    protected $description = 'Ghi một điểm giá từ snapshot hiện tại vào lịch sử (theo last_checked_at)';

    public function handle(TrackedInventoryStore $store, PriceHistoryService $priceHistory): int
    {
        $count = 0;

        foreach ($store->all() as $row) {
            $items = InventorySnapshotReader::itemsFromInventory($row);
            $at = $row->last_checked_at ?? now()->toIso8601String();
            $priceHistory->recordFromItems($items, $at);
            $count += count($items);
            $this->line('Kho #'.$row->id.': '.count($items).' item');
        }

        $this->info("Đã ghi {$count} item vào lịch sử. Các mốc hôm qua / 7 ngày cần thêm sau vài lần sync.");

        return self::SUCCESS;
    }
}
