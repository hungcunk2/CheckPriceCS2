<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class InventorySyncStatus
{
    private const TTL_SECONDS = 900;

    public static function markQueued(int $inventoryId): void
    {
        Cache::put(self::key($inventoryId), [
            'status' => 'queued',
            'message' => 'Đã xếp hàng — đang chờ worker…',
        ], self::TTL_SECONDS);
    }

    public static function markRunning(int $inventoryId): void
    {
        Cache::put(self::key($inventoryId), [
            'status' => 'running',
            'message' => 'Đang đồng bộ kho và giá…',
        ], self::TTL_SECONDS);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function markDone(int $inventoryId, array $payload): void
    {
        Cache::put(self::key($inventoryId), array_merge($payload, [
            'status' => 'done',
            'ok' => true,
        ]), 300);
    }

    public static function markFailed(int $inventoryId, string $message): void
    {
        Cache::put(self::key($inventoryId), [
            'status' => 'failed',
            'ok' => false,
            'message' => $message,
        ], 300);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(int $inventoryId): ?array
    {
        $value = Cache::get(self::key($inventoryId));

        return is_array($value) ? $value : null;
    }

    private static function key(int $inventoryId): string
    {
        return 'inventory_sync_status:'.$inventoryId;
    }
}
