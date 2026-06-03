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
                <a href="{{ route('public.pricing') }}" @class(['is-active' => request()->routeIs('public.pricing')])>Bảng giá</a>
            </div>
            <div class="lp-nav-right">
                @auth
                    <a href="{{ route('member.inventories.index') }}" class="lp-btn-ghost me-2">Kho đồ</a>
                @endauth
                @if($showHeaderActions ?? false)
                    <div class="lp-nav-utilities">
                        @include('partials.header-actions')
                    </div>
                @endif
                @auth
                    <a href="{{ route('member.inventories.index') }}" class="lp-btn-primary lp-glow-blue">Xem kho ngay</a>
                @else
                    <button type="button" class="lp-btn-primary lp-glow-blue" data-open-auth-modal
                            data-auth-tab="login" data-bs-toggle="modal" data-bs-target="#memberAuthModal">
                        Xem kho ngay
                    </button>
                @endif
            </div>
        </nav>
    </div>
</header>
