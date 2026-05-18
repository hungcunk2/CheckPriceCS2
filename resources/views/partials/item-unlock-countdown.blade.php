@php
    $item = (object) ($item ?? []);
    $unlock = $item->trade_unlock_at ?? null;
@endphp
@if(!empty($unlock))
    @php
        $unlockCarbon = \Carbon\Carbon::parse($unlock)->timezone('Asia/Ho_Chi_Minh');
    @endphp
    <div
        class="trade-countdown trade-countdown--table"
        data-trade-at="{{ $unlockCarbon->toIso8601String() }}"
    >
        <span class="trade-countdown-value fw-semibold d-block">Đang tính…</span>
        <span class="trade-countdown-date text-muted">{{ $unlockCarbon->format('d/m H:i') }}</span>
    </div>
@else
    <span class="text-muted">—</span>
@endif
