@php
    $fid = ($formIdSuffix ?? '') === 'modal' ? '-modal' : '';
    $otpSent = $otpSent ?? session('register_otp_sent') || (old('otp') && old('email'));
    $otpEmail = old('email', session('register_otp_email', ''));
    $otpService = app(\App\Services\RegistrationOtpService::class);
    $authMode = $mode ?? old('mode', request('auth', session('auth_tab', 'login')));
    $showForgot = $authMode === 'forgot' || request()->has('forgot') || $errors->getBag('forgot')->isNotEmpty();
    $showRegister = ! $showForgot && ($authMode === 'register' || $otpSent || $errors->has('password') || $errors->has('password_confirmation') || $errors->has('otp'));
    $redirectTo = $authRedirectTo ?? url()->current();
@endphp
<div class="ma-card ma-card--embedded">
    <div class="ma-brand">
        <span class="ma-brand-check">CheckPrice</span><span class="ma-brand-cs2 lp-text-gradient-primary">CS2</span>
    </div>

    <div id="ma-panel-login{{ $fid }}" class="ma-panel {{ ($showRegister || $showForgot) ? 'is-hidden' : '' }}">
        <h2 class="ma-title">Đăng nhập</h2>
        <p class="ma-subtitle">
            Chưa có tài khoản?
            <button type="button" class="ma-switch-link" data-ma-switch="register" data-ma-scope="{{ $fid }}">Đăng ký ngay</button>
        </p>

        @if(session('register_success'))
            <div class="ma-alert ma-alert--success">{{ session('register_success') }}</div>
        @endif
        @if(session('forgot_success'))
            <div class="ma-alert ma-alert--success">{{ session('forgot_success') }}</div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" novalidate>
            @csrf
            <input type="hidden" name="mode" value="login">
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

            <div class="ma-field">
                <div class="ma-input-wrap">
                    <i class="fas fa-envelope ma-input-icon" aria-hidden="true"></i>
                    <input type="email" name="email" class="ma-input @error('email') is-invalid @enderror"
                           value="{{ old('email') }}" placeholder="Email" required autocomplete="username">
                </div>
                @error('email')
                    <p class="ma-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="ma-field">
                <div class="ma-input-wrap">
                    <i class="fas fa-lock ma-input-icon" aria-hidden="true"></i>
                    <input type="password" name="password" id="login_password{{ $fid }}" class="ma-input"
                           placeholder="Mật khẩu" required autocomplete="current-password">
                    <button type="button" class="ma-input-toggle" data-ma-toggle-password="login_password{{ $fid }}" aria-label="Hiện mật khẩu">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <div class="ma-footer-row">
                <label class="ma-check">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>Ghi nhớ đăng nhập</span>
                </label>
                <button type="button" class="ma-switch-link ma-forgot-link" data-ma-switch="forgot" data-ma-scope="{{ $fid }}">
                    Quên mật khẩu?
                </button>
            </div>

            <button type="submit" class="ma-btn-primary lp-btn-primary">Đăng nhập</button>
        </form>
    </div>

    <div id="ma-panel-forgot{{ $fid }}" class="ma-panel {{ $showForgot ? '' : 'is-hidden' }}">
        <h2 class="ma-title">Quên mật khẩu</h2>
        <p class="ma-subtitle">
            Nhập email đăng ký — chúng tôi gửi mật khẩu tạm 6 số.
            <button type="button" class="ma-switch-link" data-ma-switch="login" data-ma-scope="{{ $fid }}">Quay lại đăng nhập</button>
        </p>

        <form method="POST" action="{{ route('password.forgot') }}" novalidate>
            @csrf
            <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

            <div class="ma-field">
                <div class="ma-input-wrap">
                    <i class="fas fa-envelope ma-input-icon" aria-hidden="true"></i>
                    <input type="email" name="email" class="ma-input @if($errors->getBag('forgot')->has('email')) is-invalid @endif"
                           value="{{ old('email') }}" placeholder="Email đã đăng ký" required autocomplete="email">
                </div>
                @if($errors->getBag('forgot')->has('email'))
                    <p class="ma-error">{{ $errors->getBag('forgot')->first('email') }}</p>
                @endif
            </div>

            <button type="submit" class="ma-btn-primary lp-btn-primary">Gửi mật khẩu mới</button>
        </form>
    </div>

    <div id="ma-panel-register{{ $fid }}" class="ma-panel {{ ($showRegister && ! $showForgot) ? '' : 'is-hidden' }}">
        <h2 class="ma-title">Đăng ký</h2>
        <p class="ma-subtitle">
            Đã có tài khoản?
            <button type="button" class="ma-switch-link" data-ma-switch="login" data-ma-scope="{{ $fid }}">Đăng nhập</button>
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
                <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">

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
                        <input type="password" name="password" id="reg_password{{ $fid }}" class="ma-input @error('password') is-invalid @enderror"
                               placeholder="Mật khẩu (tối thiểu 8 ký tự)" required minlength="8" autocomplete="new-password">
                        <button type="button" class="ma-input-toggle" data-ma-toggle-password="reg_password{{ $fid }}" aria-label="Hiện mật khẩu">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                    @error('password')<p class="ma-error">{{ $message }}</p>@enderror
                </div>

                <div class="ma-field">
                    <div class="ma-input-wrap">
                        <i class="fas fa-lock ma-input-icon" aria-hidden="true"></i>
                        <input type="password" name="password_confirmation" id="reg_password_confirm{{ $fid }}" class="ma-input"
                               placeholder="Nhập lại mật khẩu" required minlength="8" autocomplete="new-password">
                        <button type="button" class="ma-input-toggle" data-ma-toggle-password="reg_password_confirm{{ $fid }}" aria-label="Hiện mật khẩu">
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
                <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
                <input type="hidden" name="email" value="{{ $otpEmail }}">

                <div class="ma-field">
                    <div class="ma-input-wrap">
                        <i class="fas fa-envelope ma-input-icon" aria-hidden="true"></i>
                        <input type="email" class="ma-input" value="{{ $otpEmail }}" readonly>
                    </div>
                </div>

                <div class="ma-field">
                    <label class="ma-hint ma-otp-label" for="reg_otp{{ $fid }}">Mã OTP (6 số)</label>
                    <input type="text" name="otp" id="reg_otp{{ $fid }}" class="ma-input ma-input--otp @error('otp') is-invalid @enderror"
                           value="{{ old('otp') }}" required maxlength="6" minlength="6" pattern="\d{6}"
                           inputmode="numeric" autocomplete="one-time-code" placeholder="000000">
                    @error('otp')<p class="ma-error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="ma-btn-primary lp-btn-primary">Hoàn tất đăng ký</button>
            </form>

            <form method="POST" action="{{ route('register.resend-otp') }}" class="ma-form-secondary">
                @csrf
                <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
                <input type="hidden" name="email" value="{{ $otpEmail }}">
                <button type="submit" class="ma-btn-outline">Gửi lại OTP</button>
            </form>

            <p class="ma-hint ma-hint--center">
                <a href="{{ route('register.cancel', ['redirect_to' => $redirectTo]) }}" class="ma-switch-link">Đổi email khác</a>
            </p>
        @endif
    </div>
</div>
