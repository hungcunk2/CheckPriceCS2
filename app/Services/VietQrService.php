<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VietQrService
{
    private const BANKS_URL = 'https://api.vietqr.io/v2/banks';

    /**
     * @return list<array{bin: string, code: string, short_name: string, name: string, logo: string|null}>
     */
    public function banks(): array
    {
        return Cache::remember('vietqr:banks:v1', 86400, function () {
            $response = Http::timeout(15)->acceptJson()->get(self::BANKS_URL);
            if (! $response->successful()) {
                return [];
            }

            $payload = $response->json('data');
            if (! is_array($payload)) {
                return [];
            }

            $banks = [];
            foreach ($payload as $row) {
                if (! is_array($row) || empty($row['bin'])) {
                    continue;
                }
                $banks[] = [
                    'bin' => (string) $row['bin'],
                    'code' => (string) ($row['code'] ?? ''),
                    'short_name' => (string) ($row['short_name'] ?? ''),
                    'name' => (string) ($row['name'] ?? $row['short_name'] ?? ''),
                    'logo' => isset($row['logo']) ? (string) $row['logo'] : null,
                ];
            }

            usort($banks, fn (array $a, array $b) => strcmp($a['short_name'], $b['short_name']));

            return $banks;
        });
    }

    public function clearBanksCache(): void
    {
        Cache::forget('vietqr:banks:v1');
    }
}
