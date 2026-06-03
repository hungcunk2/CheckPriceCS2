@php
    $primary = $primary ?? false;
    $free = $free ?? false;
    $label = $label ?? ('Chọn '.$plan);
    $btnClass = 'lp-pricing-btn'.($primary ? ' is-primary' : '');
@endphp
@if($free)
    <a href="{{ route('public.landing') }}#hero" class="{{ $btnClass }}">{{ $label }}</a>
@elseif(auth()->check())
    <a href="{{ route('member.dashboard') }}" class="{{ $btnClass }}">{{ $label }}</a>
@else
    <button type="button" class="{{ $btnClass }}" data-open-auth-modal
            data-auth-tab="register" data-bs-toggle="modal" data-bs-target="#memberAuthModal">
        {{ $label }}
    </button>
@endif
