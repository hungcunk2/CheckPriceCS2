<?php

namespace App\Services;

use App\Models\TrackedInventory;
use Illuminate\Support\Collection;

class TrackedInventoryStore
{
    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return TrackedInventory::query()
            ->orderByDesc('last_total_cny')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TrackedInventory $row) => $this->asObject($row));
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
    public function upsert(array $attributes, ?int $id = null): object
    {
        $model = null;

        if ($id) {
            $model = TrackedInventory::query()->find($id);
        }

        if (! $model && ! empty($attributes['url'])) {
            $model = TrackedInventory::query()->where('url', $attributes['url'])->first();
        }

        $payload = $this->normalizeAttributes($attributes);

        if ($model) {
            $model->fill($payload);
            $model->save();
        } else {
            $model = TrackedInventory::query()->create(array_merge([
                'is_public' => true,
            ], $payload));
        }

        return $this->asObject($model);
    }

    public function delete(int $id): void
    {
        TrackedInventory::query()->where('id', $id)->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $allowed = [
            'label', 'url', 'steam_id', 'steam_persona_name', 'steam_avatar_url',
            'is_public', 'last_checked_at', 'last_total_cny', 'last_total_vnd',
            'item_count', 'priced_count', 'failed_count', 'last_snapshot',
        ];

        $payload = array_intersect_key($attributes, array_flip($allowed));

        if (isset($payload['last_checked_at']) && is_string($payload['last_checked_at'])) {
            $payload['last_checked_at'] = \Carbon\Carbon::parse($payload['last_checked_at']);
        }

        return $payload;
    }

    private function asObject(TrackedInventory $row): object
    {
        $data = $row->toArray();

        if ($row->last_checked_at) {
            $data['last_checked_at'] = $row->last_checked_at->toIso8601String();
        }

        $data['created_at'] = $row->created_at?->toIso8601String();
        $data['updated_at'] = $row->updated_at?->toIso8601String();

        return (object) $data;
    }
}
