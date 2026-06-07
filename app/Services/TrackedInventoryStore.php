<?php

namespace App\Services;

use App\Models\TrackedInventory;
use App\Support\InventorySteamProfileResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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

    /**
     * @return Collection<int, object>
     */
    public function publicInventories(): Collection
    {
        return TrackedInventory::query()
            ->where('is_public', true)
            ->orderByDesc('last_total_cny')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TrackedInventory $row) => $this->asObject($row));
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

    public function findPublic(int $id): ?object
    {
        $row = TrackedInventory::query()
            ->where('id', $id)
            ->where('is_public', true)
            ->first();

        return $row ? $this->asObject($row) : null;
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
            $urlQuery = TrackedInventory::query()->where('url', $attributes['url']);
            if ($ownerUserId !== null) {
                $urlQuery->where('user_id', $ownerUserId);
            } elseif ($ownerAdminUsername !== null) {
                $urlQuery->whereNull('user_id')->where('admin_username', $ownerAdminUsername);
            } elseif (array_key_exists('user_id', $attributes)) {
                $userId = $attributes['user_id'];
                $userId === null
                    ? $urlQuery->whereNull('user_id')
                    : $urlQuery->where('user_id', $userId);
                if ($userId === null && array_key_exists('admin_username', $attributes)) {
                    $adminUsername = $attributes['admin_username'];
                    $adminUsername === null
                        ? $urlQuery->whereNull('admin_username')
                        : $urlQuery->where('admin_username', $adminUsername);
                }
            }
            $model = $urlQuery->first();
        }

        $payload = $this->normalizeAttributes($attributes);
        $payload = $this->mergeSteamProfileFromUrl($payload);

        if ($model) {
            $model->fill($payload);
            $model->save();
        } else {
            $model = TrackedInventory::query()->create(array_merge([
                'is_public' => (bool) ($payload['is_public'] ?? false),
            ], $payload));
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
            'is_public', 'sort_order', 'trade_at', 'last_checked_at', 'last_total_cny', 'last_total_vnd',
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
        $orphan = TrackedInventory::query()
            ->where('url', $url)
            ->whereNull('user_id')
            ->orderByDesc('id')
            ->first();

        return $orphan?->id;
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
