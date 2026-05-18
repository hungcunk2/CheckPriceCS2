@props(['cny' => null])

@php
    use App\Support\Currency;
    $cny = isset($cny) ? (float) $cny : null;
@endphp

@if($cny !== null)
    <span class="price-vnd">{{ number_format(Currency::cnyToVnd($cny)) }} ₫</span>
    <span class="price-usd">{{ Currency::formatUsd(Currency::cnyToUsd($cny)) }}</span>
@else
    —
@endif
