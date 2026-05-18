@props(['cny' => null, 'class' => ''])

@php
    use App\Support\Currency;
    $cny = isset($cny) ? (float) $cny : null;
@endphp

@if($cny !== null && $cny > 0)
    <span class="price-converted {{ $class }}">
        <span class="price-vnd">{{ number_format(Currency::cnyToVnd($cny)) }} ₫</span>
        <span class="price-usd">{{ Currency::formatUsd(Currency::cnyToUsd($cny)) }}</span>
    </span>
@endif
