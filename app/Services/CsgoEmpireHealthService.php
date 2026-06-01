<?php

namespace App\Services;

use App\Support\CsgoEmpireApiPool;
use Illuminate\Support\Facades\Cache;

class CsgoEmpireHealthService
{
    private const CACHE_KEY = 'empire_health:last';

    private const CACHE_PREFIX = 'empire_health:';

    private const CACHE_TTL = 300;

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $last = Cache::get(self::CACHE_KEY);

        return [
            'enabled' => (bool) config('cs2price.empire_enabled', false),
            'configured' => app(CsgoEmpireService::class)->isConfigured(),
            'api_key_count' => count(CsgoEmpireApiPool::accounts()),
            'api_keys_available' => count(CsgoEmpireApiPool::available()),
            'coin_to_usd' => \App\Support\ExchangeRateStore::empireCoinToUsd(),
            'coin_to_vnd' => \App\Support\ExchangeRateStore::empireCoinToVnd(),
            'max_fetches' => (int) config('cs2price.empire_max_fetches_per_check', 30),
            'last_check' => is_array($last) ? $last : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastCheckForLabel(string $label): ?array
    {
        $cached = Cache::get(self::CACHE_PREFIX.$label);

        return is_array($cached) ? $cached : null;
    }

    /**
     * Kiểm tra pool (key đầu tiên khả dụng) — giữ cho nút "Kiểm tra Empire" chung.
     *
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        if (CsgoEmpireApiPool::usesDatabase() && count(CsgoEmpireApiPool::accounts()) > 1) {
            return $this->probeAllSummary();
        }

        return $this->probePoolQuick();
    }

    /**
     * @return array<string, mixed>
     */
    public function probeLabel(string $label): array
    {
        $account = collect(CsgoEmpireApiPool::accounts())->firstWhere('label', $label);
        if ($account === null) {
            return [
                'label' => $label,
                'status' => 'missing',
                'http_status' => null,
                'message' => 'Không tìm thấy key trong cấu hình.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        return $this->probeAccount($account);
    }

    /**
     * @param  array{label: string, api_key: string}  $account
     * @return array<string, mixed>
     */
    public function probeAccount(array $account): array
    {
        $checkedAt = now()->toIso8601String();
        $started = microtime(true);
        $empire = app(CsgoEmpireService::class);

        if (! $empire->isEnabled()) {
            $result = [
                'label' => $account['label'],
                'status' => 'error',
                'http_status' => null,
                'message' => 'Empire tắt — bật EMPIRE_ENABLED=true trong .env',
                'checked_at' => $checkedAt,
            ];
            Cache::put(self::CACHE_PREFIX.$account['label'], $result, self::CACHE_TTL);

            return $result;
        }

        $price = $empire->probeForAccount($account);
        $ms = (int) round((microtime(true) - $started) * 1000);
        $httpStatus = $price['http_status'] ?? null;
        unset($price['http_status']);

        if ($price['error'] !== null) {
            $result = [
                'label' => $account['label'],
                'status' => 'error',
                'http_status' => $httpStatus,
                'message' => $price['error'],
                'latency_ms' => $ms,
                'checked_at' => $checkedAt,
            ];
        } else {
            $coins = $price['market_value_coins'];
            $cny = $empire->coinsToCny($coins);
            $result = [
                'label' => $account['label'],
                'status' => 'ok',
                'http_status' => $httpStatus,
                'message' => sprintf(
                    'OK — %.2f coin (≈ ¥%s), %d listing',
                    $coins,
                    $cny !== null ? number_format($cny, 2) : '—',
                    $price['listing_count'] ?? 0
                ),
                'latency_ms' => $ms,
                'checked_at' => $checkedAt,
            ];
        }

        Cache::put(self::CACHE_PREFIX.$account['label'], $result, self::CACHE_TTL);
        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function probeAll(): array
    {
        $results = [];

        foreach (CsgoEmpireApiPool::accounts() as $account) {
            $results[] = $this->probeAccount($account);
            usleep(400_000);
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function probePoolQuick(): array
    {
        $checkedAt = now()->toIso8601String();
        $started = microtime(true);
        $empire = app(CsgoEmpireService::class);

        if (! $empire->isEnabled()) {
            $result = [
                'status' => 'error',
                'message' => 'Empire tắt — bật EMPIRE_ENABLED=true trong .env',
                'checked_at' => $checkedAt,
            ];
            Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

            return $result;
        }

        if (! $empire->isConfigured()) {
            $result = [
                'status' => 'error',
                'message' => 'Chưa có API key Empire — thêm trong Admin → Buff & Empire',
                'checked_at' => $checkedAt,
            ];
            Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

            return $result;
        }

        $price = $empire->probe();
        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($price['error'] !== null) {
            $result = [
                'status' => 'error',
                'message' => $price['error'],
                'latency_ms' => $ms,
                'checked_at' => $checkedAt,
            ];
        } else {
            $coins = $price['market_value_coins'];
            $cny = $empire->coinsToCny($coins);
            $result = [
                'status' => 'ok',
                'message' => sprintf(
                    'OK — %.2f coin (≈ ¥%s), %d listing',
                    $coins,
                    $cny !== null ? number_format($cny, 2) : '—',
                    $price['listing_count'] ?? 0
                ),
                'latency_ms' => $ms,
                'checked_at' => $checkedAt,
            ];
        }

        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function probeAllSummary(): array
    {
        $results = $this->probeAll();
        $ok = array_values(array_filter($results, fn (array $r) => ($r['status'] ?? '') === 'ok'));
        $failed = array_values(array_filter($results, fn (array $r) => ($r['status'] ?? '') !== 'ok'));

        $checkedAt = now()->toIso8601String();

        if ($ok !== []) {
            $first = $ok[0];
            $summary = [
                'status' => $failed === [] ? 'ok' : 'warning',
                'message' => sprintf(
                    '%d/%d key OK — %s',
                    count($ok),
                    count($results),
                    $first['message'] ?? ''
                ),
                'latency_ms' => $first['latency_ms'] ?? null,
                'checked_at' => $checkedAt,
                'keys' => $results,
            ];
        } else {
            $messages = array_map(
                fn (array $r) => ($r['label'] ?? '?').': '.($r['message'] ?? 'lỗi'),
                $failed
            );
            $summary = [
                'status' => 'error',
                'message' => implode(' · ', array_slice($messages, 0, 3)).(count($messages) > 3 ? ' …' : ''),
                'checked_at' => $checkedAt,
                'keys' => $results,
            ];
        }

        Cache::put(self::CACHE_KEY, $summary, self::CACHE_TTL);

        return $summary;
    }
}
