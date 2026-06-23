<?php

namespace App\Support;

use App\Services\FiveStarsRotatingProxyService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class Cs2CapHttp
{
    private static bool $lastRequestViaProxy = false;

    public static function lastRequestViaProxy(): bool
    {
        return self::$lastRequestViaProxy;
    }

    public static function normalizeApiKey(string $apiKey): string
    {
        $apiKey = trim($apiKey);

        return preg_replace('/\s+/', '', $apiKey) ?? $apiKey;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('cs2price.cs2cap_base_url', 'https://api.cs2c.app/v1'), '/');
    }

    /**
     * GET CS2Cap — mặc định qua proxy xoay 5Stars (nếu đã cấu hình key), không dùng IP VPS.
     */
    public static function get(string $apiKey, string $path, array $query = [], int $timeoutSeconds = 20): Response
    {
        $url = self::url($path);
        $preferProxy = self::shouldPreferProxy();

        self::$lastRequestViaProxy = $preferProxy && self::proxyAvailable();
        $response = self::buildClient($apiKey, $timeoutSeconds, $preferProxy)->get($url, $query);

        if (! self::isIpBanned($response) || self::$lastRequestViaProxy || ! self::proxyAvailable()) {
            return $response;
        }

        self::$lastRequestViaProxy = true;

        return self::buildClient($apiKey, $timeoutSeconds, true)->get($url, $query);
    }

    /**
     * @deprecated Dùng get() để có fallback proxy khi IP bị cấm.
     */
    public static function client(string $apiKey, int $timeoutSeconds = 20): PendingRequest
    {
        self::$lastRequestViaProxy = self::shouldPreferProxy();

        return self::buildClient($apiKey, $timeoutSeconds, self::$lastRequestViaProxy);
    }

    public static function isIpBanned(Response $response): bool
    {
        return $response->status() === 403
            && $response->json('code') === 'ACCOUNT_IP_BANNED';
    }

    public static function proxyAvailable(): bool
    {
        $proxy = app(FiveStarsRotatingProxyService::class)->currentProxyUrl();

        return is_string($proxy) && $proxy !== '';
    }

    public static function maskKey(string $apiKey): string
    {
        $apiKey = self::normalizeApiKey($apiKey);
        if ($apiKey === '') {
            return '—';
        }

        if (strlen($apiKey) <= 12) {
            return str_repeat('•', strlen($apiKey));
        }

        return substr($apiKey, 0, 10).'…'.substr($apiKey, -4);
    }

    /**
     * @return array{code: string|null, detail: string|null, is_json: bool}
     */
    public static function parseApiError(Response $response): array
    {
        $code = $response->json('code');
        $detail = $response->json('detail');
        if (is_string($code) || is_string($detail)) {
            return [
                'code' => is_string($code) ? $code : null,
                'detail' => is_string($detail) ? $detail : null,
                'is_json' => true,
            ];
        }

        $body = trim($response->body());
        if ($body !== '' && ! str_starts_with($body, '{')) {
            return [
                'code' => null,
                'detail' => strlen($body) > 200 ? substr($body, 0, 200).'…' : $body,
                'is_json' => false,
            ];
        }

        return ['code' => null, 'detail' => null, 'is_json' => false];
    }

    public static function formatHttpError(string $endpoint, Response $response, ?string $keyHint = null): string
    {
        $status = $response->status();
        $err = self::parseApiError($response);
        $parts = ["HTTP {$status}"];

        if ($err['code'] !== null) {
            $parts[] = $err['code'];
        }
        if ($err['detail'] !== null) {
            $parts[] = $err['detail'];
        }

        if ($err['code'] === 'ACCOUNT_IP_BANNED') {
            if (self::proxyAvailable()) {
                $parts[] = 'Đã có proxy 5Stars — deploy bản mới hoặc CS2CAP_USE_PROXY=true';
            } else {
                $parts[] = 'IP VPS bị CS2Cap cấm — thêm key proxy xoay 5Stars trong Admin';
            }
        } elseif ($status === 403 && $err['is_json']) {
            $parts[] = 'Key đúng nhưng thiếu quyền endpoint';
        } elseif ($status === 403 && ! $err['is_json']) {
            $parts[] = 'Có thể IP VPS bị chặn';
        }

        if ($keyHint !== null) {
            $parts[] = "key DB {$keyHint}";
        }

        return $endpoint.': '.implode(' — ', $parts);
    }

    private static function shouldPreferProxy(): bool
    {
        $setting = config('cs2price.cs2cap_use_proxy');
        if ($setting !== null && $setting !== '') {
            return filter_var($setting, FILTER_VALIDATE_BOOL);
        }

        // Mặc định: có key xoay 5Stars → CS2Cap đi qua proxy, tránh IP VPS bị cấm.
        return app(FiveStarsRotatingProxyService::class)->isConfigured();
    }

    private static function url(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return self::baseUrl().'/'.ltrim($path, '/');
    }

    private static function buildClient(string $apiKey, int $timeoutSeconds, bool $viaProxy): PendingRequest
    {
        $pending = Http::timeout($timeoutSeconds)
            ->withHeaders([
                'Authorization' => 'Bearer '.self::normalizeApiKey($apiKey),
                'Accept' => 'application/json',
                'Accept-Encoding' => 'gzip',
                'User-Agent' => 'CheckPriceCS2/1.0',
            ])
            ->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);

        if ($viaProxy) {
            $proxy = app(FiveStarsRotatingProxyService::class)->currentProxyUrl();
            if (is_string($proxy) && $proxy !== '') {
                $pending = $pending->withOptions(['proxy' => $proxy]);
            }
        }

        return $pending;
    }
}
