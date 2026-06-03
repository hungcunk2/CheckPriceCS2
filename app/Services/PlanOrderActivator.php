<?php

namespace App\Services;

use App\Models\PlanOrder;
use App\Models\User;
use App\Support\SubscriptionPlans;
use Illuminate\Support\Carbon;

class PlanOrderActivator
{
    public function confirm(PlanOrder $order): void
    {
        if ($order->status !== PlanOrder::STATUS_PENDING) {
            return;
        }

        $user = $order->user;
        if ($user === null) {
            return;
        }

        $this->extendSubscription($user, (int) $order->months, $order->plan);

        $order->update([
            'status' => PlanOrder::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
    }

    public function extendSubscription(User $user, int $months, ?string $plan = null): void
    {
        $base = $user->paid_until instanceof Carbon && $user->paid_until->isFuture()
            ? $user->paid_until
            : now();

        $user->paid_until = $base->copy()->addMonths($months);
        $user->is_active = true;

        if ($plan !== null && $plan !== '' && SubscriptionPlans::exists($plan)) {
            $user->subscription_plan = $plan;
        }

        $user->save();
    }
}
