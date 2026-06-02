@props(['items' => [], 'compact' => false])

@php
    use App\Support\Currency;
    use App\Support\InventoryWeaponStats;
    $placeholderImg = asset('images/logo.png');
@endphp

@if(count($items) > 0)
    <div class="item-table-wrap table-responsive {{ $compact ? 'mt-2' : '' }}" data-total-items="{{ count($items) }}">
        <table class="table table-hover mb-0 align-middle item-table {{ $compact ? 'table-sm' : '' }}">
            <thead class="table-light">
                <tr>
                    <th></th>
                    <th>Item</th>
                    <th class="text-center">SL</th>
                    <th class="text-end">Buff</th>
                    @if($empireEnabled ?? false)
                        <th class="text-end">Empire</th>
                        <th class="text-center">Nên bán</th>
                    @endif
                    <th class="text-end"><span class="price-col-label-vnd">VND</span><span class="price-col-label-usd">USD</span></th>
                    <th class="text-end">Tổng Buff</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    @php
                        $item = (object) $item;
                        $weaponCategory = InventoryWeaponStats::categoryKey($item->market_hash_name ?? $item->name ?? '');
                    @endphp
                    <tr data-weapon-category="{{ $weaponCategory }}">
                        <td>
                            <img
                                src="{{ !empty($item->icon_url) ? $item->icon_url : $placeholderImg }}"
                                class="item-thumb image-zoomable"
                                alt=""
                                data-hash="{{ $item->market_hash_name ?? '' }}"
                                loading="lazy"
                                referrerpolicy="no-referrer"
                                onerror="window.__cpcs2CatalogImgFallback && window.__cpcs2CatalogImgFallback(this)"
                                style="{{ $compact ? 'width:36px;height:36px' : '' }}"
                            >
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $item->name ?? '' }}</div>
                            @unless($compact)
                                <div class="small text-muted">{{ $item->market_hash_name ?? '' }}</div>
                            @endunless
                            @if(!empty($item->buff_error))
                                <div class="small text-danger">{{ $item->buff_error }}</div>
                            @endif
                            @if(!empty($item->empire_error) && ($empireEnabled ?? false))
                                <div class="small text-muted">{{ $item->empire_error }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->amount ?? 1 }}</td>
                        <td class="text-end">
                            @if(isset($item->buff_price_cny) && $item->buff_price_cny !== null)
                                ¥{{ number_format($item->buff_price_cny, 2) }}
                                @if(!empty($item->buff_url))
                                    <a href="{{ $item->buff_url }}" target="_blank" rel="noopener" class="small ms-1" title="Mở Buff163"><i class="fas fa-external-link-alt"></i></a>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        @if($empireEnabled ?? false)
                            <td class="text-end">
                                @include('partials.empire-price-cell', ['item' => $item])
                            </td>
                            <td class="text-center">
                                @include('partials.best-sell-venue', ['venue' => $item->best_sell_venue ?? null])
                            </td>
                        @endif
                        <td class="text-end">
                            @include('partials.price-cell', ['cny' => $item->buff_price_cny ?? null])
                        </td>
                        <td class="text-end fw-semibold">
                            @include('partials.price-cell', ['cny' => $item->line_total_cny ?? null])
                        </td>
                    </tr>
                @endforeach
                <tr class="weapon-filter-empty d-none">
                    <td colspan="{{ ($empireEnabled ?? false) ? 8 : 6 }}" class="text-center text-muted py-4">Không có vật phẩm thuộc loại này.</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
