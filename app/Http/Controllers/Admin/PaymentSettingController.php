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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'bank_bin' => ['required', 'string', 'regex:/^\d{6}$/'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'bank_display_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'min:6', 'max:32'],
            'account_holder' => ['required', 'string', 'max:80'],
            'qr_template' => ['required', 'string', 'in:'.implode(',', PaymentSetting::QR_TEMPLATES)],
        ]);

        $settings = PaymentSetting::current();
        $settings->forgetStaticQrCache();
        $settings->update([
            'enabled' => $request->boolean('enabled'),
            'bank_bin' => $validated['bank_bin'],
            'bank_code' => trim((string) ($validated['bank_code'] ?? '')) ?: null,
            'bank_display_name' => trim($validated['bank_display_name']),
            'account_number' => preg_replace('/\s+/', '', trim($validated['account_number'])),
            'account_holder' => trim($validated['account_holder']),
            'qr_template' => $validated['qr_template'],
        ]);
        $settings->forgetStaticQrCache();

        return redirect()
            ->route('admin.payment-settings.edit')
            ->with('success', 'Đã lưu cấu hình thanh toán ngân hàng.');
    }

    public function refreshBanks(VietQrService $vietQr): RedirectResponse
    {
        $vietQr->clearBanksCache();

        return redirect()
            ->route('admin.payment-settings.edit')
            ->with('success', 'Đã làm mới danh sách ngân hàng VietQR.');
    }

}
