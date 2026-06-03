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

<section class="lp-checkout-page">
    <div class="lp-container">
        <a href="{{ route('public.pricing') }}" class="lp-checkout-back">
            <i class="fas fa-arrow-left"></i> Quay lại bảng giá
        </a>

        <div class="lp-checkout-header">
            <span class="lp-checkout-badge">Thanh toán</span>
            <h1 class="lp-checkout-title">Hoàn tất đăng ký gói {{ $plan['name'] }}</h1>
            <p class="lp-checkout-subtitle">Chuyển khoản ngân hàng — admin duyệt tay sau khi đối soát (thường trong 24h).</p>
        </div>

        <div class="lp-checkout-layout">
            <div class="lp-checkout-card">
                <h2>Tóm tắt đơn hàng</h2>
                <div class="lp-checkout-plan-name">{{ $plan['name'] }}</div>
                <div class="lp-checkout-plan-slots">{{ $plan['slots'] }}</div>
                <ul class="lp-checkout-features">
                    @foreach($plan['features'] as $feature)
                        <li><span class="check">✓</span> {{ $feature }}</li>
                    @endforeach
                </ul>
                <div class="lp-checkout-total-row">
                    <span class="lp-checkout-total-label">Tổng thanh toán</span>
                    <span class="lp-checkout-total-amount" id="checkoutTotal">{{ \App\Support\SubscriptionPlans::formatVnd($amount) }}</span>
                </div>
            </div>

            <div class="lp-checkout-card">
                <h2>Thông tin thanh toán</h2>
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
                        <label class="lp-checkout-ref-label" for="checkout_email">Email tài khoản</label>
                        <input type="email" name="email" id="checkout_email" class="lp-checkout-note mb-3" required
                               value="{{ old('email', request('email')) }}" placeholder="email@ban.com">
                        @error('email')<p class="text-danger small mb-2">{{ $message }}</p>@enderror
                    @endif

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

                    <div class="lp-checkout-ref">
                        <div class="lp-checkout-ref-label">Nội dung chuyển khoản (bắt buộc đúng)</div>
                        <div class="lp-checkout-ref-box">
                            @if($reference)
                                <code class="lp-checkout-ref-code" id="checkoutRef">{{ $reference }}</code>
                                <button type="button" class="lp-checkout-copy" data-copy-target="checkoutRef">Sao chép</button>
                            @else
                                <code class="lp-checkout-ref-code" id="checkoutRef">Nhập email tài khoản và tải lại trang để lấy mã</code>
                            @endif
                        </div>
                    </div>

                    <label class="lp-checkout-ref-label" for="member_note">Ghi chú (tuỳ chọn)</label>
                    <textarea name="member_note" id="member_note" class="lp-checkout-note" rows="2"
                              placeholder="VD: Đã chuyển lúc 14:30 ngày 03/06, tên TK người gửi...">{{ old('member_note') }}</textarea>

                    <button type="submit" class="lp-checkout-submit">Tôi đã chuyển khoản</button>
                </form>

                <p class="lp-checkout-hint">
                    Sau khi bấm xác nhận, đơn chờ admin duyệt. Cần hỗ trợ? Xem
                    <a href="{{ route('public.pricing') }}">bảng giá</a> hoặc liên hệ qua kênh hỗ trợ của site.
                </p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/checkout.css') }}?v={{ @filemtime(public_path('css/checkout.css')) ?: 1 }}">
@endpush

@push('scripts')
<script>
(function () {
    const form = document.getElementById('checkoutForm');
    const totalEl = document.getElementById('checkoutTotal');
    const bankAmountEl = document.getElementById('checkoutBankAmount');
    const planKey = @json($planKey);
    const prefix = @json(strtoupper(config('cs2price.payment.transfer_prefix', 'CPCS2')));
    const userId = @json($checkoutUser?->id);

    function formatVnd(n) {
        return new Intl.NumberFormat('vi-VN').format(n) + 'đ';
    }

    function updateFromCycle() {
        const checked = form.querySelector('input[name="months"]:checked');
        if (!checked) return;
        const months = parseInt(checked.value, 10);
        const price = parseInt(checked.dataset.price, 10);
        totalEl.textContent = formatVnd(price);
        bankAmountEl.textContent = formatVnd(price);
        if (userId) {
            document.getElementById('checkoutRef').textContent =
                prefix + '-' + userId + '-' + planKey.toUpperCase() + '-' + months;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('months', months);
        window.history.replaceState({}, '', url);
    }

    form.querySelectorAll('input[name="months"]').forEach(function (el) {
        el.addEventListener('change', updateFromCycle);
    });

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
