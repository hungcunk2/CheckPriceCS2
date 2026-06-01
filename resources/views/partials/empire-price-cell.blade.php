@php
    $item = $item ?? null;
@endphp
@if($item && isset($item->empire_price_coins) && $item->empire_price_coins !== null)
    <div>{{ number_format($item->empire_price_coins, 2) }} coin</div>
    @if(isset($item->empire_price_cny) && $item->empire_price_cny !== null)
        <div class="small text-muted">≈ ¥{{ number_format($item->empire_price_cny, 2) }}</div>
    @endif
    @if(!empty($item->empire_price_vnd))
        <div class="small text-muted">≈ {{ number_format($item->empire_price_vnd) }} ₫</div>
    @endif
    @if(!empty($item->empire_url))
        <a href="{{ $item->empire_url }}" target="_blank" rel="noopener" class="small" title="Mở Empire"><i class="fas fa-external-link-alt"></i></a>
    @endif
@elseif($item && !empty($item->empire_error))
    <span class="small text-danger" title="{{ $item->empire_error }}">{{ \Illuminate\Support\Str::limit($item->empire_error, 42) }}</span>
@else
    —
@endif
