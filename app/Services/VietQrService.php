<?php

namespace App\Services;

use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VietQrService
{
    private const BANKS_URL = 'https://api.vietqr.io/v2/banks';

    private const GENERATE_URL = 'https://api.vietqr.io/v2/generate';

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

    /**
     * @return array{ok: bool, message: string, qr_data_url: string|null}
     */
    public function generateViaApi(
        PaymentSetting $settings,
        ?int $amount = null,
        ?string $addInfo = null
    ): array {
        if (! $settings->hasVietQrApiCredentials()) {
            return [
                'ok' => false,
                'message' => 'Chưa nhập Client ID / API Key VietQR.',
                'qr_data_url' => null,
            ];
        }

        if (! $settings->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Chưa cấu hình đủ ngân hàng, STK và tên chủ tài khoản.',
                'qr_data_url' => null,
            ];
        }

        $body = [
            'accountNo' => preg_replace('/\s+/', '', (string) $settings->account_number),
            'accountName' => $settings->vietqrAccountName(),
            'acqId' => (string) $settings->bank_bin,
            'template' => $settings->qr_template ?: 'compact',
        ];

        if ($amount > 0) {
            $body['amount'] = (string) $amount;
        }
        if ($addInfo !== null && $addInfo !== '') {
            $body['addInfo'] = mb_substr($addInfo, 0, 25);
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'x-client-id' => (string) $settings->vietqr_client_id,
                'x-api-key' => (string) $settings->vietqr_api_key,
            ])
            ->post(self::GENERATE_URL, $body);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'VietQR API lỗi HTTP '.$response->status(),
                'qr_data_url' => null,
            ];
        }

        $code = (string) $response->json('code', '');
        if ($code !== '00') {
            return [
                'ok' => false,
                'message' => (string) ($response->json('desc') ?: 'VietQR từ chối tạo mã.'),
                'qr_data_url' => null,
            ];
        }

        $qr = $response->json('data.qrDataURL') ?? $response->json('data.qrDataUrl');

        return [
            'ok' => is_string($qr) && $qr !== '',
            'message' => is_string($qr) && $qr !== '' ? 'Tạo mã QR thành công (API).' : 'Không nhận được ảnh QR.',
            'qr_data_url' => is_string($qr) ? $qr : null,
        ];
    }

    public function clearBanksCache(): void
    {
        Cache::forget('vietqr:banks:v1');
    }
}
