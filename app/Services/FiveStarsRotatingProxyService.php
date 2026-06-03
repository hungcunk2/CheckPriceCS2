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

    public function isEnabled(): bool
    {
        $settings = EmpireProxySetting::current();

        return $settings->enabled && trim((string) $settings->rotation_key) !== '';
    }

    /**
     * URL proxy cho Laravel Http::withOptions(['proxy' => ...]) hoặc null.
     */
    public function currentProxyUrl(): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->refreshProxy();
    }

    /**
     * @return array{ok: bool, message: string, proxy_url: string|null, raw: array<string, mixed>|null}
     */
    public function testFetch(): array
    {
        $settings = EmpireProxySetting::current();
        $key = trim((string) $settings->rotation_key);
        if ($key === '') {
            return ['ok' => false, 'message' => 'Chưa nhập key xoay.', 'proxy_url' => null, 'raw' => null];
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
        if ($status !== 100) {
            return [
                'ok' => false,
                'message' => 'API status='.$status.' — '.($body['message'] ?? $body['comen'] ?? 'lỗi'),
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
            'message' => trim((string) ($body['message'] ?? 'OK')),
            'proxy_url' => $url,
            'raw' => $body,
        ];
    }

    private function refreshProxy(): ?string
    {
        $result = $this->testFetch();
        if (! $result['ok'] || $result['proxy_url'] === null) {
            Log::warning('fivestars_proxy: refresh failed', ['message' => $result['message']]);

            return null;
        }

        $this->rememberProxy($result['proxy_url'], $result['message']);

        return $result['proxy_url'];
    }

    public function rememberProxy(string $url, string $message): void
    {
        Cache::put(self::CACHE_KEY, $url, $this->cacheTtlSeconds($message));
    }

    public function cacheTtlSeconds(string $message): int
    {
        $rotate = max(0, (int) config('cs2price.fivestars_proxy_rotate_seconds', 62));

        $apiLifetime = null;
        if (preg_match('/die sau (\d+)s/i', $message, $m)) {
            $apiLifetime = max(1, (int) $m[1]);
        }

        if ($rotate > 0) {
            $ttl = $rotate;
        } elseif ($apiLifetime !== null) {
            $ttl = max(10, $apiLifetime - 2);
        } else {
            $ttl = 62;
        }

        if ($apiLifetime !== null) {
            $ttl = min($ttl, max(10, $apiLifetime - 2));
        }

        return min($ttl, 3600);
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
