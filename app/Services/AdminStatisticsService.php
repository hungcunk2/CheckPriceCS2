<?php

namespace App\Services;

use App\Models\Cs2CapApiKey;
use App\Models\PlanOrder;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\TrackedInventory;
use App\Models\User;
use App\Support\Buff163AccountPool;
use App\Support\Cs2CapApiPool;
use App\Support\Cs2CapQuotaTracker;
use App\Support\InventoryWeaponStats;
use App\Support\SubscriptionPlans;
use App\Support\SubscriptionSyncPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class AdminStatisticsService
{
    public function __construct(
        private SupportChatService $supportChat,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $now = now($this->tz());

        return [
            'overview' => $this->overview($now),
            'subscription' => $this->subscription($now),
            'sync_quality' => $this->syncQuality($now),
            'aum' => $this->aum(),
            'support' => $this->support($now),
            'api_ops' => $this->apiOps($now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(Carbon $now): array
    {
        $paidActive = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('paid_until')->orWhere('paid_until', '>', $now);
            })
            ->whereNotNull('subscription_plan')
            ->count();

        $expiring7 = User::query()
            ->where('is_active', true)
            ->whereNotNull('paid_until')
            ->whereBetween('paid_until', [$now, $now->copy()->addDays(7)])
            ->count();

        $expiring30 = User::query()
            ->where('is_active', true)
            ->whereNotNull('paid_until')
            ->whereBetween('paid_until', [$now, $now->copy()->addDays(30)])
            ->count();

        $monthStart = $now->copy()->startOfMonth();
        $revenueMonth = 0;
        $pendingOrders = 0;

        if ($this->hasPlanOrders()) {
            $revenueMonth = (int) PlanOrder::query()
                ->where('status', PlanOrder::STATUS_CONFIRMED)
                ->where('confirmed_at', '>=', $monthStart)
                ->sum('amount_vnd');

            $pendingOrders = (int) PlanOrder::query()
                ->where('status', PlanOrder::STATUS_PENDING)
                ->count();
        }

        $invQuery = TrackedInventory::query();
        $totalVnd = (int) (clone $invQuery)->sum('last_total_vnd');
        $adminInv = (int) (clone $invQuery)->whereNull('user_id')->count();
        $memberInv = (int) (clone $invQuery)->whereNotNull('user_id')->count();

        $overdueSync = $this->countOverdueInventories();
        $pricing = $this->pricingCoverage();

        return [
            'paid_active_users' => $paidActive,
            'expiring_7_days' => $expiring7,
            'expiring_30_days' => $expiring30,
            'revenue_month_vnd' => $revenueMonth,
            'pending_orders' => $pendingOrders,
            'total_inventory_vnd' => $totalVnd,
            'admin_inventories' => $adminInv,
            'member_inventories' => $memberInv,
            'overdue_sync' => $overdueSync,
            'pricing_coverage_pct' => $pricing['pct'],
            'pricing_priced_items' => $pricing['priced'],
            'pricing_total_items' => $pricing['total'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscription(Carbon $now): array
    {
        $activeByPlan = [];
        foreach (array_keys(SubscriptionPlans::PLANS) as $plan) {
            $activeByPlan[$plan] = 0;
        }

        $activeUsers = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('paid_until')->orWhere('paid_until', '>', $now);
            })
            ->whereNotNull('subscription_plan')
            ->get(['subscription_plan']);

        $mrrEstimate = 0;
        foreach ($activeUsers as $user) {
            $plan = (string) $user->subscription_plan;
            if (! isset($activeByPlan[$plan])) {
                $activeByPlan[$plan] = 0;
            }
            $activeByPlan[$plan]++;
            $monthly = SubscriptionPlans::price($plan, 1);
            if ($monthly !== null) {
                $mrrEstimate += $monthly;
            }
        }

        $pendingOver24 = 0;
        $pendingOver48 = 0;
        $pendingList = collect();
        $revenueByMonth = [];
        $expiringThisWeek = collect();

        if ($this->hasPlanOrders()) {
            $pendingOver24 = (int) PlanOrder::query()
                ->where('status', PlanOrder::STATUS_PENDING)
                ->where('created_at', '<=', $now->copy()->subHours(24))
                ->count();

            $pendingOver48 = (int) PlanOrder::query()
                ->where('status', PlanOrder::STATUS_PENDING)
                ->where('created_at', '<=', $now->copy()->subHours(48))
                ->count();

            $pendingList = PlanOrder::query()
                ->with('user:id,name,email')
                ->where('status', PlanOrder::STATUS_PENDING)
                ->orderBy('created_at')
                ->limit(20)
                ->get();

            for ($i = 5; $i >= 0; $i--) {
                $month = $now->copy()->subMonths($i);
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfMonth();
                $label = $month->format('m/Y');
                $revenueByMonth[] = [
                    'label' => $label,
                    'amount_vnd' => (int) PlanOrder::query()
                        ->where('status', PlanOrder::STATUS_CONFIRMED)
                        ->whereBetween('confirmed_at', [$start, $end])
                        ->sum('amount_vnd'),
                ];
            }
        }

        $weekEnd = $now->copy()->addDays(7)->endOfDay();
        $expiringThisWeek = User::query()
            ->where('is_active', true)
            ->whereNotNull('paid_until')
            ->whereBetween('paid_until', [$now, $weekEnd])
            ->orderBy('paid_until')
            ->limit(30)
            ->get(['id', 'name', 'email', 'subscription_plan', 'paid_until']);

        return [
            'active_by_plan' => $activeByPlan,
            'mrr_estimate_vnd' => $mrrEstimate,
            'pending_over_24h' => $pendingOver24,
            'pending_over_48h' => $pendingOver48,
            'pending_orders' => $pendingList,
            'revenue_by_month' => $revenueByMonth,
            'expiring_this_week' => $expiringThisWeek,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncQuality(Carbon $now): array
    {
        $inventories = TrackedInventory::query()
            ->with('user:id,subscription_plan')
            ->get();

        $stale24 = 0;
        $empireSkins = 0;
        $totalSkins = 0;
        $buffWins = 0;
        $empireWins = 0;
        $worst = [];

        foreach ($inventories as $inv) {
            if ($inv->last_checked_at === null || $inv->last_checked_at->lt($now->copy()->subHours(24))) {
                $stale24++;
            }

            $snap = is_array($inv->last_snapshot) ? $inv->last_snapshot : [];
            $empireSkins += (int) ($snap['empire_priced_count'] ?? 0);
            $totalSkins += (int) ($inv->item_count ?? 0);
            $buffWins += (int) ($snap['sell_compare_buff_wins'] ?? 0);
            $empireWins += (int) ($snap['sell_compare_empire_wins'] ?? 0);

            $itemCount = (int) ($inv->item_count ?? 0);
            $priced = (int) ($inv->priced_count ?? 0);
            $failed = (int) ($inv->failed_count ?? 0);
            $ratio = $itemCount > 0 ? round(($priced / $itemCount) * 100, 1) : 100.0;

            if ($itemCount > 0 && ($ratio < 90 || $failed > 0)) {
                $worst[] = [
                    'id' => $inv->id,
                    'label' => $this->inventoryLabel($inv),
                    'item_count' => $itemCount,
                    'priced_count' => $priced,
                    'failed_count' => $failed,
                    'coverage_pct' => $ratio,
                    'last_checked_at' => $inv->last_checked_at?->timezone($this->tz())->format('d/m/Y H:i'),
                ];
            }
        }

        usort($worst, function ($a, $b) {
            $cmp = ($a['coverage_pct'] ?? 100) <=> ($b['coverage_pct'] ?? 100);
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($b['failed_count'] ?? 0) <=> ($a['failed_count'] ?? 0);
        });

        return [
            'stale_over_24h' => $stale24,
            'overdue_sync' => $this->countOverdueInventories($inventories),
            'empire_priced_skins' => $empireSkins,
            'total_skins' => $totalSkins,
            'empire_coverage_pct' => $totalSkins > 0 ? round(($empireSkins / $totalSkins) * 100, 1) : 0,
            'buff_sell_wins' => $buffWins,
            'empire_sell_wins' => $empireWins,
            'worst_inventories' => array_slice($worst, 0, 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aum(): array
    {
        $inventories = TrackedInventory::query()->get();
        $top = [];
        $publicVnd = 0;
        $privateVnd = 0;
        $publicCount = 0;
        $privateCount = 0;
        $allItems = [];

        foreach ($inventories as $inv) {
            $vnd = (int) ($inv->last_total_vnd ?? 0);
            $top[] = [
                'id' => $inv->id,
                'label' => $this->inventoryLabel($inv),
                'total_vnd' => $vnd,
                'total_cny' => (float) ($inv->last_total_cny ?? 0),
                'item_count' => (int) ($inv->item_count ?? 0),
                'is_public' => (bool) $inv->is_public,
                'owner' => $inv->user_id === null ? 'Admin' : 'Member',
            ];

            if ($inv->is_public) {
                $publicVnd += $vnd;
                $publicCount++;
            } else {
                $privateVnd += $vnd;
                $privateCount++;
            }

            $snap = is_array($inv->last_snapshot) ? $inv->last_snapshot : [];
            foreach ($snap['items'] ?? [] as $raw) {
                $allItems[] = (array) $raw;
            }
        }

        usort($top, fn ($a, $b) => ($b['total_vnd'] ?? 0) <=> ($a['total_vnd'] ?? 0));

        return [
            'top_inventories' => array_slice($top, 0, 10),
            'public' => ['count' => $publicCount, 'total_vnd' => $publicVnd],
            'private' => ['count' => $privateCount, 'total_vnd' => $privateVnd],
            'weapon_stats' => InventoryWeaponStats::summarize($allItems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function support(Carbon $now): array
    {
        if (! $this->hasSupportTables()) {
            return [
                'available' => false,
                'unread_conversations' => 0,
                'avg_response_minutes' => null,
                'active_users_with_ticket' => 0,
                'recent_unread' => collect(),
            ];
        }

        $unread = $this->supportChat->unreadCountForAdmin();

        $recentUnread = SupportConversation::query()
            ->with(['user:id,name,email', 'lastMessage'])
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('support_messages')
                    ->whereColumn('support_messages.support_conversation_id', 'support_conversations.id')
                    ->where('support_messages.sender', SupportMessage::SENDER_MEMBER)
                    ->where(function ($inner) {
                        $inner->whereNull('support_conversations.admin_last_read_at')
                            ->orWhereColumn('support_messages.created_at', '>', 'support_conversations.admin_last_read_at');
                    });
            })
            ->orderByDesc('last_message_at')
            ->limit(10)
            ->get();

        $since = $now->copy()->subDays(30);
        $avgMinutes = $this->averageAdminResponseMinutes($since);

        $activeWithTicket = (int) User::query()
            ->where('is_active', true)
            ->whereHas('supportConversation', function ($q) use ($now) {
                $q->where('last_message_at', '>=', $now->copy()->subDays(14));
            })
            ->count();

        return [
            'available' => true,
            'unread_conversations' => $unread,
            'avg_response_minutes' => $avgMinutes,
            'active_users_with_ticket' => $activeWithTicket,
            'recent_unread' => $recentUnread,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function apiOps(Carbon $now): array
    {
        $staleCount = TrackedInventory::query()
            ->where(function ($q) use ($now) {
                $q->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', $now->copy()->subHours(24));
            })
            ->count();

        $buffActive = count(Buff163AccountPool::accounts());
        $cs2capKeys = $this->cs2CapKeyRows();
        $exhausted = array_values(array_filter($cs2capKeys, fn (array $row) => ! empty($row['exhausted'])));
        $activeKeys = array_values(array_filter($cs2capKeys, fn (array $row) => ! empty($row['active']) && empty($row['exhausted'])));

        return [
            'buff_accounts' => $buffActive,
            'cs2cap_total' => count($cs2capKeys),
            'cs2cap_active' => count($activeKeys),
            'cs2cap_exhausted' => count($exhausted),
            'cs2cap_keys' => $cs2capKeys,
            'stale_inventories' => $staleCount,
            'critical_alert' => $staleCount > 0 && count($exhausted) > 0,
            'alert_message' => $staleCount > 0 && count($exhausted) > 0
                ? "{$staleCount} kho chưa sync >24h và ".count($exhausted).' key CS2Cap hết quota — kiểm tra ngay.'
                : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cs2CapKeyRows(): array
    {
        $rows = [];

        if (Cs2CapApiPool::usesDatabase() && Schema::hasTable('cs2cap_api_keys')) {
            foreach (Cs2CapApiKey::query()->orderBy('sort_order')->orderBy('id')->get() as $key) {
                $rows[] = $this->cs2CapKeyRow((string) $key->label, (bool) $key->is_active);
            }
        } else {
            foreach (Cs2CapApiPool::accounts() as $account) {
                $rows[] = $this->cs2CapKeyRow((string) $account['label'], true);
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function cs2CapKeyRow(string $label, bool $active): array
    {
        $snapshot = Cs2CapApiPool::quotaSnapshot($label);
        $exhausted = Cs2CapQuotaTracker::isExhausted($label);

        return [
            'label' => $label,
            'active' => $active,
            'exhausted' => $exhausted,
            'tier' => $snapshot['tier'] ?? null,
            'quota_remaining' => $snapshot['quota_remaining'] ?? null,
            'quota_limit' => $snapshot['quota_limit'] ?? null,
        ];
    }

    private function countOverdueInventories(?\Illuminate\Support\Collection $inventories = null): int
    {
        $inventories ??= TrackedInventory::query()->with('user:id,subscription_plan')->get();
        $count = 0;

        foreach ($inventories as $inv) {
            $isAdmin = $inv->user_id === null;
            $plan = $inv->user?->subscription_plan;
            if (SubscriptionSyncPolicy::isDueForAutoSync($inv->last_checked_at, $plan, $isAdmin)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{priced: int, total: int, pct: float|null}
     */
    private function pricingCoverage(): array
    {
        $totals = TrackedInventory::query()
            ->selectRaw('COALESCE(SUM(priced_count), 0) as priced, COALESCE(SUM(item_count), 0) as total')
            ->first();

        $priced = (int) ($totals->priced ?? 0);
        $total = (int) ($totals->total ?? 0);
        $pct = $total > 0 ? round(($priced / $total) * 100, 1) : null;

        return ['priced' => $priced, 'total' => $total, 'pct' => $pct];
    }

    private function averageAdminResponseMinutes(Carbon $since): ?float
    {
        $memberMessages = SupportMessage::query()
            ->where('sender', SupportMessage::SENDER_MEMBER)
            ->where('created_at', '>=', $since)
            ->orderBy('support_conversation_id')
            ->orderBy('id')
            ->get(['id', 'support_conversation_id', 'created_at']);

        if ($memberMessages->isEmpty()) {
            return null;
        }

        $deltas = [];
        foreach ($memberMessages as $memberMsg) {
            $adminReply = SupportMessage::query()
                ->where('support_conversation_id', $memberMsg->support_conversation_id)
                ->where('sender', SupportMessage::SENDER_ADMIN)
                ->where('id', '>', $memberMsg->id)
                ->orderBy('id')
                ->first(['created_at']);

            if ($adminReply === null) {
                continue;
            }

            $deltas[] = $memberMsg->created_at->diffInMinutes($adminReply->created_at);
        }

        if ($deltas === []) {
            return null;
        }

        return round(array_sum($deltas) / count($deltas), 1);
    }

    private function inventoryLabel(TrackedInventory $inv): string
    {
        $label = trim((string) ($inv->label ?? ''));
        if ($label !== '' && ! str_starts_with($label, 'http')) {
            return $label;
        }

        $persona = trim((string) ($inv->steam_persona_name ?? ''));
        if ($persona !== '') {
            return $persona;
        }

        return 'Kho #'.$inv->id;
    }

    private function hasPlanOrders(): bool
    {
        try {
            return Schema::hasTable('plan_orders');
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasSupportTables(): bool
    {
        try {
            return Schema::hasTable('support_conversations') && Schema::hasTable('support_messages');
        } catch (\Throwable) {
            return false;
        }
    }

    private function tz(): \DateTimeZone
    {
        return new \DateTimeZone(config('cs2price.timezone', 'Asia/Ho_Chi_Minh'));
    }
}
