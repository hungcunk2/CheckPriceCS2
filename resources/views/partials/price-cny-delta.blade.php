@props(['delta' => null, 'label' => null])

@if($delta !== null)
    <div class="price-cny-delta small {{ $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-muted') }}">
        <span class="price-cny-delta-value">{{ $delta > 0 ? '+' : '' }}¥{{ number_format($delta, 2) }}</span>
        @if(filled($label))
            <span class="price-cny-delta-label text-muted">{{ $label }}</span>
        @endif
    </div>
@endif
