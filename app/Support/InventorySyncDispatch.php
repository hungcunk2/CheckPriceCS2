<?php

namespace App\Support;

use App\Jobs\SyncInventoryJob;

final class InventorySyncDispatch
{
    public static function shouldQueue(): bool
    {
        if (! filter_var(config('cs2price.sync_use_queue', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return (string) config('queue.default') !== 'sync';
    }

    /**
     * @param  list<int>  $inventoryIds
     */
    public static function dispatch(array $inventoryIds, bool $isManualRefresh, ?int $memberUserId = null): void
    {
        SyncInventoryJob::dispatch($inventoryIds, $isManualRefresh, $memberUserId);

        if ($isManualRefresh) {
            foreach ($inventoryIds as $inventoryId) {
                InventorySyncStatus::markQueued((int) $inventoryId);
            }
        }
    }
}
