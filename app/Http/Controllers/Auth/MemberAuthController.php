<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RegistrationOtpService;
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
            return redirect()->route('member.dashboard');
        }

        $mode = (string) $request->query('mode', session('auth_tab', 'login'));
        if (! in_array($mode, ['login', 'register'], true)) {
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
            return redirect()->route('login', ['mode' => 'login'])
                ->withErrors(['email' => 'Email hoặc mật khẩu không đúng.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::user();
        if ($user === null || ! $user->hasActiveSubscription()) {
            Auth::logout();

            return redirect()->route('login', ['mode' => 'login'])
                ->withErrors(['email' => 'Tài khoản chưa kích hoạt hoặc đã hết hạn gói. Liên hệ admin.'])
                ->onlyInput('email');
        }

        return redirect()->intended(route('member.dashboard'));
    }

    public function registerSendOtp(Request $request): RedirectResponse
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

        if (! $result['ok']) {
            return redirect()->route('login', ['mode' => 'register'])
                ->withErrors(['email' => $result['message']])
                ->withInput($request->only('email'));
        }

        return redirect()->route('login', ['mode' => 'register'])
            ->with([
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
        ], [
            'otp.size' => 'Mã OTP phải gồm 6 chữ số.',
        ]);

        if (User::query()->where('email', $validated['email'])->exists()) {
            return redirect()->route('login', ['mode' => 'register'])
                ->withErrors(['email' => 'Email đã được đăng ký.'])
                ->with('register_otp_sent', true)
                ->with('register_otp_email', $validated['email']);
        }

        $verified = $this->registrationOtp->verifyOtp($validated['email'], $validated['otp']);

        if (! $verified['ok']) {
            return redirect()->route('login', ['mode' => 'register'])
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

        session()->forget(['register_otp_sent', 'register_otp_email', 'register_otp_message']);

        return redirect()->route('login', ['mode' => 'login'])
            ->with('register_success', 'Đăng ký thành công. Admin sẽ kích hoạt gói — sau đó bạn đăng nhập được.');
    }

    public function registerCancel(): RedirectResponse
    {
        session()->forget([
            'register_otp_sent',
            'register_otp_email',
            'register_otp_message',
        ]);

        return redirect()->route('login', ['mode' => 'register']);
    }

    public function registerResendOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $this->registrationOtp->resendOtp($validated['email']);

        if (! $result['ok']) {
            return redirect()->route('login', ['mode' => 'register'])
                ->withErrors(['otp' => $result['message']]);
        }

        return redirect()->route('login', ['mode' => 'register'])
            ->with([
                'register_otp_sent' => true,
                'register_otp_email' => $validated['email'],
                'register_otp_message' => $result['message'],
            ]);
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
}
