@extends('layouts.landing')

@section('content')
@include('landing.nav')

@php
    $cycleKey = match ($months) {
        3 => '3m',
        6 => '6m',
        default => '1m',
    };
    $initialEmail = $checkoutUser?->email ?? old('email', request('email'));
@endphp

<section class="cp-checkout-v3">
    <div class="container">
        <header class="header">
            <a href="{{ route('public.pricing') }}" class="back">← Quay lại bảng giá</a>
            <span class="badge">Thanh toán</span>
            <h1 class="cp-title">Hoàn tất đăng ký</h1>
            <p class="subtitle">Chỉ vài bước để kích hoạt gói của bạn.</p>
        </header>

        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert-success" style="border-color:rgba(248,113,113,0.4);color:#fca5a5;background:rgba(248,113,113,0.08);">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('public.checkout.submit') }}" id="checkoutForm">
            @csrf
            <input type="hidden" name="plan" id="input_plan" value="{{ $planKey }}">
            <input type="hidden" name="months" id="input_months" value="{{ $months }}">
            <input type="hidden" name="payment_method" id="input_method" value="bank">

            <div class="layout">
                <div class="main">
                    <section class="card">
                        <div class="step">
                            <span class="step-num">1</span>
                            <h2>Chọn gói</h2>
                        </div>
                        <div class="plan-grid" id="planGrid">
                            @foreach(\App\Support\SubscriptionPlans::PLANS as $key => $p)
                                <button type="button" class="plan-pick{{ $planKey === $key ? ' active' : '' }}"
                                        data-plan="{{ $key }}">
                                    <div class="plan-pick-name">{{ $p['name'] }}</div>
                                    <div class="plan-pick-price">{{ number_format($p['prices'][1], 0, ',', '.') }}<span>đ/th</span></div>
                                    <div class="plan-pick-slots">{{ $p['slots'] }}</div>
                                </button>
                            @endforeach
                        </div>
                    </section>

                    <section class="card">
                        <div class="step">
                            <span class="step-num">2</span>
                            <h2>Chu kỳ thanh toán</h2>
                        </div>
                        <div class="cycle-grid" id="cycleGrid">
                            <button type="button" class="cycle-pick{{ $cycleKey === '1m' ? ' active' : '' }}" data-cycle="1m">
                                <div class="cycle-label">1 tháng</div>
                                <div class="cycle-price" id="price-1m">—</div>
                            </button>
                            <button type="button" class="cycle-pick{{ $cycleKey === '3m' ? ' active' : '' }}" data-cycle="3m">
                                <div class="cycle-label">3 tháng</div>
                                <div class="cycle-price" id="price-3m">—</div>
                                <div class="cycle-save">Tiết kiệm 5%</div>
                            </button>
                            <button type="button" class="cycle-pick{{ $cycleKey === '6m' ? ' active' : '' }}" data-cycle="6m">
                                <div class="cycle-label">6 tháng</div>
                                <div class="cycle-price" id="price-6m">—</div>
                                <div class="cycle-save">Tiết kiệm 10%</div>
                            </button>
                        </div>
                    </section>

                    <section class="card">
                        <div class="step">
                            <span class="step-num">3</span>
                            <h2>Thông tin liên hệ</h2>
                        </div>
                        @if($checkoutUser)
                            <p style="color:#94a3b8;font-size:14px;margin:0 0 10px;">
                                Tài khoản: <strong style="color:#f8fafc">{{ $checkoutUser->email }}</strong>
                            </p>
                            <input type="hidden" name="email" id="checkout_email" value="{{ $checkoutUser->email }}">
                        @else
                            <label class="field">
                                <span>Email đã đăng ký trên site</span>
                                <input type="email" name="email" id="checkout_email" required
                                       value="{{ $initialEmail }}" placeholder="ban@example.com"
                                       autocomplete="email">
                            </label>
                            @error('email')<p class="field-error">{{ $message }}</p>@enderror
                            <p style="color:#64748b;font-size:12px;margin-top:8px;">
                                Chưa có tài khoản? <a href="{{ route('login', ['mode' => 'register']) }}" style="color:#60a5fa">Đăng ký</a>
                            </p>
                        @endif
                        <label class="field" style="margin-top:14px">
                            <span>Ghi chú (tuỳ chọn)</span>
                            <input type="text" name="member_note" value="{{ old('member_note') }}"
                                   placeholder="VD: Đã CK lúc 14:30, tên người gửi...">
                        </label>
                    </section>

                    <section class="card">
                        <div class="step">
                            <span class="step-num">4</span>
                            <h2>Phương thức thanh toán</h2>
                        </div>
                        <div class="method-list" id="methodList">
                            <button type="button" class="method is-disabled" data-method="momo" disabled>
                                <div class="method-icon">M</div>
                                <div class="method-text">
                                    <div class="method-label">MoMo</div>
                                    <div class="method-desc">Đang phát triển</div>
                                </div>
                                <div class="radio"><div class="radio-inner"></div></div>
                            </button>
                            <button type="button" class="method active" data-method="bank">
                                <div class="method-icon">🏦</div>
                                <div class="method-text">
                                    <div class="method-label">Chuyển khoản ngân hàng</div>
                                    <div class="method-desc">Admin duyệt trong 24h</div>
                                </div>
                                <div class="radio"><div class="radio-inner"></div></div>
                            </button>
                            <button type="button" class="method is-disabled" data-method="card" disabled>
                                <div class="method-icon">💳</div>
                                <div class="method-text">
                                    <div class="method-label">Thẻ Visa / Mastercard</div>
                                    <div class="method-desc">Đang phát triển</div>
                                </div>
                                <div class="radio"><div class="radio-inner"></div></div>
                            </button>
                        </div>

                        @if(session('success'))
                            <div class="bank-panel" id="bankPanel">
                                <p><strong>Chuyển khoản theo thông tin sau:</strong></p>
                                <p>Ngân hàng: {{ $payment['bank_name'] ?: '—' }}</p>
                                <p>Số TK: {{ $payment['account_number'] ?: 'Liên hệ admin' }}</p>
                                <p>Chủ TK: {{ $payment['account_holder'] ?: '—' }}</p>
                                <p>Số tiền: <strong id="bankAmount">—</strong></p>
                                <p>Nội dung CK: <strong id="bankRef">—</strong></p>
                                <p style="margin-top:8px;color:#64748b;font-size:12px;">
                                    Mã CK = phần email trước @ + gói + tháng (vd. trantuanhungpro3)
                                </p>
                            </div>
                        @endif
                    </section>
                </div>

                <aside class="summary">
                    <div class="summary-inner">
                        <h3>Đơn hàng</h3>
                        <div class="row">
                            <div>
                                <div class="row-label" id="summary-plan-name">Gói {{ $plan['name'] }}</div>
                                <div class="row-sub" id="summary-plan-slots">{{ $plan['slots'] }}</div>
                            </div>
                            <div class="row-val" id="summary-plan-price">—</div>
                        </div>
                        <div class="row">
                            <div>
                                <div class="row-label">Chu kỳ</div>
                                <div class="row-sub" id="summary-cycle-label">—</div>
                            </div>
                            <div class="row-val" id="summary-cycle-mult">—</div>
                        </div>
                        <div class="divider"></div>
                        <div class="row">
                            <div class="row-label">Tạm tính</div>
                            <div class="row-val" id="summary-subtotal">—</div>
                        </div>
                        <div class="row" id="summary-discount-row" style="display:none">
                            <div class="row-label" style="color:#f97316" id="summary-discount-label">Giảm giá</div>
                            <div class="row-val" style="color:#f97316" id="summary-discount">—</div>
                        </div>
                        <div class="divider"></div>
                        <div class="row total">
                            <div>Tổng cộng</div>
                            <div class="total-num" id="summary-total">—</div>
                        </div>
                        <button type="submit" class="pay-btn" id="pay-btn" disabled>
                            Thanh toán
                        </button>
                        <p class="secure">🔒 Chuyển khoản an toàn · Admin duyệt tay</p>
                    </div>
                </aside>
            </div>
        </form>
    </div>
