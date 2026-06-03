@php
    $landingSection = function (string $id): string {
        return request()->routeIs('public.landing')
            ? '#'.$id
            : route('public.landing').'#'.$id;
    };
@endphp
<header class="lp-nav-wrap">
    <div class="lp-container">
        <nav class="lp-nav lp-glass">
            <a href="{{ route('public.landing') }}" class="lp-brand">
                <div class="lp-brand-icon lp-glow-blue">
                    <img src="{{ asset('images/logo.png') }}" alt="" width="22" height="22" style="border-radius:4px">
                </div>
                <span class="lp-brand-text">
                    CheckPrice<span class="lp-text-gradient-accent">CS2</span>
                </span>
            </a>
            <div class="lp-nav-links">
                <a href="{{ $landingSection('features') }}">Tính năng</a>
                <a href="{{ $landingSection('how') }}">Cách dùng</a>
                <a href="{{ route('blog.index') }}" @class(['is-active' => request()->routeIs('blog.*')])>Blog</a>
                <a href="{{ $landingSection('faq') }}">FAQ</a>
                <a href="{{ route('public.index') }}" @class(['is-active' => request()->routeIs('public.index', 'public.show')])>Bảng giá</a>
            </div>
            <div class="lp-nav-right">
                @auth
                    @if(auth()->user()?->hasActiveSubscription())
                        <a href="{{ route('member.dashboard') }}" class="lp-btn-ghost me-2">Tài khoản</a>
                    @endif
                @else
                    <div class="lp-nav-auth me-2">
                        <button type="button" class="lp-nav-auth-link" data-bs-toggle="modal" data-bs-target="#memberAuthModal"
                                data-auth-tab="login">Đăng nhập</button>
                        <span class="lp-nav-auth-sep" aria-hidden="true">·</span>
                        <button type="button" class="lp-nav-auth-link" data-bs-toggle="modal" data-bs-target="#memberAuthModal"
                                data-auth-tab="register">Đăng ký</button>
                    </div>
                @endauth
                @if($showHeaderActions ?? false)
                    <div class="lp-nav-utilities">
                        @include('partials.header-actions')
                    </div>
                @endif
                <a href="{{ route('public.index') }}" class="lp-btn-primary lp-glow-blue">Xem kho ngay</a>
            </div>
        </nav>
    </div>
</header>
