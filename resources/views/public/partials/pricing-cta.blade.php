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
    <a href="{{ route('public.checkout', ['plan' => $planKey]) }}" class="{{ $btnClass }}">{{ $label }}</a>
@endif
