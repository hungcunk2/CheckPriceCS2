@extends('layouts.landing')

@section('content')
@include('landing.nav')
@include('partials.flash-alerts')

@php
    $cycleLabels = [
        1 => '1 tháng',
        3 => '3 tháng',
        6 => '6 tháng',
    ];
    $savings = [3 => 'Tiết kiệm 5%', 6 => 'Tiết kiệm 10%'];
@endphp

<section class="lp-pricing-page lp-checkout-page">
    <div class="lp-container">
        <a href="{{ route('public.pricing') }}" class="lp-checkout-back">
            <i class="fas fa-arrow-left"></i> Quay lại bảng giá
        </a>

        <div class="lp-pricing-header">
            <span class="lp-pricing-badge">Thanh toán</span>
            <h1 class="lp-pricing-title">Hoàn tất gói {{ $plan['name'] }}</h1>
            <p class="lp-pricing-subtitle">
                Chuyển khoản ngân hàng — admin duyệt sau khi đối soát (thường trong 24h).
            </p>
        </div>

        <div class="lp-checkout-layout">
            <div class="lp-pricing-card lp-checkout-summary">
                <div class="lp-pricing-plan">{{ $plan['name'] }}</div>
                <div class="lp-pricing-price">
                    <span class="lp-pricing-price-num" id="checkoutTotal">{{ \App\Support\SubscriptionPlans::formatVnd($amount) }}</span>
                </div>
                <div class="lp-pricing-tagline">{{ $plan['slots'] }}</div>
                <div class="lp-pricing-divider"></div>
                <ul class="lp-pricing-features">
                    @foreach($plan['features'] as $feature)
                        <li><span class="lp-pricing-check">✓</span> {{ $feature }}</li>
                    @endforeach
                </ul>
                <p class="lp-checkout-summary-hint small text-muted mb-0">Tổng thanh toán theo chu kỳ bên phải.</p>
            </div>

            <div class="lp-pricing-card lp-checkout-payment">
                <div class="lp-pricing-plan">Thông tin thanh toán</div>
                <div class="lp-pricing-divider"></div>

                @if($checkoutUser)
                    <p class="lp-checkout-user">
                        Tài khoản: <strong>{{ $checkoutUser->name }}</strong> — {{ $checkoutUser->email }}
                    </p>
                @else
                    <p class="lp-checkout-user">
                        Dùng email đã <a href="{{ route('login', ['mode' => 'register']) }}">đăng ký</a> trên site.
                    </p>
                @endif

                <form method="POST" action="{{ route('public.checkout.submit') }}" id="checkoutForm">
                    @csrf
                    <input type="hidden" name="plan" value="{{ $planKey }}">
                    @if($checkoutUser)
                        <input type="hidden" name="email" value="{{ $checkoutUser->email }}">
                    @else
                        <label class="lp-checkout-label" for="checkout_email">Email tài khoản</label>
                        <input type="email" name="email" id="checkout_email" class="lp-checkout-input" required
                               value="{{ old('email', request('email')) }}" placeholder="email@ban.com">
                        @error('email')<p class="text-danger small mb-2">{{ $message }}</p>@enderror
                    @endif

                    <p class="lp-checkout-label mt-3 mb-2">Chu kỳ thanh toán</p>
                    <div class="lp-checkout-cycles" role="group" aria-label="Chu kỳ thanh toán">
                        @foreach(\App\Support\SubscriptionPlans::CYCLES as $cycle)
                            @php $cyclePrice = $plan['prices'][$cycle]; @endphp
                            <div class="lp-checkout-cycle">
                                <input type="radio" name="months" id="months{{ $cycle }}" value="{{ $cycle }}"
                                       data-price="{{ $cyclePrice }}"
                                       @checked($months === $cycle)>
                                <label for="months{{ $cycle }}">
                                    <span class="lp-checkout-cycle-title">{{ $cycleLabels[$cycle] }}</span>
                                    <span class="lp-checkout-cycle-price">{{ \App\Support\SubscriptionPlans::formatVnd($cyclePrice) }}</span>
                                    @if(isset($savings[$cycle]))
                                        <span class="lp-checkout-cycle-save">{{ $savings[$cycle] }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="lp-checkout-bank">
                        <div class="lp-checkout-bank-row">
                            <span class="lp-checkout-bank-label">Ngân hàng</span>
                            <span class="lp-checkout-bank-value">{{ $payment['bank_name'] ?: '—' }}</span>
                        </div>
                        <div class="lp-checkout-bank-row">
                            <span class="lp-checkout-bank-label">Số tài khoản</span>
                            <span class="lp-checkout-bank-value">{{ $payment['account_number'] ?: 'Liên hệ admin' }}</span>
                        </div>
                        <div class="lp-checkout-bank-row">
                            <span class="lp-checkout-bank-label">Chủ tài khoản</span>
                            <span class="lp-checkout-bank-value">{{ $payment['account_holder'] ?: '—' }}</span>
                        </div>
                        <div class="lp-checkout-bank-row">
                            <span class="lp-checkout-bank-label">Số tiền</span>
                            <span class="lp-checkout-bank-value" id="checkoutBankAmount">{{ \App\Support\SubscriptionPlans::formatVnd($amount) }}</span>
                        </div>
                    </div>

                    <p class="lp-checkout-label">Nội dung chuyển khoản <span class="text-muted">(bắt buộc đúng)</span></p>
                    <p class="lp-checkout-ref-example small text-muted mb-2">
                        Ví dụ: <code>trantuanhungpro3</code> — email <code>trantuanhung@…</code>, gói Pro, 3 tháng.
                    </p>
                    <div class="lp-checkout-ref-box">
                        @if($reference)
                            <code class="lp-checkout-ref-code" id="checkoutRef">{{ $reference }}</code>
                            <button type="button" class="lp-checkout-copy" data-copy-target="checkoutRef">Sao chép</button>
                        @else
                            <code class="lp-checkout-ref-code" id="checkoutRef">Nhập email để hiện mã</code>
                        @endif
                    </div>

                    <label class="lp-checkout-label mt-3" for="member_note">Ghi chú (tuỳ chọn)</label>
                    <textarea name="member_note" id="member_note" class="lp-checkout-input" rows="2"
                              placeholder="VD: Đã chuyển lúc 14:30, tên TK người gửi...">{{ old('member_note') }}</textarea>

                    <button type="submit" class="lp-pricing-btn is-primary mt-3">Tôi đã chuyển khoản</button>
                </form>

                <p class="lp-pricing-note mt-3 mb-0">
                    Sau khi xác nhận, đơn chờ admin duyệt. <a href="{{ route('public.pricing') }}">Xem bảng giá</a>.
                </p>
            </div>
        </div>
    </div>
</section>

@include('landing.footer')
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pricing.css') }}?v={{ @filemtime(public_path('css/pricing.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/checkout.css') }}?v={{ @filemtime(public_path('css/checkout.css')) ?: 1 }}">
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('checkoutForm');
    const totalEl = document.getElementById('checkoutTotal');
    const bankAmountEl = document.getElementById('checkoutBankAmount');
    const planKey = @json($planKey);
    const checkoutEmail = @json($checkoutUser?->email ?? old('email', request('email')));

    function formatVnd(n) {
        return new Intl.NumberFormat('vi-VN').format(n) + 'đ';
    }

    function emailLocal(email) {
        var part = (String(email || '').split('@')[0] || '').toLowerCase();
        return part.replace(/[^a-z0-9]/g, '');
    }

    function buildTransferRef(email, months) {
        var local = emailLocal(email);
        return local ? (local + planKey + months) : '';
    }

    function currentCheckoutEmail() {
        var hidden = form.querySelector('input[name="email"][type="hidden"]');
        if (hidden && hidden.value) return hidden.value;
        var field = document.getElementById('checkout_email');
        return field ? field.value : checkoutEmail;
    }

    function updateTransferRef(months) {
        var ref = buildTransferRef(currentCheckoutEmail(), months);
        var el = document.getElementById('checkoutRef');
        if (ref) el.textContent = ref;
    }

    function updateFromCycle() {
        const checked = form.querySelector('input[name="months"]:checked');
        if (!checked) return;
        const months = parseInt(checked.value, 10);
        const price = parseInt(checked.dataset.price, 10);
        totalEl.textContent = formatVnd(price);
        bankAmountEl.textContent = formatVnd(price);
        updateTransferRef(months);
        const url = new URL(window.location.href);
        url.searchParams.set('months', months);
        window.history.replaceState({}, '', url);
    }

    form.querySelectorAll('input[name="months"]').forEach(function (el) {
        el.addEventListener('change', updateFromCycle);
    });

    var emailField = document.getElementById('checkout_email');
    if (emailField) {
        emailField.addEventListener('input', function () {
            var checked = form.querySelector('input[name="months"]:checked');
            if (checked) updateTransferRef(parseInt(checked.value, 10));
        });
    }

    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-copy-target');
            const text = document.getElementById(id).textContent.trim();
            navigator.clipboard.writeText(text).then(function () {
                btn.textContent = 'Đã copy';
                setTimeout(function () { btn.textContent = 'Sao chép'; }, 2000);
            });
        });
    });
})();
</script>
@endpush
