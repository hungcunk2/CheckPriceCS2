<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class InventoryRefreshLimiter
{
    private function cacheKey(int $userId): string
    {
        return 'inventory_manual_refresh:'.CarbonImmutable::now('Asia/Ho_Chi_Minh')->format('Y-m-d').':'.$userId;
    }

    public function remaining(User $user): ?int
    {
        $limit = SubscriptionSyncPolicy::manualRefreshDailyLimit($user->subscription_plan);

        if ($limit === null) {
            return null;
        }

        $used = (int) Cache::get($this->cacheKey($user->id), 0);

        return max(0, $limit - $used);
    }

    public function canRefresh(User $user): bool
    {
        $remaining = $this->remaining($user);

        return $remaining === null || $remaining > 0;
    }

    public function record(User $user): void
    {
        $limit = SubscriptionSyncPolicy::manualRefreshDailyLimit($user->subscription_plan);

        if ($limit === null) {
            return;
        }

        $key = $this->cacheKey($user->id);
        $used = (int) Cache::get($key, 0);
        Cache::put($key, $used + 1, CarbonImmutable::now('Asia/Ho_Chi_Minh')->endOfDay());
    }

    public function limitExceededMessage(User $user): string
    {
        $limit = SubscriptionSyncPolicy::manualRefreshDailyLimit($user->subscription_plan) ?? 0;
        $plan = $user->subscriptionPlanLabel() ?? 'Pro';

        return "Gói {$plan} chỉ cho phép khoảng {$limit} lần refresh tay/ngày. Thử lại vào ngày mai hoặc nâng cấp gói.";
    }
}
