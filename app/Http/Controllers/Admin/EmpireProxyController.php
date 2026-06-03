<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmpireProxySetting;
use App\Services\FiveStarsRotatingProxyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class EmpireProxyController extends Controller
{
    public function edit(): View
    {
        $settings = EmpireProxySetting::current();

        return view('admin.empire-proxy.edit', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
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

        return redirect()
            ->route('admin.empire-proxy.edit')
            ->with('success', 'Đã lưu cấu hình proxy Empire.');
    }

    public function test(FiveStarsRotatingProxyService $proxy): RedirectResponse
    {
        $result = $proxy->testFetch();
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
    }
}
