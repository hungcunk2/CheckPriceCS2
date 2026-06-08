<?php

namespace App\Jobs;

use App\Services\InventorySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncInventoryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    /**
     * @param  list<int>  $inventoryIds
     */
    public function __construct(
        public array $inventoryIds,
        public bool $isManualRefresh = false,
        public ?int $memberUserId = null,
    ) {}

    public function handle(InventorySyncService $sync): void
    {
        $result = $sync->syncByInventoryIds(
            $this->inventoryIds,
            $this->isManualRefresh,
            $this->memberUserId,
            trackStatus: $this->isManualRefresh,
        );

        if ($result['failed'] > 0) {
            Log::warning('sync-inventory-job', [
                'inventory_ids' => $this->inventoryIds,
                'messages' => $result['messages'],
            ]);
        }
    }
}
