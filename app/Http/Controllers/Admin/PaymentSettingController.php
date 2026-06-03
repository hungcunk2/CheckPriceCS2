<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Services\VietQrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentSettingController extends Controller
{
    public function edit(VietQrService $vietQr): View
    {
        $settings = PaymentSetting::current();
        $banks = $vietQr->banks();

        return view('admin.payment-settings.edit', [
            'settings' => $settings,
            'banks' => $banks,
            'previewUrl' => $settings->quickLinkImageUrl(100_000, 'TESTCHECKPRICE'),
        ]);
    }

    public function update(Request $request, VietQrService $vietQr): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'bank_bin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'bank_display_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'min:6', 'max:32'],
            'account_holder' => ['required', 'string', 'max:80'],
            'qr_template' => ['required', 'string', 'in:'.implode(',', PaymentSetting::QR_TEMPLATES)],
            'vietqr_client_id' => ['nullable', 'string', 'max:64'],
            'vietqr_api_key' => ['nullable', 'string', 'max:128'],
        ]);

        $settings = PaymentSetting::current();
        $apiKey = trim((string) ($validated['vietqr_api_key'] ?? ''));
        if ($apiKey === '' && $settings->vietqr_api_key) {
            $apiKey = $settings->vietqr_api_key;
        }

        $settings->update([
            'enabled' => $request->boolean('enabled'),
            'bank_bin' => $validated['bank_bin'],
            'bank_code' => trim((string) ($validated['bank_code'] ?? '')) ?: null,
            'bank_display_name' => trim($validated['bank_display_name']),
            'account_number' => preg_replace('/\s+/', '', trim($validated['account_number'])),
            'account_holder' => trim($validated['account_holder']),
            'qr_template' => $validated['qr_template'],
            'vietqr_client_id' => trim((string) ($validated['vietqr_client_id'] ?? '')) ?: null,
            'vietqr_api_key' => $apiKey !== '' ? $apiKey : null,
        ]);

        return redirect()
            ->route('admin.payment-settings.edit')
            ->with('success', 'Đã lưu cấu hình thanh toán ngân hàng.');
    }

    public function importFromEnv(): RedirectResponse
    {
        $legacy = config('cs2price.payment', []);
        $settings = PaymentSetting::current();
        $settings->update([
            'bank_display_name' => (string) ($legacy['bank_name'] ?? $settings->bank_display_name),
            'account_number' => (string) ($legacy['account_number'] ?? $settings->account_number),
            'account_holder' => (string) ($legacy['account_holder'] ?? $settings->account_holder),
        ]);

        return redirect()
            ->route('admin.payment-settings.edit')
            ->with('success', 'Đã nhập STK/tên từ .env (nếu có). Chọn ngân hàng (BIN) và lưu lại.');
    }

    public function refreshBanks(VietQrService $vietQr): RedirectResponse
    {
        $vietQr->clearBanksCache();

        return redirect()
            ->route('admin.payment-settings.edit')
            ->with('success', 'Đã làm mới danh sách ngân hàng VietQR.');
    }

    public function testQr(Request $request, VietQrService $vietQr): RedirectResponse
    {
        $settings = PaymentSetting::current();
        $result = $vietQr->generateViaApi($settings, 100_000, 'TESTAPI');

        if ($result['ok']) {
            return redirect()
                ->route('admin.payment-settings.edit')
                ->with('success', $result['message'])
                ->with('test_qr_data_url', $result['qr_data_url']);
        }

        return redirect()
            ->route('admin.payment-settings.edit')
            ->with('error', $result['message']);
    }
}
