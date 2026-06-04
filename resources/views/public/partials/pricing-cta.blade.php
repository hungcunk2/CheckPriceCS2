@php
    $primary = $primary ?? false;
    $free = $free ?? false;
    $label = $label ?? ('Chọn '.$plan);
    $planKey = $planKey ?? strtolower($plan);
    $btnClass = 'lp-pricing-btn'.($primary ? ' is-primary' : '');
@endphp
@if($free)
    <a href="{{ route('public.landing') }}#hero" class="{{ $btnClass }}">{{ $label }}</a>
@else
    @auth
        <a href="{{ route('public.checkout', ['plan' => $planKey]) }}" class="{{ $btnClass }}">{{ $label }}</a>
    @else
        <button type="button"
            class="{{ $btnClass }} border-0"
            data-require-auth-checkout
            data-checkout-plan="{{ $planKey }}"
            data-open-auth-modal
            data-auth-tab="login"
            data-bs-toggle="modal"
            data-bs-target="#memberAuthModal">{{ $label }}</button>
    @endauth
@endif
