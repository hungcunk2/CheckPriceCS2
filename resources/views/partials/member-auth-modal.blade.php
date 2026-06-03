@php
    $authTab = old('auth_tab', request('auth', session('auth_tab', 'login')));
    $otpSent = session('register_otp_sent') || (old('otp') && old('email'));
    $otpEmail = old('email', session('register_otp_email', ''));
    $otpService = app(\App\Services\RegistrationOtpService::class);
@endphp
<div class="modal fade" id="memberAuthModal" tabindex="-1" aria-labelledby="memberAuthModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content lp-auth-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <p class="lp-auth-modal-kicker mb-1">Thành viên</p>
                    <h5 class="modal-title mb-0" id="memberAuthModalLabel">Đăng nhập / Đăng ký</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="lp-auth-modal-desc mb-3">
                    Giá Empire chính xác (coin). Khách tra link Steam — không cần tài khoản.
                </p>

                @if(session('register_success'))
                    <div class="alert alert-success lp-auth-alert small mb-3">{{ session('register_success') }}</div>
                @endif
                @if(session('register_otp_message'))
                    <div class="alert alert-info lp-auth-alert small mb-3">{{ session('register_otp_message') }}</div>
                @endif

                <ul class="nav nav-pills nav-fill mb-3 lp-auth-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if($authTab !== 'register') active @endif" id="auth-tab-login"
                                data-bs-toggle="pill" data-bs-target="#auth-pane-login" type="button" role="tab">
                            Đăng nhập
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if($authTab === 'register') active @endif" id="auth-tab-register"
                                data-bs-toggle="pill" data-bs-target="#auth-pane-register" type="button" role="tab">
                            Đăng ký
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade @if($authTab !== 'register') show active @endif" id="auth-pane-login" role="tabpanel">
                        <form method="POST" action="{{ route('login.submit') }}" class="lp-auth-form">
                            @csrf
                            <input type="hidden" name="auth_tab" value="login">
                            <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email') }}" required autocomplete="username">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mật khẩu</label>
                                <input type="password" name="password" class="form-control" required autocomplete="current-password">
                            </div>
                            <div class="mb-3 form-check lp-auth-check">
                                <input type="checkbox" name="remember" value="1" class="form-check-input" id="authRemember">
                                <label class="form-check-label" for="authRemember">Ghi nhớ đăng nhập</label>
                            </div>
                            <button type="submit" class="lp-btn-primary w-100">Đăng nhập</button>
                        </form>
                    </div>

                    <div class="tab-pane fade @if($authTab === 'register') show active @endif" id="auth-pane-register" role="tabpanel">
                        @if(!$otpSent)
                            <form method="POST" action="{{ route('register.send-otp') }}" class="lp-auth-form">
                                @csrf
                                <input type="hidden" name="auth_tab" value="register">
                                <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                                <div class="mb-3">
                                    <label class="form-label">Họ tên</label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name') }}" required maxlength="120">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                           value="{{ old('email') }}" required autocomplete="email">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mật khẩu</label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                           required minlength="8" autocomplete="new-password">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nhập lại mật khẩu</label>
                                    <input type="password" name="password_confirmation" class="form-control" required minlength="8" autocomplete="new-password">
                                </div>
                                <button type="submit" class="lp-btn-primary w-100">Gửi mã OTP qua email</button>
                                <p class="lp-auth-hint mt-3 mb-0">Mã 6 số gửi tới email để xác nhận đăng ký.</p>
                            </form>
                        @else
                            <form method="POST" action="{{ route('register.verify') }}" class="lp-auth-form">
                                @csrf
                                <input type="hidden" name="auth_tab" value="register">
                                <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                                <p class="lp-auth-hint mb-3">
                                    Đã gửi mã OTP tới <strong>{{ $otpService->maskEmail($otpEmail) }}</strong>.
                                    Hiệu lực {{ $otpService->ttlMinutes() }} phút.
                                </p>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                           value="{{ $otpEmail }}" required readonly>
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mã OTP (6 số)</label>
                                    <input type="text" name="otp" class="form-control lp-auth-otp-input text-center font-monospace @error('otp') is-invalid @enderror"
                                           value="{{ old('otp') }}" required maxlength="6" minlength="6" pattern="\d{6}"
                                           inputmode="numeric" autocomplete="one-time-code" placeholder="000000">
                                    @error('otp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="lp-btn-primary w-100">Xác nhận &amp; đăng ký</button>
                            </form>
                            <form method="POST" action="{{ route('register.resend-otp') }}" class="mt-3 text-center">
                                @csrf
                                <input type="hidden" name="auth_tab" value="register">
                                <input type="hidden" name="redirect_to" value="{{ url()->current() }}">
                                <input type="hidden" name="email" value="{{ $otpEmail }}">
                                <button type="submit" class="lp-auth-link-btn">Gửi lại mã OTP</button>
                            </form>
                            <p class="lp-auth-hint mt-3 mb-0 text-center">
                                <a href="{{ route('register.cancel') }}" class="lp-auth-inline-link">Đăng ký email khác</a>
                                <span class="mx-1">·</span>
                                Admin kích hoạt gói sau đăng ký
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    (function () {
        var modalEl = document.getElementById('memberAuthModal');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        document.querySelectorAll('[data-bs-target="#memberAuthModal"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-auth-tab');
                if (tab === 'register') {
                    var tabBtn = document.getElementById('auth-tab-register');
                    if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                }
            });
        });

        var shouldOpen = @json(
            $errors->any()
            || session('register_success')
            || session('register_otp_sent')
            || session('register_otp_message')
            || request()->has('auth')
            || request()->query('openAuth')
        );
        if (shouldOpen) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    })();
    </script>
    @endpush
@endonce
