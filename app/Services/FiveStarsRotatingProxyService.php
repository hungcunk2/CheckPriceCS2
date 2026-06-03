<?php

namespace App\Services;

use App\Models\EmpireProxySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy xoay IPv4 — API 5Stars / proxyxoay.shop (xem tài liệu nhà cung cấp).
 *
 * @see https://5starsproxy.vn/?home=apixoay
 */
class FiveStarsRotatingProxyService
{
    private const API_GET = 'https://proxyxoay.shop/api/get.php';

    private const CACHE_KEY = 'fivestars_rotating_proxy:url';

    private const LAST_FETCH_KEY = 'fivestars_rotating_proxy:last_fetch_at';

    private const WAIT_UNTIL_KEY = 'fivestars_rotating_proxy:wait_until';

    /** Đã nhập key xoay 5Stars (dùng cho ảnh Steam / cron refresh). */
    public function isConfigured(): bool
    {
        return trim((string) EmpireProxySetting::current()->rotation_key) !== '';
    }

    /** Bật proxy cho API Empire (checkbox Admin). */
    public function isEnabled(): bool
    {
        return EmpireProxySetting::current()->enabled && $this->isConfigured();
    }

    /**
     * URL proxy cho Laravel Http::withOptions(['proxy' => ...]) hoặc null.
     */
    public function currentProxyUrl(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->refreshProxyIfDue(true);
    }

    /**
     * Cron / lệnh nền: lấy IP mới khi đủ FIVESTARS_PROXY_ROTATE_SECONDS (mặc định 62).
     */
    public function refreshProxyIfDue(bool $force = false): ?string
    {
        return $this->refreshProxyIfDueWithStatus($force)['url'];
    }

    /**
     * @return array{url: string|null, source: string, message: string}
     */
    public function refreshProxyIfDueWithStatus(bool $force = false): array
    {
        if (! $this->isConfigured()) {
            return ['url' => null, 'source' => 'disabled', 'message' => 'Chưa nhập key xoay 5Stars.'];
        }

        if (! $force && $this->isThrottled()) {
            return [
                'url' => $this->cachedProxyUrl(),
                'source' => 'throttle_cache',
                'message' => $this->throttleMessage(),
            ];
        }

        if (! $force) {
            $lastFetch = (int) Cache::get(self::LAST_FETCH_KEY, 0);
            if ($lastFetch > 0 && (time() - $lastFetch) < $this->rotateIntervalSeconds()) {
                $wait = $this->rotateIntervalSeconds() - (time() - $lastFetch);

                return [
                    'url' => $this->cachedProxyUrl(),
                    'source' => 'interval_cache',
                    'message' => 'Chưa đủ '.$wait.'s kể từ lần gọi API trước (FIVESTARS_PROXY_ROTATE_SECONDS).',
                ];
            }
        }

        return $this->refreshProxyWithStatus($force);
    }

    /**
     * @return array{ok: bool, message: string, proxy_url: string|null, raw: array<string, mixed>|null, throttled?: bool}
     */
    public function testFetch(bool $force = false): array
    {
        $settings = EmpireProxySetting::current();
        $key = trim((string) $settings->rotation_key);
        if ($key === '') {
            return ['ok' => false, 'message' => 'Chưa nhập key xoay.', 'proxy_url' => null, 'raw' => null];
        }

        if (! $force && $this->isThrottled()) {
            $cached = $this->cachedProxyUrl();
            if ($cached !== null) {
                return [
                    'ok' => true,
                    'message' => 'Đang chờ API 5Stars cho phép đổi proxy.',
                    'proxy_url' => $cached,
                    'raw' => null,
                    'throttled' => true,
                ];
            }
        }

        try {
            $response = Http::timeout(25)->get(self::API_GET, $this->queryParams($settings, $key));
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Lỗi kết nối: '.$e->getMessage(), 'proxy_url' => null, 'raw' => null];
        }

        $body = $response->json();
        if (! is_array($body)) {
            return ['ok' => false, 'message' => 'Phản hồi không phải JSON (HTTP '.$response->status().')', 'proxy_url' => null, 'raw' => null];
        }

        $status = (int) ($body['status'] ?? 0);
        $message = trim((string) ($body['message'] ?? $body['comen'] ?? 'lỗi'));

        if ($status !== 100) {
            $wait = $this->parseCooldownSeconds($message);
            if ($wait !== null) {
                $this->markThrottle($wait);
                $cached = $this->cachedProxyUrl();
                if ($cached !== null) {
                    return [
                        'ok' => true,
                        'message' => 'Giữ proxy hiện tại — '.$message,
                        'proxy_url' => $cached,
                        'raw' => $body,
                        'throttled' => true,
                    ];
                }
            }

            return [
                'ok' => false,
                'message' => 'API status='.$status.' — '.$message,
                'proxy_url' => null,
                'raw' => $body,
            ];
        }

        $url = $this->parseProxyFromBody($body, (bool) $settings->use_socks5);
        if ($url === null) {
            return ['ok' => false, 'message' => 'Không parse được proxy từ response.', 'proxy_url' => null, 'raw' => $body];
        }

        return [
            'ok' => true,
            'message' => $message,
            'proxy_url' => $url,
            'raw' => $body,
        ];
    }

