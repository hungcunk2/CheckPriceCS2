<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpireProxySetting;
use App\Services\FiveStarsRotatingProxyService;
use App\Support\AdminFacingError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class EmpireProxyController extends Controller
{
    private function missingTableRedirect(): ?RedirectResponse
    {
        if (Schema::hasTable('empire_proxy_settings')) {
            return null;
        }

        return redirect()
            ->route('admin.inventories.index')
            ->with('error', 'Thiếu bảng empire_proxy_settings. Trên VPS chạy: php artisan migrate --force');
    }

    public function edit(): View|RedirectResponse
    {
        if ($redirect = $this->missingTableRedirect()) {
            return $redirect;
        }

        $settings = EmpireProxySetting::current();

        return view('admin.empire-proxy.edit', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if ($redirect = $this->missingTableRedirect()) {
            return $redirect;
        }

        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'rotation_key' => ['nullable', 'string', 'max:128'],
            'nhamang' => ['nullable', 'string', 'max:32'],
            'tinhthanh' => ['nullable', 'string', 'max:8'],
            'whitelist_ip' => ['nullable', 'string', 'max:45'],
            'use_socks5' => ['sometimes', 'boolean'],
        ]);

        $settings = EmpireProxySetting::current();
        $settings->update([
            'enabled' => $request->boolean('enabled'),
            'rotation_key' => trim((string) ($validated['rotation_key'] ?? '')) ?: null,
            'nhamang' => trim((string) ($validated['nhamang'] ?? 'Random')) ?: 'Random',
            'tinhthanh' => trim((string) ($validated['tinhthanh'] ?? '0')) ?: '0',
            'whitelist_ip' => trim((string) ($validated['whitelist_ip'] ?? '')) ?: null,
            'use_socks5' => $request->boolean('use_socks5'),
        ]);

        Cache::forget('fivestars_rotating_proxy:url');
        Cache::forget('fivestars_rotating_proxy:last_fetch_at');
        Cache::forget('fivestars_rotating_proxy:wait_until');

        return redirect()
            ->route('admin.empire-proxy.edit')
            ->with('success', 'Đã lưu cấu hình proxy Empire.');
    }

    public function test(FiveStarsRotatingProxyService $proxy): RedirectResponse
    {
        if ($redirect = $this->missingTableRedirect()) {
            return $redirect;
        }

        try {
            $result = $proxy->testFetch();
            if ($result['ok'] && $result['proxy_url'] && empty($result['throttled'])) {
                Cache::put('fivestars_rotating_proxy:last_fetch_at', time(), 86400);
            }
            $settings = EmpireProxySetting::current();
            $settings->update([
                'last_tested_at' => now(),
                'last_test_message' => ($result['ok'] ? 'OK: ' : 'Lỗi: ').($result['message'] ?? '')
                    .($result['proxy_url'] ? ' — '.$result['proxy_url'] : ''),
            ]);

            if ($result['ok'] && $result['proxy_url']) {
                $proxy->rememberProxy($result['proxy_url'], (string) ($result['message'] ?? ''));
            }

            return redirect()
                ->route('admin.empire-proxy.edit')
                ->with($result['ok'] ? 'success' : 'error', $settings->last_test_message);
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.empire-proxy.edit')
                ->with('error', AdminFacingError::message($e, 'Lỗi kiểm tra proxy: '.$e->getMessage()));
        }
    }
}
