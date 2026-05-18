@php
    $tradeAt = $inventory->trade_at ?? null;
@endphp
@if(!empty($tradeAt))
    @php
        $tradeCarbon = \Carbon\Carbon::parse($tradeAt)->timezone('Asia/Ho_Chi_Minh');
    @endphp
    <div
        class="trade-countdown {{ $class ?? '' }}"
        data-trade-at="{{ $tradeCarbon->toIso8601String() }}"
    >
        <div class="trade-countdown-main">
            @if(!empty($showLabel))
                <span class="trade-countdown-label text-muted">{{ $label ?? 'Còn' }}:</span>
            @endif
            <span class="trade-countdown-value fw-semibold">Đang tính…</span>
        </div>
        <span class="trade-countdown-date small text-muted">{{ $tradeCarbon->format('d/m/Y H:i') }}</span>
    </div>
@endif