    /**
     * @return array{url: string|null, source: string, message: string}
     */
    private function refreshProxyWithStatus(bool $force = false): array
    {
        $previous = $this->cachedProxyUrl();
        $result = $this->testFetch($force);

        if (! $result['ok'] || $result['proxy_url'] === null) {
            Log::warning('fivestars_proxy: refresh failed', ['message' => $result['message']]);

            return [
                'url' => $this->cachedProxyUrl(),
                'source' => 'error_cache',
                'message' => $result['message'],
            ];
        }

        if (! empty($result['throttled'])) {
            Log::info('fivestars_proxy: throttled, kept cached proxy', ['message' => $result['message']]);

            return [
                'url' => $result['proxy_url'],
                'source' => 'api_throttled',
                'message' => $result['message'],
            ];
        }

        $this->rememberProxy($result['proxy_url'], $result['message']);
        Cache::put(self::LAST_FETCH_KEY, time(), 86400);
        Log::info('fivestars_proxy: new proxy', ['url' => $result['proxy_url']]);

        $changed = $previous === null || $previous !== $result['proxy_url'];
        $hint = $changed
            ? 'API trả cổng proxy mới.'
            : 'API trả cùng host:port — bình thường với key xoay (IP ra ngoài đổi khi dùng proxy, không đổi trong URL).';

        return [
            'url' => $result['proxy_url'],
            'source' => 'api_fresh',
            'message' => trim($result['message'].' '.$hint),
        ];
    }

    /** IP mà website nhìn thấy khi đi qua proxy (khác host:port trong URL proxy). */
    public function probeExitIp(?string $proxyUrl = null): ?string
    {
        $proxyUrl ??= $this->cachedProxyUrl();
        if ($proxyUrl === null || $proxyUrl === '') {
            return null;
        }

        try {
            $ip = trim((string) Http::timeout(20)
                ->withOptions(['proxy' => $proxyUrl])
                ->get('https://api.ipify.org')
                ->body());
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        } catch (\Throwable $e) {
            Log::debug('fivestars_proxy: probe exit ip failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function throttleMessage(): string
    {
        $until = (int) Cache::get(self::WAIT_UNTIL_KEY, 0);
        $left = max(0, $until - time());

        return 'API 5Stars chưa cho đổi — còn ~'.$left.'s (giữ proxy trong cache).';
    }

    public function rememberProxy(string $url, string $message): void
    {
        Cache::put(self::CACHE_KEY, $url, $this->cacheTtlSeconds($message));
    }

    public function cacheTtlSeconds(string $message): int
    {
        $rotate = $this->rotateIntervalSeconds();

        $apiLifetime = null;
        if (preg_match('/die sau (\d+)s/i', $message, $m)) {
            $apiLifetime = max(1, (int) $m[1]);
        }

        $ttl = $rotate > 0 ? $rotate : 62;

        if ($apiLifetime !== null) {
            $ttl = min($ttl, max(10, $apiLifetime - 2));
        }

        return min($ttl, 3600);
    }

    public function rotateIntervalSeconds(): int
    {
        return max(60, (int) config('cs2price.fivestars_proxy_rotate_seconds', 62));
    }

    /**
     * @return array<string, mixed>
     */
    public function httpProxyOptions(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $url = $this->currentProxyUrl();
        if ($url === null || $url === '') {
            return [];
        }

        return ['proxy' => $url];
    }

    private function cachedProxyUrl(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    private function isThrottled(): bool
    {
        $until = (int) Cache::get(self::WAIT_UNTIL_KEY, 0);

        return $until > time();
    }

    private function markThrottle(int $seconds): void
    {
        $seconds = max(1, $seconds);
        Cache::put(self::WAIT_UNTIL_KEY, time() + $seconds, $seconds + 120);
    }

    private function parseCooldownSeconds(string $message): ?int
    {
        if (preg_match('/con\s+(\d+)\s*s/i', $message, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function parseProxyFromBody(array $body, bool $socks5): ?string
    {
        $raw = $socks5 ? ($body['proxysocks5'] ?? null) : ($body['proxyhttp'] ?? null);
        if (! is_string($raw) || trim($raw) === '') {
            $raw = $body['proxyhttp'] ?? $body['proxysocks5'] ?? null;
        }
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $parts = explode(':', trim($raw));
        $host = $parts[0] ?? '';
        $port = $parts[1] ?? '';
        if ($host === '' || $port === '') {
            return null;
        }

        $scheme = $socks5 ? 'socks5' : 'http';

        return "{$scheme}://{$host}:{$port}";
    }

    /**
     * @return array<string, string>
     */
    private function queryParams(EmpireProxySetting $settings, string $key): array
    {
        $params = [
            'key' => $key,
            'nhamang' => (string) ($settings->nhamang ?: 'Random'),
            'tinhthanh' => (string) ($settings->tinhthanh ?: '0'),
        ];

        $whitelist = trim((string) ($settings->whitelist_ip ?: ''));
        if ($whitelist === '') {
            $whitelist = $this->detectServerIp();
        }
        if ($whitelist !== '') {
            $params['whitelist'] = $whitelist;
        }

        return $params;
    }

    private function detectServerIp(): string
    {
        try {
            $ip = trim((string) Http::timeout(8)->get('https://api.ipify.org')->body());
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        } catch (\Throwable) {
            // ignore
        }

        return '';
    }
}
