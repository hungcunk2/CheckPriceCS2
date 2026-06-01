<?php

namespace App\Services;

use App\Models\EmpireApiKey;
use Illuminate\Support\Collection;

class EmpireApiKeyStore
{
    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return EmpireApiKey::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EmpireApiKey $row) => $this->asObject($row));
    }

    public function find(int $id): ?object
    {
        $row = EmpireApiKey::query()->find($id);

        return $row ? $this->asObject($row, includeSecret: true) : null;
    }

    /**
     * @return list<array{label: string, api_key: string}>
     */
    public function activeForPool(): array
    {
        return EmpireApiKey::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (EmpireApiKey $row) => [
                'label' => $row->label,
                'api_key' => trim((string) $row->api_key),
            ])
            ->filter(fn (array $account) => $account['api_key'] !== '')
            ->values()
            ->all();
    }

    public function hasAny(): bool
    {
        try {
            return EmpireApiKey::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes, ?int $id = null): object
    {
        $model = $id ? EmpireApiKey::query()->find($id) : null;

        if (! $model) {
            $model = new EmpireApiKey;
        }

        $apiKey = trim((string) ($attributes['api_key'] ?? ''));
        if ($apiKey !== '') {
            $model->api_key = $apiKey;
        } elseif (! $model->exists) {
            throw new \InvalidArgumentException('API key Empire là bắt buộc khi thêm mới.');
        }

        $model->label = (string) $attributes['label'];
        $model->is_active = (bool) ($attributes['is_active'] ?? true);
        $model->sort_order = (int) ($attributes['sort_order'] ?? 0);
        $model->save();

        return $this->asObject($model, includeSecret: true);
    }

    public function delete(int $id): void
    {
        EmpireApiKey::query()->whereKey($id)->delete();
    }

    public function importFromEnvIfEmpty(): int
    {
        if ($this->hasAny()) {
            return 0;
        }

        $imported = 0;
        foreach ($this->envRowsForImport() as $row) {
            EmpireApiKey::query()->create($row);
            $imported++;
        }

        return $imported;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function envRowsForImport(): array
    {
        $rows = [];
        $primary = trim((string) config('cs2price.empire_api_key', ''));
        if ($primary !== '') {
            $rows[] = [
                'label' => (string) config('cs2price.empire_account_label', 'empire-1'),
                'api_key' => $primary,
                'is_active' => true,
                'sort_order' => 1,
            ];
        }

        foreach (config('cs2price.empire_extra_api_keys', []) as $index => $extra) {
            $key = trim((string) ($extra['api_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $rows[] = [
                'label' => (string) ($extra['label'] ?? 'empire-extra'),
                'api_key' => $key,
                'is_active' => true,
                'sort_order' => $index + 2,
            ];
        }

        return $rows;
    }

    private function asObject(EmpireApiKey $row, bool $includeSecret = false): object
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
