<?php

namespace App\Services;

use App\Models\TrackedInventory;
use App\Models\User;
use App\Support\InventorySteamProfileResolver;
use App\Support\InventoryUrlMatcher;
use App\Support\SubscriptionSyncPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class TrackedInventoryStore
{
    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return $this->mapQuery(
            TrackedInventory::query()->orderByDesc('last_total_cny')->orderByDesc('id')
        );
    }

    /**
     * Kho đến hạn auto-sync — lọc SQL (MariaDB/MySQL), không load toàn bộ bảng.
     *
     * @return Collection<int, object>
     */
    public function dueForAutoSync(): Collection
    {
        if (! Schema::hasTable('tracked_inventories')) {
            return collect();
        }

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return $this->dueForAutoSyncFallback();
        }

        $rows = TrackedInventory::query()
            ->leftJoin('users', 'users.id', '=', 'tracked_inventories.user_id')
            ->select('tracked_inventories.*')
            ->where(function ($query) {
                $query->whereNull('tracked_inventories.last_checked_at')
                    ->orWhereRaw($this->dueForAutoSyncSql(), [now()]);
            })
            ->orderBy('tracked_inventories.last_checked_at')
            ->orderBy('tracked_inventories.id')
            ->get();

        return $rows->map(fn (TrackedInventory $row) => $this->asObject($row));
    }

    private function dueForAutoSyncSql(): string
    {
        return 'tracked_inventories.last_checked_at <= DATE_SUB(?, INTERVAL (
            CASE
                WHEN tracked_inventories.user_id IS NULL THEN 1
                WHEN users.subscription_plan = \'shop\' THEN 1
                WHEN users.subscription_plan = \'max\' THEN 2
                WHEN users.subscription_plan = \'plus\' THEN 4
                WHEN users.subscription_plan = \'pro\' THEN 8
                ELSE 8
            END
        ) HOUR)';
    }

    /**
     * @return Collection<int, object>
     */
    private function dueForAutoSyncFallback(): Collection
    {
        $candidates = TrackedInventory::query()
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<=', now()->subHour());
            })
            ->orderBy('last_checked_at')
            ->orderBy('id')
            ->get()
            ->map(fn (TrackedInventory $row) => $this->asObject($row));

        if ($candidates->isEmpty()) {
            return collect();
        }

        $usersById = User::query()
            ->whereIn('id', $candidates->pluck('user_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        return $candidates->filter(function (object $row) use ($usersById) {
            $plan = null;
            if ($row->user_id !== null && isset($usersById[(int) $row->user_id])) {
                $plan = $usersById[(int) $row->user_id]->subscription_plan;
            }

            return SubscriptionSyncPolicy::isDueForAutoSync(
                $row->last_checked_at ?? null,
                $plan,
                $row->user_id === null,
            );
        })->values();
    }

    /**
     * @return Collection<int, object>
     */
    public function forAdmin(string $adminUsername): Collection
    {
        return $this->mapQuery(
            $this->adminInventoryQuery($adminUsername)
                ->orderByDesc('last_total_cny')
                ->orderByDesc('id')
        );
    }

    /**
     * @return Collection<int, object>
     */
    public function forUser(int $userId): Collection
    {
        return $this->mapQuery(
            $this->memberInventoryQuery($userId)
                ->orderByDesc('last_total_cny')
                ->orderByDesc('id')
        );
    }

    public function countForUser(int $userId): int
    {
        return $this->memberInventoryQuery($userId)->count();
    }

    public function find(int $id): ?object
    {
        $row = TrackedInventory::query()->find($id);

        return $row ? $this->asObject($row) : null;
    }

    public function findForAdmin(int $id, string $adminUsername): ?object
    {
        $row = $this->adminInventoryQuery($adminUsername)
            ->where('id', $id)
            ->first();

        return $row ? $this->asObject($row) : null;
    }

    public function findForUser(int $id, int $userId): ?object
    {
        $row = $this->memberInventoryQuery($userId)
            ->where('id', $id)
            ->first();

        return $row ? $this->asObject($row) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertForAdmin(string $adminUsername, array $attributes, ?int $id = null): object
    {
        $url = trim((string) ($attributes['url'] ?? ''));
        if ($id === null && $url !== '' && $this->hasDuplicateForAdmin($adminUsername, $url)) {
            throw new RuntimeException('Kho Steam này đã có trong danh sách — không thêm trùng.');
        }

        return $this->upsert(
            array_merge($attributes, [
                'user_id' => null,
                'admin_username' => $adminUsername,
            ]),
            $id,
            ownerAdminUsername: $adminUsername,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertForUser(int $userId, array $attributes, ?int $id = null): object
    {
        $url = trim((string) ($attributes['url'] ?? ''));
        if ($id === null && $url !== '') {
            if ($this->hasDuplicateForUser($userId, $url)) {
                throw new RuntimeException('Kho Steam này đã có trong danh sách — không thêm trùng.');
            }

            $claimed = $this->claimOrphanForUser($userId, $url);
            if ($claimed !== null) {
                return $this->upsert(
                    array_merge($attributes, [
                        'user_id' => $userId,
                        'admin_username' => null,
                    ]),
                    $claimed,
                    $userId,
                );
            }
        }

        return $this->upsert(
            array_merge($attributes, [
                'user_id' => $userId,
                'admin_username' => null,
            ]),
            $id,
            $userId,
        );
    }

    public function deleteForAdmin(int $id, string $adminUsername): bool
    {
        return $this->adminInventoryQuery($adminUsername)
            ->where('id', $id)
            ->delete() > 0;
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        return $this->memberInventoryQuery($userId)
            ->where('id', $id)
            ->delete() > 0;
    }

    public function hasDuplicateForUser(int $userId, string $url, ?int $exceptId = null): bool
    {
        return $this->findOwnedDuplicate($url, ownerUserId: $userId, exceptId: $exceptId) !== null;
    }

    public function hasDuplicateForAdmin(string $adminUsername, string $url, ?int $exceptId = null): bool
    {
        return $this->findOwnedDuplicate($url, ownerAdminUsername: $adminUsername, exceptId: $exceptId) !== null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes, ?int $id = null, ?int $ownerUserId = null, ?string $ownerAdminUsername = null): object
    {
        $model = null;

        if ($id) {
            $idQuery = TrackedInventory::query()->where('id', $id);
            if ($ownerUserId !== null) {
                $idQuery->where('user_id', $ownerUserId);
            } elseif ($ownerAdminUsername !== null) {
                $idQuery->whereNull('user_id')->where('admin_username', $ownerAdminUsername);
            }
            $model = $idQuery->first();
        }

        if (! $model && ! empty($attributes['url'])) {
            $existing = $this->findOwnedDuplicate(
                (string) $attributes['url'],
                ownerUserId: $ownerUserId,
                ownerAdminUsername: $ownerAdminUsername,
            );
            if ($existing !== null) {
                $model = $existing;
            }
        }

        $payload = $this->normalizeAttributes($attributes);
        $payload = $this->mergeSteamProfileFromUrl($payload);

        if ($model) {
            $model->fill($payload);
            $model->save();
        } else {
            $model = TrackedInventory::query()->create($payload);
        }

        return $this->asObject($model);
    }

    public function delete(int $id): void
    {
        TrackedInventory::query()->where('id', $id)->delete();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeSteamProfileFromUrl(array $payload): array
    {
        $url = trim((string) ($payload['url'] ?? ''));
        if ($url === '') {
            return $payload;
        }

        return app(InventorySteamProfileResolver::class)->mergeIntoAttributes($payload, $url);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $allowed = [
            'user_id', 'admin_username', 'label', 'notes', 'url', 'steam_id', 'steam_persona_name', 'steam_avatar_url',
            'sort_order', 'trade_at', 'last_checked_at', 'last_total_cny', 'last_total_vnd',
            'item_count', 'priced_count', 'failed_count', 'last_snapshot',
        ];

        $payload = array_intersect_key($attributes, array_flip($allowed));

        if (isset($payload['last_checked_at']) && is_string($payload['last_checked_at'])) {
            $payload['last_checked_at'] = \Carbon\Carbon::parse($payload['last_checked_at']);
        }

        if (! Schema::hasColumn('tracked_inventories', 'trade_at')) {
            unset($payload['trade_at']);
        } elseif (array_key_exists('trade_at', $payload)) {
            $payload['trade_at'] = filled($payload['trade_at'])
                ? \Carbon\Carbon::parse($payload['trade_at'])
                : null;
        }

        return $payload;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<TrackedInventory>  $query
     * @return Collection<int, object>
     */
    private function mapQuery($query): Collection
    {
        return $query->get()->map(fn (TrackedInventory $row) => $this->asObject($row));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TrackedInventory>
     */
    private function adminInventoryQuery(string $adminUsername)
    {
        $memberUrls = TrackedInventory::query()
            ->whereNotNull('user_id')
            ->pluck('url');

        $query = TrackedInventory::query()
            ->whereNull('user_id')
            ->where('admin_username', $adminUsername);

        if ($memberUrls->isNotEmpty()) {
            $query->whereNotIn('url', $memberUrls);
        }

        return $query;
    }

    private function claimOrphanForUser(int $userId, string $url): ?int
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $variants = InventoryUrlMatcher::urlVariants($url);
        $steamId = InventoryUrlMatcher::steamIdFromUrlLocal($url);

        $orphan = TrackedInventory::query()
            ->whereNull('user_id')
            ->where(function ($q) use ($variants, $steamId) {
                $hasUrl = $variants !== [];
                $hasSteam = $steamId !== null;

                if (! $hasUrl && ! $hasSteam) {
                    $q->whereRaw('0 = 1');

                    return;
                }

                if ($hasUrl) {
                    $q->whereIn('url', $variants);
                }

                if ($hasSteam) {
                    $hasUrl
                        ? $q->orWhere('steam_id', $steamId)
                        : $q->where('steam_id', $steamId);
                }
            })
            ->orderByDesc('id')
            ->first();

        return $orphan?->id;
    }

    private function findOwnedDuplicate(
        string $url,
        ?int $ownerUserId = null,
        ?string $ownerAdminUsername = null,
        ?int $exceptId = null,
    ): ?TrackedInventory {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $query = TrackedInventory::query();

        if ($ownerUserId !== null) {
            $query->where('user_id', $ownerUserId);
        } elseif ($ownerAdminUsername !== null) {
            // Chỉ kho admin thuộc tài khoản đang đăng nhập — không khớp bản ghi ẩn (admin_username null).
            $query->whereNull('user_id')->where('admin_username', $ownerAdminUsername);
        } else {
            return null;
        }

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $variants = InventoryUrlMatcher::urlVariants($url);
        $steamId = InventoryUrlMatcher::steamIdFromUrlLocal($url);

        return $query->where(function ($q) use ($variants, $steamId) {
            $hasUrl = $variants !== [];
            $hasSteam = $steamId !== null;

            if (! $hasUrl && ! $hasSteam) {
                $q->whereRaw('0 = 1');

                return;
            }

            if ($hasUrl) {
                $q->whereIn('url', $variants);
            }

            if ($hasSteam) {
                $hasUrl
                    ? $q->orWhere('steam_id', $steamId)
                    : $q->where('steam_id', $steamId);
            }
        })->orderByDesc('id')->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TrackedInventory>
     */
    private function memberInventoryQuery(int $userId)
    {
        return TrackedInventory::query()->where('user_id', $userId);
    }

    private function asObject(TrackedInventory $row): object
    {
        $data = $row->toArray();

        if ($row->last_checked_at) {
            $data['last_checked_at'] = $row->last_checked_at->toIso8601String();
        }

        if ($row->trade_at) {
            $data['trade_at'] = $row->trade_at->toIso8601String();
        }

        $data['created_at'] = $row->created_at?->toIso8601String();
        $data['updated_at'] = $row->updated_at?->toIso8601String();

        return (object) $data;
    }
}
