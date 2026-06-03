@extends('layouts.admin')

@section('title', 'Thanh toán ngân hàng')
@section('page-title', 'Cài đặt thanh toán')

@section('content')
<div class="mb-3">
    <p class="small text-muted mb-0">
        Nhập ngân hàng, STK và tên chủ tài khoản. Trang <code>/thanh-toan</code> tự tạo mã VietQR
        (số tiền + nội dung CK theo email/gói/tháng).
    </p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="col-lg-8 px-0">
    <div class="panel-admin rounded border p-4 mb-3">
        <form method="POST" action="{{ route('admin.payment-settings.update') }}">
            @csrf
            @method('PUT')

            <div class="mb-3 form-check form-switch">
                <input type="checkbox" name="enabled" value="1" class="form-check-input" id="pay_enabled"
                       @checked(old('enabled', $settings->enabled))>
                <label class="form-check-label" for="pay_enabled">Hiển thị thanh toán chuyển khoản trên /thanh-toan</label>
            </div>

            <div class="mb-3">
                <label class="form-label">Ngân hàng <span class="text-danger">*</span></label>
                @if(count($banks) === 0)
                    <div class="alert alert-warning py-2 small mb-2">
                        Không tải được danh sách ngân hàng. Bấm «Làm mới danh sách NH» bên dưới.
                    </div>
                @endif
                <select name="bank_bin" id="bank_bin" class="form-select" required>
                    <option value="">— Chọn ngân hàng —</option>
                    @foreach($banks as $bank)
                        <option value="{{ $bank['bin'] }}"
                                data-code="{{ $bank['code'] }}"
                                data-name="{{ $bank['short_name'] }}"
                                @selected(old('bank_bin', $settings->bank_bin) === $bank['bin'])>
                            {{ $bank['short_name'] }} (BIN {{ $bank['bin'] }}) — {{ $bank['name'] }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="bank_code" id="bank_code" value="{{ old('bank_code', $settings->bank_code) }}">
                @error('bank_bin')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Tên hiển thị</label>
                <input type="text" name="bank_display_name" id="bank_display_name" class="form-control"
                       value="{{ old('bank_display_name', $settings->bank_display_name) }}"
                       placeholder="VD: Vietcombank" required>
                @error('bank_display_name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Số tài khoản <span class="text-danger">*</span></label>
                    <input type="text" name="account_number" class="form-control font-monospace"
                           value="{{ old('account_number', $settings->account_number) }}"
                           placeholder="1023456789" required autocomplete="off">
                    @error('account_number')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tên chủ tài khoản <span class="text-danger">*</span></label>
                    <input type="text" name="account_holder" class="form-control"
                           value="{{ old('account_holder', $settings->account_holder) }}"
                           placeholder="NGUYEN VAN A" required>
                    <div class="form-text">Không dấu, viết hoa.</div>
                    @error('account_holder')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Mẫu QR</label>
                <select name="qr_template" class="form-select">
                    @foreach(\App\Models\PaymentSetting::QR_TEMPLATES as $tpl)
                        <option value="{{ $tpl }}" @selected(old('qr_template', $settings->qr_template) === $tpl)>{{ $tpl }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
        </form>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.payment-settings.import-env') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Nhập STK/tên từ .env</button>
        </form>
        <form method="POST" action="{{ route('admin.payment-settings.refresh-banks') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Làm mới danh sách NH</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const sel = document.getElementById('bank_bin');
    const codeInput = document.getElementById('bank_code');
    const nameInput = document.getElementById('bank_display_name');
    if (!sel) return;

    function syncBank() {
        const opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return;
        if (codeInput) codeInput.value = opt.dataset.code || '';
        if (nameInput && (!nameInput.value || nameInput.dataset.auto === '1')) {
            nameInput.value = opt.dataset.name || '';
            nameInput.dataset.auto = '1';
        }
    }

    nameInput?.addEventListener('input', function () {
        nameInput.dataset.auto = '0';
    });

    sel.addEventListener('change', syncBank);
})();
</script>
@endpush
