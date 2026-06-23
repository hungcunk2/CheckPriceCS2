<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cs2CapApiKeyStore;
use App\Support\Cs2CapHttp;
use App\Support\Cs2CapQuotaTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class Cs2CapApiKeyController extends Controller
{
    public function __construct(
        private Cs2CapApiKeyStore $store,
    ) {}

    public function create(): View
    {
        return view('admin.cs2cap-keys.form', [
            'key' => null,
            'nextSortOrder' => $this->store->nextSortOrder(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);

        try {
            $this->store->upsert($validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã thêm API key CS2Cap.');
    }

    public function edit(int $cs2capKey): View|RedirectResponse
    {
        $row = $this->store->find($cs2capKey);
        if (! $row) {
            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy key.');
        }

        return view('admin.cs2cap-keys.form', [
            'key' => $row,
            'nextSortOrder' => null,
        ]);
    }

    public function update(Request $request, int $cs2capKey): RedirectResponse
    {
        $row = $this->store->find($cs2capKey);
        if (! $row) {
            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy key.');
        }

        $validated = $this->validateForm($request, $cs2capKey);

        try {
            $this->store->upsert($validated, $cs2capKey);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã cập nhật API key CS2Cap.');
    }

    public function destroy(Request $request, int $cs2capKey): JsonResponse|RedirectResponse
    {
        $this->store->delete($cs2capKey);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Đã xóa API key CS2Cap.',
                'cs2cap_key_id' => $cs2capKey,
            ]);
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', 'Đã xóa API key CS2Cap.');
    }

    public function importFromEnv(): RedirectResponse
    {
        $count = $this->store->importFromEnvIfEmpty();

        if ($count === 0) {
            return redirect()
                ->route('admin.buff-accounts.index')
                ->with('error', 'Đã có key trong DB hoặc .env trống — không import được.');
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with('success', "Đã import {$count} API key từ .env vào DB.");
    }

    public function probe(Request $request, int $cs2capKey): JsonResponse|RedirectResponse
    {
        $row = $this->store->find($cs2capKey);
        if (! $row) {
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => 'Không tìm thấy key.'], 404);
            }

            return redirect()->route('admin.buff-accounts.index')->with('error', 'Không tìm thấy key.');
        }

        $result = $this->probeCredentials((string) $row->label, (string) $row->api_key);
        $result['cs2cap_key_id'] = $cs2capKey;

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->withFragment('cs2cap-keys')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function probeAll(Request $request): JsonResponse|RedirectResponse
    {
        $keys = $this->store->allWithSecrets();
        if ($keys->isEmpty()) {
            $message = 'Chưa có API key CS2Cap trong DB.';
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message, 'keys' => []], 422);
            }

            return redirect()->route('admin.buff-accounts.index')->withFragment('cs2cap-keys')->with('error', $message);
        }

        $results = [];
        $failed = [];

        foreach ($keys as $row) {
            $probe = $this->probeCredentials((string) $row->label, (string) $row->api_key);
            $probe['cs2cap_key_id'] = (int) $row->id;
            $results[] = $probe;
            if (! $probe['ok']) {
                $failed[] = $probe['message'];
            }
        }

        $total = count($results);
        $failCount = count($failed);
        $allOk = $failCount === 0;

        if ($allOk) {
            $message = "CS2Cap: đã kiểm tra {$total} key — tất cả OK.";
        } else {
            $message = "CS2Cap: đã kiểm tra {$total} key — {$failCount} lỗi: ".implode(' · ', array_slice($failed, 0, 5));
            if ($failCount > 5) {
                $message .= ' …';
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => $allOk,
                'message' => $message,
                'keys' => $results,
            ]);
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->withFragment('cs2cap-keys')
            ->with($allOk ? 'success' : 'error', $message);
    }

    /**
     * @return array{ok: bool, message: string, label: string, details: array<string, mixed>}
     */
    private function probeCredentials(string $label, string $apiKey): array
    {
        $apiKey = Cs2CapHttp::normalizeApiKey($apiKey);
        $keyHint = Cs2CapHttp::maskKey($apiKey);
        if ($apiKey === '') {
            return [
                'ok' => false,
                'message' => $label.': API key trống trong DB',
                'label' => $label,
                'details' => [
                    'account_http_status' => null,
                    'prices_http_status' => null,
                    'effective_quota' => null,
                    'quota_remaining' => null,
                    'quota_limit' => null,
                    'quota_reset' => null,
                    'key_hint' => $keyHint,
                    'api_error_code' => null,
                    'api_error_detail' => null,
                ],
            ];
        }

        $base = Cs2CapHttp::baseUrl();

        $response = Cs2CapHttp::client($apiKey, 20)->get("{$base}/account/key");
        $accountError = self::parseApiError($response);

        $ok = $response->successful();

        $meta = $response->json('key') ?? null;
        $effectiveQuota = is_array($meta) ? ($meta['effective_quota_requests_per_month'] ?? null) : null;
        if (is_int($effectiveQuota) || (is_string($effectiveQuota) && ctype_digit($effectiveQuota))) {
            Cs2CapQuotaTracker::recordEffectiveQuota($label, (int) $effectiveQuota);
        }

        if ($ok) {
            Cs2CapQuotaTracker::acknowledgeValidKey($label);
        }

        $prices = Cs2CapHttp::client($apiKey, 20)->get("{$base}/prices", [
            'market_hash_name' => 'AK-47 | Redline (Field-Tested)',
            'limit' => 1,
        ]);
        $pricesError = self::parseApiError($prices);

        Cs2CapQuotaTracker::recordFromResponse($label, $prices);

        $snapshot = Cs2CapQuotaTracker::snapshot($label);
        $tier = is_array($meta) ? ($meta['tier'] ?? ($snapshot['tier'] ?? null)) : ($snapshot['tier'] ?? null);

        if ($ok) {
            $message = $label.': HTTP '.$response->status().' — OK';
            if (! $prices->successful()) {
                $message .= ' · '.Cs2CapHttp::formatHttpError('/prices', $prices, $keyHint);
                if ($prices->status() === 429) {
                    $message .= ' (giới hạn tạm — key vẫn hợp lệ)';
                }
            }
        } else {
            $message = $label.': '.Cs2CapHttp::formatHttpError('/account/key', $response, $keyHint);
        }

        $details = array_merge([
            'account_http_status' => $response->status(),
            'prices_http_status' => $prices->status(),
            'tier' => $tier,
            'effective_quota' => $effectiveQuota !== null ? (int) $effectiveQuota : null,
            'quota_remaining' => null,
            'quota_limit' => $effectiveQuota !== null ? (int) $effectiveQuota : null,
            'quota_reset' => null,
            'key_hint' => $keyHint,
            'api_error_code' => $accountError['code'] ?? $pricesError['code'] ?? null,
            'api_error_detail' => $accountError['detail'] ?? $pricesError['detail'] ?? null,
            'uses_proxy' => filter_var(config('cs2price.cs2cap_use_proxy', false), FILTER_VALIDATE_BOOL),
        ], $snapshot ?? []);

        return [
            'ok' => $ok,
            'message' => $message,
            'label' => $label,
            'details' => $details,
        ];
    }

    /**
     * @return array{code: string|null, detail: string|null}
     */
    private static function parseApiError(\Illuminate\Http\Client\Response $response): array
    {
        $parsed = Cs2CapHttp::parseApiError($response);

        return [
            'code' => $parsed['code'],
            'detail' => $parsed['detail'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?int $ignoreId = null): array
    {
        $labelRule = ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9\\-]+$/'];

        if ($ignoreId) {
            $labelRule[] = 'unique:cs2cap_api_keys,label,'.$ignoreId;
        } else {
            $labelRule[] = 'unique:cs2cap_api_keys,label';
        }

        $keyRule = $ignoreId
            ? ['nullable', 'string', 'max:512']
            : ['required', 'string', 'max:512'];

        $validated = $request->validate([
            'label' => $labelRule,
            'api_key' => $keyRule,
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }
}

