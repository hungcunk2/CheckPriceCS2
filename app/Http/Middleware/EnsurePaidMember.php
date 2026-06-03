<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePaidMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->hasActiveSubscription()) {
            if ($user !== null) {
                return redirect()
                    ->route('member.dashboard')
                    ->with('error', 'Tài khoản chưa được kích hoạt gói. Liên hệ admin.');
            }

            return redirect()
                ->route('login')
                ->with('error', 'Cần đăng nhập tài khoản trả phí để dùng tính năng này.');
        }

        return $next($request);
    }
}
