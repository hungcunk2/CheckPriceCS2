@php
    $primary = $primary ?? false;
    $free = $free ?? false;
    $btnClass = 'lp-pricing-btn'.($primary ? ' is-primary' : '');
@endphp
@if($free)
    <a href="{{ route('public.landing') }}#hero" class="{{ $btnClass }}">Tra giá miễn phí</a>
@elseif(auth()->check())
    <a href="{{ route('member.dashboard') }}" class="{{ $btnClass }}">Chọn {{ $plan }}</a>
@else
    <button type="button" class="{{ $btnClass }}" data-open-auth-modal
            data-auth-tab="register" data-bs-toggle="modal" data-bs-target="#memberAuthModal">
        Chọn {{ $plan }}
    </button>
@endif
