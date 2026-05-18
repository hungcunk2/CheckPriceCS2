@props(['items' => [], 'compact' => false, 'showUnlock' => false])

@php
    use App\Support\Currency;
    use App\Support\InventoryWeaponStats;
@endphp

@if(count($items) > 0)
    <div class="item-table-wrap table-responsive {{ $compact ? 'mt-2' : '' }}" data-total-items="{{ count($items) }}">
        <table class="table table-hover mb-0 align-middle item-table {{ $compact ? 'table-sm' : '' }}">
            <thead class="table-light">
                <tr>
                    <th></th>
                    <th>Item</th>
                    <th class="text-center">SL</th>
                    <th class="text-end">Giá Buff</th>
                    @if($showUnlock)
                        <th class="text-end" style="min-width: 140px">Mở khóa</th>
                    @endif
                    <th class="text-end"><span class="price-col-label-vnd">VND</span><span class="price-col-label-usd">USD</span></th>
                    <th class="text-end">Tổng</th>
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
                            @if(!empty($item->icon_url))
                                <img src="{{ $item->icon_url }}" class="item-thumb image-zoomable" alt="" style="{{ $compact ? 'width:36px;height:36px' : '' }}">
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $item->name ?? '' }}</div>
                            @unless($compact)
                                <div class="small text-muted">{{ $item->market_hash_name ?? '' }}</div>
                            @endunless
                            @if(!empty($item->buff_error))
                                <div class="small text-danger">{{ $item->buff_error }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->amount ?? 1 }}</td>
                        <td class="text-end">
                            @if(isset($item->buff_price_cny) && $item->buff_price_cny !== null)
                                ¥{{ number_format($item->buff_price_cny, 2) }}
                            @else
                                —
                            @endif
                        </td>
                        @if($showUnlock)
                            <td class="text-end small">
                                @include('partials.item-unlock-countdown', ['item' => $item])
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
                    <td colspan="{{ $showUnlock ? 7 : 6 }}" class="text-center text-muted py-4">Không có vật phẩm thuộc loại này.</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
