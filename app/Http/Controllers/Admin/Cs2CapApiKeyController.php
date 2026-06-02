<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Cs2CapApiKeyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    public function destroy(int $cs2capKey): RedirectResponse
    {
        $this->store->delete($cs2capKey);

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

        $base = rtrim((string) config('cs2price.cs2cap_base_url', 'https://api.cs2c.app/v1'), '/');

        $response = Http::timeout(20)->withHeaders([
            'Authorization' => 'Bearer '.(string) $row->api_key,
            'Accept' => 'application/json',
        ])->get("{$base}/account/key");

        $ok = $response->successful();

        $tier = (string) ($response->json('key.effective_rate_requests_per_minute') !== null
            ? ($response->header('X-RateLimit-Tier') ?? '')
            : ($response->header('X-RateLimit-Tier') ?? ''));

        $meta = $response->json('key') ?? null;

        // Lấy remaining từ header quota (nằm trên mọi request market-data; gọi thêm /fx để chắc chắn có header)
        $fx = Http::timeout(20)->withHeaders([
            'Authorization' => 'Bearer '.(string) $row->api_key,
            'Accept' => 'application/json',
        ])->get("{$base}/fx");

        $remaining = $fx->header('X-RateLimit-Remaining');
        $limit = $fx->header('X-RateLimit-Limit');
        $reset = $fx->header('X-RateLimit-Reset');
        $tier = $fx->header('X-RateLimit-Tier') ?? $tier;

        $effectiveRpm = is_array($meta) ? ($meta['effective_rate_requests_per_minute'] ?? null) : null;
        $effectiveQuota = is_array($meta) ? ($meta['effective_quota_requests_per_month'] ?? null) : null;

        $suffix = '';
        if ($tier || $effectiveRpm || $effectiveQuota || $remaining || $limit) {
            $suffix = ' · tier='.($tier ?: '—')
                .' · rpm='.($effectiveRpm ?? '—')
                .' · quota='.($effectiveQuota ?? '—')
                .' · remaining='.($remaining ?? '—')
                .'/' .($limit ?? '—');
        }

        $message = $row->label.': HTTP '.$response->status().' — '.($ok ? 'OK' : 'Lỗi').$suffix;

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'cs2cap_key_id' => $cs2capKey,
                'details' => [
                    'tier' => $tier ?: null,
                    'effective_rpm' => $effectiveRpm,
                    'effective_quota' => $effectiveQuota,
                    'quota_remaining' => is_string($remaining) && ctype_digit($remaining) ? (int) $remaining : $remaining,
                    'quota_limit' => is_string($limit) && ctype_digit($limit) ? (int) $limit : $limit,
                    'quota_reset' => is_string($reset) && ctype_digit($reset) ? (int) $reset : $reset,
                    'account_http_status' => $response->status(),
                    'fx_http_status' => $fx->status(),
                ],
            ]);
        }

        return redirect()
            ->route('admin.buff-accounts.index')
            ->with($ok ? 'success' : 'error', $message);
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

