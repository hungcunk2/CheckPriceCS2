<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class MemberAuthController extends Controller
{
    public function __construct(
        private RegistrationOtpService $registrationOtp,
    ) {}

    public function showLogin(): RedirectResponse
    {
        if (Auth::check() && Auth::user()?->hasActiveSubscription()) {
            return redirect()->route('member.dashboard');
        }

        return redirect()->route('public.landing', ['auth' => 'login']);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'auth_tab' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        if (! Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            $request->boolean('remember')
        )) {
            return $this->authRedirectBack($request, 'login')
                ->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])
                ->onlyInput('email', 'auth_tab');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user === null || ! $user->hasActiveSubscription()) {
            Auth::logout();

            return $this->authRedirectBack($request, 'login')
                ->withErrors(['email' => 'Tài khoản chưa kích hoạt hoặc đã hết hạn gói. Liên hệ admin.'])
                ->onlyInput('email', 'auth_tab');
        }

        return redirect()->intended(route('member.dashboard'));
    }

    public function registerSendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'auth_tab' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ], [
            'email.unique' => 'Email đã được đăng ký.',
        ]);

        $result = $this->registrationOtp->sendOtp(
            $validated['name'],
            $validated['email'],
            $validated['password'],
        );

        if (! $result['ok']) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['email' => $result['message']])
                ->withInput($request->only('name', 'email', 'auth_tab'));
        }

        return $this->authRedirectBack($request, 'register')
            ->with([
                'register_otp_sent' => true,
                'register_otp_email' => $validated['email'],
                'register_otp_message' => $result['message'],
                'auth_tab' => 'register',
            ]);
    }

    public function registerVerify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'size:6'],
            'auth_tab' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ], [
            'otp.size' => 'Mã OTP phải gồm 6 chữ số.',
        ]);

        if (User::query()->where('email', $validated['email'])->exists()) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['email' => 'Email đã được đăng ký.'])
                ->with('register_otp_sent', true)
                ->with('register_otp_email', $validated['email']);
        }

        $verified = $this->registrationOtp->verifyOtp($validated['email'], $validated['otp']);

        if (! $verified['ok']) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['otp' => $verified['message']])
                ->with('register_otp_sent', true)
                ->with('register_otp_email', $validated['email']);
        }

        User::query()->create([
            'name' => $verified['name'],
            'email' => $verified['email'],
            'password' => $verified['password'],
            'is_active' => false,
            'paid_until' => null,
            'email_verified_at' => now(),
        ]);

        return $this->authRedirectBack($request, 'register')
            ->with([
                'register_success' => 'Đăng ký thành công. Admin sẽ kích hoạt gói — sau đó bạn đăng nhập được.',
                'auth_tab' => 'register',
            ]);
    }

    public function registerCancel(): RedirectResponse
    {
        session()->forget([
            'register_otp_sent',
            'register_otp_email',
            'register_otp_message',
        ]);

        return redirect()
            ->route('public.landing', ['auth' => 'register'])
            ->with('auth_tab', 'register');
    }

    public function registerResendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'auth_tab' => ['nullable', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $result = $this->registrationOtp->resendOtp($validated['email']);

        if (! $result['ok']) {
            return $this->authRedirectBack($request, 'register')
                ->withErrors(['otp' => $result['message']]);
        }

        return $this->authRedirectBack($request, 'register')
            ->with([
                'register_otp_sent' => true,
                'register_otp_email' => $validated['email'],
                'register_otp_message' => $result['message'],
                'auth_tab' => 'register',
            ]);
    }

    private function authRedirectBack(Request $request, string $tab): RedirectResponse
    {
        $to = trim((string) $request->input('redirect_to', ''));
        if ($to !== '' && str_starts_with($to, url('/'))) {
            return redirect()->to($to)->with('auth_tab', $tab);
        }

        return redirect()->route('public.landing', ['auth' => $tab])->with('auth_tab', $tab);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('public.landing');
    }
}
