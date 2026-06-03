<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\MemberForgotPasswordMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'redirect_to' => ['nullable', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if ($user === null) {
            return $this->redirectBack($request)
                ->withErrors(['email' => 'Email không tồn tại trong hệ thống.'], 'forgot')
                ->withInput($request->only('email'));
        }

        $temporaryPassword = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill(['password' => $temporaryPassword])->save();

        try {
            Mail::to($email)->send(new MemberForgotPasswordMail($user, $temporaryPassword));
        } catch (Throwable $e) {
            report($e);

            return $this->redirectBack($request)
                ->withErrors(['email' => 'Không gửi được email. Kiểm tra MAIL_* trong .env.'], 'forgot')
                ->withInput($request->only('email'));
        }

        return $this->redirectBack($request, 'login')
            ->with('forgot_success', 'Mật khẩu mới 6 số đã gửi tới email. Dùng mật khẩu đó để đăng nhập.');
    }

    private function redirectBack(Request $request, string $mode = 'forgot'): RedirectResponse
    {
        $to = trim((string) $request->input('redirect_to', ''));

        if ($to !== '' && str_starts_with($to, url('/'))) {
            $separator = str_contains($to, '?') ? '&' : '?';

            return redirect()->to($to.$separator.http_build_query([
                'openAuth' => 1,
                'auth' => $mode,
            ]))->with('auth_tab', $mode);
        }

        return redirect()->route('login', ['mode' => $mode])->with('auth_tab', $mode);
    }
}
