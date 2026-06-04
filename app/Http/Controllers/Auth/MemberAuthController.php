<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationOtpService;
use App\Support\CheckoutAuthRedirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class MemberAuthController extends Controller
{
    public function __construct(
        private RegistrationOtpService $registrationOtp,
    ) {}

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()?->hasActiveSubscription()) {
            return redirect()->route('member.inventories.index');
        }

        $mode = (string) $request->query('mode', session('auth_tab', $request->has('forgot') ? 'forgot' : 'login'));
        if (! in_array($mode, ['login', 'register', 'forgot'], true)) {
            $mode = 'login';
        }

        return view('auth.member', [
            'mode' => $mode,
            'otpSent' => session('register_otp_sent') || ($request->old('otp') && $request->old('email')),
        ]);
    }

    public function showRegister(): RedirectResponse
    {
        return redirect()->route('login', ['mode' => 'register']);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember')
        )) {
            return $this->authRedirectBack($request, 'login')
                ->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $redirectTo = CheckoutAuthRedirect::sanitize($request->input('redirect_to'));
        $checkoutFlow = CheckoutAuthRedirect::isCheckoutUrl($redirectTo);

        if ($user === null || (! $checkoutFlow && ! $user->hasActiveSubscription())) {
            Auth::logout();

            return $this->authRedirectBack($request, 'login')
                ->withErrors(['email' => 'Tài khoản chưa kích hoạt hoặc đã hết hạn gói. Liên hệ admin.'])
                ->onlyInput('email');
        }

        if ($checkoutFlow && $redirectTo !== null) {
            return redirect()->to($redirectTo);
        }

        return redirect()->intended(route('member.inventories.index'));
    }

    public function registerSendOtp(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'email.unique' => 'Email đã được đăng ký.',
        ]);

        $name = $this->displayNameFromEmail($validated['email']);

        $result = $this->registrationOtp->sendOtp(
            $name,
            $validated['email'],
            $validated['password'],
        );

        if ($request->expectsJson()) {
            if (! $result['ok']) {
                return response()->json(['ok' => false, 'message' => $result['message']], 422);
            }

            return response()->json([
                'ok' => true,
                'message' => $result['message'],
                'email' => $validated['email'],
            ]);
        }

        if (! $result['ok']) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['email' => $result['message']])
                ->withInput($request->only('email'));
        }

        return $this->authRedirectBack($request, 'register')
            ->with([
                'auth_tab' => 'register',
                'register_otp_sent' => true,
                'register_otp_email' => $validated['email'],
                'register_otp_message' => $result['message'],
            ]);
    }

    public function registerVerify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'otp.size' => 'Mã OTP phải gồm 6 chữ số.',
        ]);

        if (User::query()->where('email', $validated['email'])->exists()) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['email' => 'Email đã được đăng ký.'])
                ->with('register_otp_sent', true)
                ->with('register_otp_email', $validated['email']);
        }

        $result = $this->completeRegistration(
            $validated['email'],
            $validated['otp'],
            $validated['password'],
        );

        if (! $result['ok']) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['otp' => $result['message']])
                ->with('register_otp_sent', true)
                ->with('register_otp_email', $validated['email']);
        }

        session()->forget(['register_otp_sent', 'register_otp_email', 'register_otp_message']);

        /** @var User $user */
        $user = $result['user'];
        $redirectTo = CheckoutAuthRedirect::sanitize($request->input('redirect_to'));

        if (CheckoutAuthRedirect::isCheckoutUrl($redirectTo)) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()
                ->to($redirectTo)
                ->with('success', 'Đăng ký thành công. Hoàn tất chuyển khoản để admin kích hoạt gói.');
        }

        return $this->authRedirectBack($request, 'login')
            ->with('register_success', 'Đăng ký thành công. Admin sẽ kích hoạt gói — sau đó bạn đăng nhập được.');
    }

    public function registerConfirmEmail(Request $request): RedirectResponse
    {
        $email = trim(mb_strtolower((string) $request->query('email', '')));
        $otp = trim((string) $request->query('otp', ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! preg_match('/^\d{6}$/', $otp)) {
            return redirect()->route('public.landing')
                ->with('error', 'Liên kết xác nhận không hợp lệ.');
        }

        if (Auth::check()) {
            return redirect()->route('public.landing')
                ->with('register_magic_success', 'Bạn đã đăng nhập rồi.');
        }

        if (User::query()->where('email', $email)->exists()) {
            return redirect()->route('public.landing')
                ->with('register_success', 'Email đã được đăng ký. Đăng nhập sau khi admin kích hoạt gói.');
        }

        $result = $this->completeRegistration($email, $otp);

        if (! $result['ok']) {
            return redirect()->route('public.landing')
                ->with('error', $result['message']);
        }

        /** @var User $user */
        $user = $result['user'];
        Auth::login($user, true);
        $request->session()->regenerate();

        session()->forget(['register_otp_sent', 'register_otp_email', 'register_otp_message']);

        return redirect()
            ->route('member.dashboard')
            ->with('register_magic_success', 'Đăng ký thành công! Bạn đã được đăng nhập. Admin sẽ kích hoạt gói để dùng kho đồ Steam.');
    }

    public function registerCancel(Request $request): RedirectResponse
    {
        session()->forget([
            'register_otp_sent',
            'register_otp_email',
            'register_otp_message',
        ]);

        $to = trim((string) $request->query('redirect_to', ''));
        if ($to !== '' && str_starts_with($to, url('/'))) {
            return redirect()->to($to)->with('auth_tab', 'register');
        }

        return $this->authRedirectBack($request, 'register');
    }

    public function registerResendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $this->registrationOtp->resendOtp($validated['email']);

        if (! $result['ok']) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['otp' => $result['message']]);
        }

        return $this->authRedirectBack($request, 'register')
            ->with([
                'auth_tab' => 'register',
                'register_otp_sent' => true,
                'register_otp_email' => $validated['email'],
                'register_otp_message' => $result['message'],
            ]);
    }

    private function authRedirectBack(Request $request, string $mode): RedirectResponse
    {
        $to = trim((string) $request->input('redirect_to', ''));

        if ($to !== '' && str_starts_with($to, url('/'))) {
            $separator = str_contains($to, '?') ? '&' : '?';
            $query = ['openAuth' => 1];
            if ($mode === 'forgot') {
                $query['forgot'] = 1;
                $query['auth'] = 'forgot';
            } else {
                $query['auth'] = $mode;
            }

            return redirect()
                ->to($to.$separator.http_build_query($query))
                ->with('auth_tab', $mode)
                ->withInput($mode === 'register' ? $request->only('email') : []);
        }

        $routeParams = ['mode' => $mode];
        if ($mode === 'forgot') {
            $routeParams['forgot'] = 1;
        }

        return redirect()->route('login', $routeParams)->with('auth_tab', $mode);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.landing');
    }

    private function displayNameFromEmail(string $email): string
    {
        $local = Str::before(strtolower(trim($email)), '@');
        $name = trim(str_replace(['.', '_', '+'], ' ', $local));

        return $name !== '' ? Str::title($name) : 'Thành viên';
    }

    /**
     * @return array{ok: bool, message: string, user?: User}
     */
    private function completeRegistration(string $email, string $otp, ?string $password = null): array
    {
        $verified = $this->registrationOtp->verifyOtp($email, $otp);

        if (! $verified['ok']) {
            return ['ok' => false, 'message' => $verified['message']];
        }

        $plainPassword = $password ?? (string) ($verified['password'] ?? '');
        if ($plainPassword === '') {
            return ['ok' => false, 'message' => 'Phiên đăng ký đã hết hạn. Vui lòng đăng ký lại.'];
        }

        $user = User::query()->create([
            'name' => $verified['name'],
            'email' => $verified['email'],
            'password' => $plainPassword,
            'is_active' => false,
            'paid_until' => null,
            'email_verified_at' => now(),
        ]);

        return ['ok' => true, 'message' => 'Đăng ký thành công.', 'user' => $user];
    }
}
