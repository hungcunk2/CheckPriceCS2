<?php

namespace App\Services;

use App\Models\Cs2CapApiKey;
use App\Support\Cs2CapHttp;
use App\Support\Cs2CapQuotaTracker;
use Illuminate\Support\Collection;

class Cs2CapApiKeyStore
{
    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return Cs2CapApiKey::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Cs2CapApiKey $row) => $this->asObject($row));
    }

    /**
     * Danh sách key kèm secret — chỉ dùng server-side (probe hàng loạt).
     *
     * @return Collection<int, object>
     */
    public function allWithSecrets(): Collection
    {
        return Cs2CapApiKey::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Cs2CapApiKey $row) => $this->asObject($row, includeSecret: true));
    }

    public function find(int $id): ?object
    {
        $row = Cs2CapApiKey::query()->find($id);

        return $row ? $this->asObject($row, includeSecret: true) : null;
    }

    /**
     * @return list<array{label: string, api_key: string}>
     */
    public function activeForPool(): array
    {
        try {
            return Cs2CapApiKey::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Cs2CapApiKey $row) => [
                    'label' => $row->label,
                    'api_key' => Cs2CapHttp::normalizeApiKey((string) $row->api_key),
                ])
                ->filter(fn (array $account) => $account['api_key'] !== '')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    public function hasAny(): bool
    {
        try {
            return Cs2CapApiKey::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function nextSortOrder(): int
    {
        $max = Cs2CapApiKey::query()->max('sort_order');

        return ($max === null ? -1 : (int) $max) + 1;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes, ?int $id = null): object
    {
        $model = $id ? Cs2CapApiKey::query()->find($id) : null;

        if (! $model) {
            $model = new Cs2CapApiKey;
        }

        $previousLabel = $model->exists ? (string) $model->label : null;
        $previousApiKey = $model->exists ? Cs2CapHttp::normalizeApiKey((string) $model->api_key) : null;

        $apiKey = Cs2CapHttp::normalizeApiKey((string) ($attributes['api_key'] ?? ''));
        if ($apiKey !== '') {
            $model->api_key = $apiKey;
        } elseif (! $model->exists) {
            throw new \InvalidArgumentException('API key CS2Cap là bắt buộc khi thêm mới.');
        }

        $model->label = (string) $attributes['label'];
        $model->is_active = (bool) ($attributes['is_active'] ?? true);
        if (! $model->exists) {
            $incoming = (int) ($attributes['sort_order'] ?? 0);
            $model->sort_order = $incoming > 0 ? $incoming : $this->nextSortOrder();
        } else {
            $model->sort_order = (int) ($attributes['sort_order'] ?? $model->sort_order);
        }
        $model->save();

        $newLabel = (string) $model->label;
        $apiKeyChanged = $apiKey !== '' && $previousApiKey !== null && $apiKey !== $previousApiKey;
        if (! $id || $apiKeyChanged) {
            Cs2CapQuotaTracker::forget($newLabel);
        }
        if ($previousLabel !== null && $previousLabel !== $newLabel) {
            Cs2CapQuotaTracker::forget($previousLabel);
        }

        return $this->asObject($model, includeSecret: true);
    }

    public function delete(int $id): void
    {
        $row = Cs2CapApiKey::query()->find($id);
        if ($row) {
            Cs2CapQuotaTracker::forget((string) $row->label);
        }

        Cs2CapApiKey::query()->whereKey($id)->delete();
    }

    public function importFromEnvIfEmpty(): int
    {
        if ($this->hasAny()) {
            return 0;
        }

        $primary = trim((string) config('cs2price.cs2cap_api_key', ''));
        if ($primary === '') {
            return 0;
        }

        $label = (string) config('cs2price.cs2cap_key_label', 'cs2cap-1');
        Cs2CapApiKey::query()->create([
            'label' => $label,
            'api_key' => $primary,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return 1;
    }

    private function asObject(Cs2CapApiKey $row, bool $includeSecret = false): object
    {
        $apiKey = (string) $row->api_key;

        return (object) [
            'id' => $row->id,
            'label' => $row->label,
            'is_active' => (bool) $row->is_active,
            'sort_order' => (int) $row->sort_order,
            'api_key_hint' => $this->maskSecret($apiKey),
            'api_key' => $includeSecret ? $apiKey : null,
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    private function maskSecret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '—';
        }

        if (strlen($value) <= 12) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 6).'…'.substr($value, -4);
    }
}

