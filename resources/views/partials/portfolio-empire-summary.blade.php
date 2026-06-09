@php
    $fmtCoins = function ($v) {
        if ($v === null) {
            return '—';
        }
        return number_format((float) $v, 2, ',', '.') . ' coin';
    };
    $fmtSignedCoins = function ($v) {
        if ($v === null) {
            return '—';
        }
        $sign = $v > 0 ? '+' : '';
        return $sign . number_format((float) $v, 2, ',', '.') . ' coin';
    };
@endphp
<div class="panel-admin rounded border p-3 h-100">
    <div class="text-muted small">Tổng Empire</div>
    <div class="fs-5 fw-semibold">{{ $fmtCoins($current['total_empire_coins'] ?? null) }}</div>
    @if(isset($current['total_empire_usd']))
        <div class="small text-muted">≈ {{ \App\Support\Currency::formatUsd($current['total_empire_usd']) }}</div>
    @endif
    @if($delta)
        <div class="small {{ ($delta['total_empire_coins'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
            {{ $fmtSignedCoins($delta['total_empire_coins'] ?? null) }}
            @if($deltaPct)<span class="ms-1">({{ $fmtPct($deltaPct['total_empire_coins'] ?? null) }})</span>@endif
        </div>
    @endif
</div>
