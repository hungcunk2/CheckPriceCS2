<?php

namespace App\Services;

use App\Models\BuffAccount;
use Illuminate\Support\Collection;

class BuffAccountStore
{
    /**
     * @return Collection<int, object>
     */
    public function all(): Collection
    {
        return BuffAccount::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BuffAccount $row) => $this->asObject($row));
    }

    public function find(int $id): ?object
    {
        $row = BuffAccount::query()->find($id);

        return $row ? $this->asObject($row, includeSecrets: true) : null;
    }

    /**
     * @return list<array{label: string, session: string, csrf: string|null}>
     */
    public function activeForPool(): array
    {
        return BuffAccount::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (BuffAccount $row) => [
                'label' => $row->label,
                'session' => trim((string) $row->session),
                'csrf' => filled($row->csrf_token) ? trim((string) $row->csrf_token) : null,
            ])
            ->filter(fn (array $account) => $account['session'] !== '')
            ->values()
            ->all();
    }

    public function hasAny(): bool
    {
        try {
            return BuffAccount::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes, ?int $id = null): object
    {
        $model = $id ? BuffAccount::query()->find($id) : null;

        if (! $model) {
            $model = new BuffAccount;
            $model->label = (string) $attributes['label'];
        }

        $session = trim((string) ($attributes['session'] ?? ''));
        if ($session !== '') {
            $model->session = $session;
        } elseif (! $model->exists) {
            throw new \InvalidArgumentException('Session Buff là bắt buộc khi thêm acc mới.');
        }

        $csrf = trim((string) ($attributes['csrf_token'] ?? ''));
        if ($csrf !== '' || ! $model->exists) {
            $model->csrf_token = $csrf !== '' ? $csrf : null;
        }

        $model->label = (string) $attributes['label'];
        $model->is_active = (bool) ($attributes['is_active'] ?? true);
        $model->sort_order = (int) ($attributes['sort_order'] ?? 0);
        $model->save();

        return $this->asObject($model, includeSecrets: true);
    }

    public function delete(int $id): void
    {
        BuffAccount::query()->whereKey($id)->delete();
    }

    public function importFromEnvIfEmpty(): int
    {
        if ($this->hasAny()) {
            return 0;
        }

        $imported = 0;
        foreach ($this->envAccountsForImport() as $account) {
            BuffAccount::query()->create($account);
            $imported++;
        }

        return $imported;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function envAccountsForImport(): array
    {
        $accounts = [];

        $primary = trim((string) config('cs2price.buff_session', ''));
        if ($primary !== '') {
            $accounts[] = [
                'label' => (string) config('cs2price.buff_account_label', 'acc-1'),
                'session' => $primary,
                'csrf_token' => config('cs2price.buff_csrf_token'),
                'is_active' => true,
                'sort_order' => 1,
            ];
        }

        foreach (config('cs2price.buff_extra_accounts', []) as $index => $extra) {
            $session = trim((string) ($extra['session'] ?? ''));
            if ($session === '') {
                continue;
            }

            $accounts[] = [
                'label' => (string) ($extra['label'] ?? 'acc-'.($index + 2)),
                'session' => $session,
                'csrf_token' => $extra['csrf'] ?? null,
                'is_active' => true,
                'sort_order' => $index + 2,
            ];
        }

        return $accounts;
    }

    private function asObject(BuffAccount $row, bool $includeSecrets = false): object
    {
        $session = (string) $row->session;

        return (object) [
            'id' => $row->id,
            'label' => $row->label,
            'has_csrf' => filled($row->csrf_token),
            'is_active' => (bool) $row->is_active,
            'sort_order' => (int) $row->sort_order,
            'session_hint' => $this->maskSecret($session),
            'session' => $includeSecrets ? $session : null,
            'csrf_token' => $includeSecrets ? (string) ($row->csrf_token ?? '') : null,
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
