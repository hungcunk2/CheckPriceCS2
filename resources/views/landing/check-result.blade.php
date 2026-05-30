@if(!empty($checkError))
    <div id="ket-qua-tra-gia" class="lp-check-result lp-check-result--error lp-glass rounded-3 p-4 mt-4">
        <div class="d-flex align-items-start gap-3">
            <i class="fas fa-circle-exclamation" style="color:var(--lp-accent);margin-top:0.15rem"></i>
            <div>
                <div class="fw-semibold mb-1">Không tra được giá</div>
                <div class="lp-muted small">{{ $checkError }}</div>
            </div>
        </div>
    </div>
@elseif(!empty($checkResult))
    @php
        $inv = $checkResult['inventory'];
        $items = $checkResult['items'];
    @endphp
    <div id="ket-qua-tra-gia" class="lp-check-result lp-glass-strong rounded-3 p-4 sm:p-6 mt-4 text-start">
        <div class="lp-check-result-header d-flex flex-wrap justify-content-between align-items-start gap-4 mb-4 pb-4" style="border-bottom:1px solid var(--lp-border)">
            <div class="d-flex align-items-center gap-3 min-w-0">
                @if(!empty($inv->steam_avatar_url))
                    <img src="{{ $inv->steam_avatar_url }}" alt="" class="lp-check-avatar" width="56" height="56">
                @else
                    <div class="lp-check-avatar lp-check-avatar--placeholder"><i class="fab fa-steam"></i></div>
                @endif
                <div class="min-w-0">
                    <div class="fw-semibold text-truncate">{{ $inv->steam_persona_name ?? $inv->label ?? 'Steam' }}</div>
                    @if(!empty($inv->url))
                        <a href="{{ $inv->url }}" target="_blank" rel="noopener noreferrer" class="small lp-muted text-decoration-none">
                            Mở trên Steam <i class="fas fa-arrow-up-right-from-square"></i>
                        </a>
                    @endif
                </div>
            </div>
            <div class="text-md-end">
                @if(($inv->last_total_cny ?? 0) > 0)
                    <div class="lp-check-total lp-text-gradient-primary">@include('partials.price-converted', ['cny' => $inv->last_total_cny])</div>
                    <div class="small lp-muted">¥{{ number_format($inv->last_total_cny, 2) }}</div>
                @else
                    <div style="color:var(--lp-accent)">Chưa có giá Buff</div>
                @endif
                <div class="small lp-muted mt-1">
                    {{ $checkResult['item_count'] }} skin · {{ $checkResult['priced_count'] }} có giá
                </div>
            </div>
        </div>

        @if(count($items) > 0)
            <div class="lp-check-table-wrap">
                <table class="lp-check-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Item</th>
                            <th class="text-center">SL</th>
                            <th class="text-end">Buff</th>
                            <th class="text-end"><span class="price-col-label-vnd">VND</span><span class="price-col-label-usd">USD</span></th>
                            <th class="text-end">Tổng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @php $item = (object) $item; @endphp
                            <tr>
                                <td>
                                    @if(!empty($item->icon_url))
                                        <img src="{{ $item->icon_url }}" alt="" class="lp-check-item-thumb">
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-medium small">{{ $item->name ?? '' }}</div>
                                    @if(!empty($item->buff_error))
                                        <div class="small" style="color:var(--lp-accent)">{{ $item->buff_error }}</div>
                                    @endif
                                </td>
                                <td class="text-center small">{{ $item->amount ?? 1 }}</td>
                                <td class="text-end small">
                                    @if(isset($item->buff_price_cny) && $item->buff_price_cny !== null)
                                        ¥{{ number_format($item->buff_price_cny, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end small">
                                    @include('partials.price-cell', ['cny' => $item->buff_price_cny ?? null])
                                </td>
                                <td class="text-end small fw-semibold">
                                    @include('partials.price-cell', ['cny' => $item->line_total_cny ?? null])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
