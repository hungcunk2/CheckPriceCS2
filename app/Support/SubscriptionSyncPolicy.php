<?php

namespace App\Support;

final class SubscriptionSyncPolicy
{
    /** @var array<string, array{auto_sync_hours: int, manual_refresh_per_day: int|null}> */
    private const PLAN_LIMITS = [
        'pro' => ['auto_sync_hours' => 8, 'manual_refresh_per_day' => 10],
        'plus' => ['auto_sync_hours' => 4, 'manual_refresh_per_day' => 30],
        'max' => ['auto_sync_hours' => 2, 'manual_refresh_per_day' => 80],
        'shop' => ['auto_sync_hours' => 1, 'manual_refresh_per_day' => null],
    ];

    public static function autoSyncIntervalHours(?string $plan, bool $isAdminInventory = false): int
    {
        if ($isAdminInventory) {
            return self::PLAN_LIMITS['shop']['auto_sync_hours'];
        }

        $plan = self::normalizePlan($plan);

        return self::PLAN_LIMITS[$plan]['auto_sync_hours'];
    }

    /** null = không giới hạn (Shop / admin). */
    public static function manualRefreshDailyLimit(?string $plan, bool $isAdminContext = false): ?int
    {
        if ($isAdminContext) {
            return null;
        }

        $plan = self::normalizePlan($plan);

        return self::PLAN_LIMITS[$plan]['manual_refresh_per_day'];
    }

    /**
     * Shop + admin: mỗi lần sync — giá Buff/Empire mới (bỏ cache DB giá).
     * Không bao gồm kho Steam/CS2Cap (xem requiresFreshInventory).
     */
    public static function requiresFreshSync(?string $plan, bool $isAdminContext = false): bool
    {
        if ($isAdminContext) {
            return true;
        }

        return self::normalizePlan($plan) === 'shop';
    }

    /** @deprecated Use requiresFreshSync() */
    public static function requiresFreshPrices(?string $plan, bool $isAdminContext = false): bool
    {
        return self::requiresFreshSync($plan, $isAdminContext);
    }

    /**
     * Tải lại danh sách skin từ Steam/CS2Cap (bỏ cache kho).
     * Auto-sync: dùng cache kho (~4h) — giá vẫn có thể fresh qua requiresFreshSync().
     * Refresh thủ công (admin/member bấm nút): luôn tải kho mới.
     */
    public static function requiresFreshInventory(bool $isManualRefresh = false): bool
    {
        return $isManualRefresh;
    }

    public static function isDueForAutoSync(string|\Carbon\CarbonInterface|null $lastCheckedAt, ?string $plan, bool $isAdminInventory = false): bool
    {
        if ($lastCheckedAt === null || $lastCheckedAt === '') {
            return true;
        }

        if (is_string($lastCheckedAt)) {
            $lastCheckedAt = \Carbon\Carbon::parse($lastCheckedAt);
        }

        $hours = self::autoSyncIntervalHours($plan, $isAdminInventory);

        return $lastCheckedAt->copy()->addHours($hours)->lte(now());
    }

    private static function normalizePlan(?string $plan): string
    {
        if ($plan !== null && $plan !== '' && SubscriptionPlans::exists($plan)) {
            return $plan;
        }

        return 'pro';
    }
}