</section>

@include('landing.footer')
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/checkout.css') }}?v={{ @filemtime(public_path('css/checkout.css')) ?: 1 }}">
@endpush

@push('scripts')
<script>
(function () {
    const planData = @json($plansJson);
    const cycles = {
        '1m': { label: '1 tháng', months: 1 },
        '3m': { label: '3 tháng', months: 3, savePct: 5 },
        '6m': { label: '6 tháng', months: 6, savePct: 10 },
    };
    const cycleToMonths = { '1m': 1, '3m': 3, '6m': 6 };
    const monthsToCycle = { 1: '1m', 3: '3m', 6: '6m' };

    let state = {
        plan: @json($planKey),
        cycle: @json($cycleKey),
        method: 'bank',
        email: @json($initialEmail ?? ''),
    };

    const form = document.getElementById('checkoutForm');
    const payBtn = document.getElementById('pay-btn');
    const emailInput = document.getElementById('checkout_email');

    function fmt(n) {
        return new Intl.NumberFormat('vi-VN').format(n) + 'đ';
    }

    function emailLocal(email) {
        return (String(email || '').split('@')[0] || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function buildRef(email, plan, months) {
        const local = emailLocal(email);
        return local ? (local + plan + months) : '';
    }

    function currentEmail() {
        if (!emailInput) return state.email;
        return emailInput.type === 'hidden' ? emailInput.value : emailInput.value.trim();
    }

    function syncHidden() {
        document.getElementById('input_plan').value = state.plan;
        const months = cycles[state.cycle].months;
        document.getElementById('input_months').value = months;
        document.getElementById('input_method').value = state.method;
        const url = new URL(window.location.href);
        url.searchParams.set('plan', state.plan);
        url.searchParams.set('months', months);
        const em = currentEmail();
        if (em) url.searchParams.set('email', em);
        else url.searchParams.delete('email');
        window.history.replaceState({}, '', url);
    }

    function updateSummary() {
        const p = planData[state.plan];
        const c = cycles[state.cycle];
        const months = c.months;
        const subtotal = p.monthly * months;
        const total = p.prices[months];
        const discount = subtotal - total;

        document.getElementById('summary-plan-name').textContent = 'Gói ' + p.name;
        document.getElementById('summary-plan-slots').textContent = p.slots;
        document.getElementById('summary-plan-price').textContent = fmt(p.monthly) + '/th';
        document.getElementById('summary-cycle-label').textContent = c.label;
        document.getElementById('summary-cycle-mult').textContent = '×' + months;
        document.getElementById('summary-subtotal').textContent = fmt(subtotal);

        const discountRow = document.getElementById('summary-discount-row');
        if (discount > 0) {
            discountRow.style.display = 'flex';
            document.getElementById('summary-discount-label').textContent =
                'Giảm giá ' + (c.savePct || Math.round((discount / subtotal) * 100)) + '%';
            document.getElementById('summary-discount').textContent = '−' + fmt(discount);
        } else {
            discountRow.style.display = 'none';
        }
        document.getElementById('summary-total').textContent = fmt(total);
        payBtn.textContent = 'Thanh toán ' + fmt(total);

        state.email = currentEmail();
        payBtn.disabled = !state.email || state.method !== 'bank';
        syncHidden();

        for (const [id, cy] of Object.entries(cycles)) {
            const el = document.getElementById('price-' + id);
            if (el) el.textContent = fmt(p.prices[cy.months]);
        }

        const bankAmount = document.getElementById('bankAmount');
        const bankRef = document.getElementById('bankRef');
        if (bankAmount) bankAmount.textContent = fmt(total);
        if (bankRef) bankRef.textContent = buildRef(state.email, state.plan, months) || '—';
    }

    document.querySelectorAll('.plan-pick').forEach(function (el) {
        el.addEventListener('click', function () {
            state.plan = el.dataset.plan;
            document.querySelectorAll('.plan-pick').forEach(function (b) {
                b.classList.toggle('active', b.dataset.plan === state.plan);
            });
            updateSummary();
        });
    });

    document.querySelectorAll('.cycle-pick').forEach(function (el) {
        el.addEventListener('click', function () {
            state.cycle = el.dataset.cycle;
            document.querySelectorAll('.cycle-pick').forEach(function (b) {
                b.classList.toggle('active', b.dataset.cycle === state.cycle);
            });
            updateSummary();
        });
    });

    document.querySelectorAll('.method:not(.is-disabled)').forEach(function (el) {
        el.addEventListener('click', function () {
            state.method = el.dataset.method;
            document.querySelectorAll('.method').forEach(function (b) {
                b.classList.toggle('active', b.dataset.method === state.method && !b.disabled);
            });
            updateSummary();
        });
    });

    if (emailInput && emailInput.type !== 'hidden') {
        emailInput.addEventListener('input', updateSummary);
    }

    updateSummary();
})();
</script>
@endpush
