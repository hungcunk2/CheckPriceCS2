@php
    $primary = $primary ?? false;
    $btnClass = 'lp-pricing-btn'.($primary ? ' is-primary' : '');
@endphp
@guest
    <button type="button" class="{{ $btnClass }}" data-open-auth-modal
            data-auth-tab="register" data-bs-toggle="modal" data-bs-target="#memberAuthModal">
        Chọn {{ $plan }}
    </button>
@else
    <a href="{{ route('member.dashboard') }}" class="{{ $btnClass }}">Chọn {{ $plan }}</a>
@endguest
