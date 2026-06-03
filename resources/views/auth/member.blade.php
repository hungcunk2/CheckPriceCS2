@extends('layouts.auth-member')

@php
    $otpSent = $otpSent ?? session('register_otp_sent') || (old('otp') && old('email'));
    $otpEmail = old('email', session('register_otp_email', ''));
    $otpService = app(\App\Services\RegistrationOtpService::class);
    $showRegister = ($mode ?? 'login') === 'register' || $otpSent || $errors->has('password') || $errors->has('password_confirmation') || $errors->has('otp');
@endphp

@section('content')
<div class="ma-card">
    <div id="ma-panel-login" class="ma-panel {{ $showRegister ? 'is-hidden' : '' }}">
        <h1 class="ma-title">Đăng nhập</h1>
        <p class="ma-subtitle">
            Chưa có tài khoản <span class="ma-site-name">{{ config('site.name') }}</span>?
            <button type="button" class="ma-switch-link" data-ma-switch="register">Đăng ký ngay</button>
        </p>

        @if(session('register_success'))
            <div class="ma-alert ma-alert--success">{{ session('register_success') }}</div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" novalidate>
            @csrf
            <input type="hidden" name="mode" value="login">

            <div class="ma-field">
                <div class="ma-input-wrap">
                    <i class="fas fa-envelope ma-input-icon" aria-hidden="true"></i>
                    <input type="email" name="email" class="ma-input @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" placeholder="Email" required autocomplete="username" autofocus>
                </div>
                @error('email')
                    <p class="ma-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="ma-field">
                <div class="ma-input-wrap">
                    <i class="fas fa-lock ma-input-icon" aria-hidden="true"></i>
                    <input type="password" name="password" id="login_password" class="ma-input"
                           placeholder="Mật khẩu" required autocomplete="current-password">
                    <button type="button" class="ma-input-toggle" data-ma-toggle-password="login_password" aria-label="Hiện mật khẩu">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <div class="ma-footer-row">
                <label class="ma-check">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Ghi nhớ đăng nhập</span>
                </label>
            </div>

            <button type="submit" class="ma-btn-primary">Đăng nhập</button>
        </form>
    </div>

    <div id="ma-panel-register" class="ma-panel {{ $showRegister ? '' : 'is-hidden' }}">
        <h1 class="ma-title">Đăng ký</h1>
        <p class="ma-subtitle">
            Đã có tài khoản?
            <button type="button" class="ma-switch-link" data-ma-switch="login">Đăng nhập</button>
        </p>

        @if(session('register_otp_message'))
            <div class="ma-alert ma-alert--info">{{ session('register_otp_message') }}</div>
        @endif

        @if($errors->has('email') && ! $otpSent)
            <div class="ma-alert ma-alert--danger">{{ $errors->first('email') }}</div>
        @endif

        @if(! $otpSent)
            <form method="POST" action="{{ route('register.send-otp') }}" novalidate>
                @csrf
                <input type="hidden" name="mode" value="register">

                <div class="ma-field">
                    <div class="ma-input-wrap">
                        <i class="fas fa-envelope ma-input-icon" aria-hidden="true"></i>
                        <input type="email" name="email" class="ma-input @error('email') is-invalid @enderror"
                               value="{{ $otpEmail }}" placeholder="Email" required autocomplete="email">
                    </div>
                </div>

                <div class="ma-field">
                    <div class="ma-input-wrap">
                        <i class="fas fa-lock ma-input-icon" aria-hidden="true"></i>
                        <input type="password" name="password" id="reg_password" class="ma-input @error('password') is-invalid @enderror"
                               placeholder="Mật khẩu (tối thiểu 8 ký tự)" required minlength="8" autocomplete="new-password">
                        <button type="button" class="ma-input-toggle" data-ma-toggle-password="reg_password" aria-label="Hiện mật khẩu">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    @error('password')<p class="ma-error">{{ $message }}</p>@enderror
                </div>

                <div class="ma-field">
                    <div class="ma-input-wrap">
                        <i class="fas fa-lock ma-input-icon" aria-hidden="true"></i>
                        <input type="password" name="password_confirmation" id="reg_password_confirm" class="ma-input"
                               placeholder="Nhập lại mật khẩu" required minlength="8" autocomplete="new-password">
                        <button type="button" class="ma-input-toggle" data-ma-toggle-password="reg_password_confirm" aria-label="Hiện mật khẩu">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <p class="ma-hint">Bấm <strong>Nhận OTP</strong> — hệ thống gửi mã 6 số tới email của bạn.</p>
                <button type="submit" class="ma-btn-outline">Nhận OTP</button>
            </form>
        @else
            <p class="ma-hint">
                Mã OTP đã gửi tới <strong>{{ $otpService->maskEmail($otpEmail) }}</strong>
                (hiệu lực {{ $otpService->ttlMinutes() }} phút).
            </p>

            <form method="POST" action="{{ route('register.verify') }}" novalidate>
                @csrf
                <input type="hidden" name="mode" value="register">
                <input type="hidden" name="email" value="{{ $otpEmail }}">

                <div class="ma-field">
                    <div class="ma-input-wrap">
                        <i class="fas fa-envelope ma-input-icon" aria-hidden="true"></i>
                        <input type="email" class="ma-input" value="{{ $otpEmail }}" readonly>
                    </div>
                </div>

                <div class="ma-field">
                    <label class="ma-hint" for="reg_otp" style="display:block;margin-bottom:0.35rem">Mã OTP (6 số)</label>
                    <input type="text" name="otp" id="reg_otp" class="ma-input ma-input--otp @error('otp') is-invalid @enderror"
                           value="{{ old('otp') }}" required maxlength="6" minlength="6" pattern="\d{6}"
                           inputmode="numeric" autocomplete="one-time-code" placeholder="000000">
                    @error('otp')<p class="ma-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="ma-btn-primary">Hoàn tất đăng ký</button>
            </form>

            <form method="POST" action="{{ route('register.resend-otp') }}" class="mt-2">
                @csrf
                <input type="hidden" name="email" value="{{ $otpEmail }}">
                <button type="submit" class="ma-btn-outline">Gửi lại OTP</button>
            </form>

            <p class="ma-hint text-center mt-3 mb-0">
                <a href="{{ route('register.cancel') }}" class="ma-switch-link" style="font-weight:500">Đổi email khác</a>
            </p>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var loginPanel = document.getElementById('ma-panel-login');
    var registerPanel = document.getElementById('ma-panel-register');
    if (!loginPanel || !registerPanel) return;

    document.querySelectorAll('[data-ma-switch]').forEach(function (el) {
        el.addEventListener('click', function () {
            var target = el.getAttribute('data-ma-switch');
            loginPanel.classList.toggle('is-hidden', target !== 'login');
            registerPanel.classList.toggle('is-hidden', target !== 'register');
        });
    });

    document.querySelectorAll('[data-ma-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-ma-toggle-password'));
            if (!input) return;
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', isPass);
                icon.classList.toggle('fa-eye-slash', !isPass);
            }
        });
    });
})();
</script>
@endpush
