<?php

namespace App\Support;

use App\Services\FiveStarsRotatingProxyService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class Cs2CapHttp
{
    public static function normalizeApiKey(string $apiKey): string
    {
        $apiKey = trim($apiKey);

        return preg_replace('/\s+/', '', $apiKey) ?? $apiKey;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('cs2price.cs2cap_base_url', 'https://api.cs2c.app/v1'), '/');
    }

    public static function client(string $apiKey, int $timeoutSeconds = 20): PendingRequest
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

        if (filter_var(config('cs2price.cs2cap_use_proxy', false), FILTER_VALIDATE_BOOL)) {
            $proxy = app(FiveStarsRotatingProxyService::class)->currentProxyUrl();
            if (is_string($proxy) && $proxy !== '') {
                $pending = $pending->withOptions(['proxy' => $proxy]);
            }
        }

        return $pending;
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
        if ($status === 403 && $err['is_json']) {
            $parts[] = 'Key đúng nhưng thiếu quyền endpoint hoặc tài khoản bị hạn chế';
        } elseif ($status === 403 && ! $err['is_json']) {
            $parts[] = 'Có thể IP VPS bị chặn — thử CS2CAP_USE_PROXY=true hoặc curl trên VPS';
        }
        if ($keyHint !== null) {
            $parts[] = "key DB {$keyHint}";
        }

        return $endpoint.': '.implode(' — ', $parts);
    }
}
