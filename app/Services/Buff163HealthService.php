<?php

namespace App\Services;

use App\Support\Buff163AccountPool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Buff163HealthService
{
    private const PROBE_SEARCH = 'AK-47 | Redline (Field-Tested)';

    private const CACHE_PREFIX = 'buff163_health:';

    private const CACHE_TTL = 300;

    /**
     * @return list<array<string, mixed>>
     */
    public function accountsOverview(): array
    {
        $availableLabels = collect(Buff163AccountPool::available())
            ->pluck('label')
            ->all();

        return array_map(function (array $account) use ($availableLabels) {
            $label = $account['label'];
            $cached = Cache::get(self::CACHE_PREFIX.$label);
            $cooldown = Buff163AccountPool::cooldownRemaining($label);

            return [
                'label' => $label,
                'has_csrf' => filled($account['csrf'] ?? null),
                'in_cooldown' => $cooldown !== null,
                'cooldown_seconds' => $cooldown,
                'available' => in_array($label, $availableLabels, true),
                'last_check' => is_array($cached) ? $cached : null,
            ];
        }, Buff163AccountPool::accounts());
    }

    /**
     * @return array<string, mixed>
     */
    public function probe(string $label): array
    {
        $account = collect(Buff163AccountPool::accounts())
            ->firstWhere('label', $label);

        if ($account === null) {
            return [
                'label' => $label,
                'status' => 'missing',
                'http_status' => null,
                'message' => 'Không tìm thấy acc trong cấu hình.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        $result = $this->probeAccount($account);
        Cache::put(self::CACHE_PREFIX.$label, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function probeAll(): array
    {
        $results = [];

        foreach (Buff163AccountPool::accounts() as $account) {
            $result = $this->probe($account['label']);
            $results[] = $result;
            usleep(400_000);
        }

        return $results;
    }

    /**
     * @param  array{label: string, session: string, csrf: string|null}  $account
     * @return array<string, mixed>
     */
    private function probeAccount(array $account): array
    {
        $checkedAt = now()->toIso8601String();

        try {
            $response = Http::timeout(15)
                ->withHeaders(Buff163AccountPool::headers($account))
                ->get('https://buff.163.com/api/market/goods', [
                    'game' => 'csgo',
                    'page_num' => 1,
                    'search' => self::PROBE_SEARCH,
                    'tab' => 'selling',
                ]);
        } catch (\Throwable $e) {
            return [
                'label' => $account['label'],
                'status' => 'error',
                'http_status' => null,
                'message' => 'Lỗi kết nối: '.$e->getMessage(),
                'checked_at' => $checkedAt,
            ];
        }

        return $this->interpretResponse($account['label'], $response, $checkedAt);
    }

    /**
     * @return array<string, mixed>
     */
    private function interpretResponse(string $label, Response $response, string $checkedAt): array
    {
        $httpStatus = $response->status();

        if ($httpStatus === 403) {
            return [
                'label' => $label,
                'status' => 'blocked',
                'http_status' => 403,
                'message' => 'Buff chặn (403) — session hết hạn hoặc IP bị chặn.',
                'checked_at' => $checkedAt,
            ];
        }

        if ($httpStatus === 429) {
            return [
                'label' => $label,
                'status' => 'rate_limited',
                'http_status' => 429,
                'message' => 'Buff tạm chặn (429) — gọi quá nhanh.',
                'checked_at' => $checkedAt,
            ];
        }

        if (! $response->successful()) {
            return [
                'label' => $label,
                'status' => 'error',
                'http_status' => $httpStatus,
                'message' => 'Buff HTTP '.$httpStatus,
                'checked_at' => $checkedAt,
            ];
        }

        $body = $response->json();
        if (($body['code'] ?? '') !== 'OK') {
            $message = (string) ($body['error'] ?? $body['msg'] ?? 'Buff từ chối — kiểm tra session/CSRF.');

            return [
                'label' => $label,
                'status' => 'invalid_session',
                'http_status' => $httpStatus,
                'message' => $message,
                'checked_at' => $checkedAt,
            ];
        }

        $items = $body['data']['items'] ?? [];
        $price = isset($items[0]['sell_min_price']) ? (float) $items[0]['sell_min_price'] : null;

        return [
            'label' => $label,
            'status' => 'ok',
            'http_status' => $httpStatus,
            'message' => $price !== null
                ? 'Hoạt động — probe ¥'.number_format($price, 2)
                : 'API OK nhưng chưa thấy giá probe.',
            'checked_at' => $checkedAt,
        ];
    }
}
