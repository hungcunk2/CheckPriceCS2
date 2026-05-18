@props(['items' => [], 'compact' => false])

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
                    <th class="text-end">USD</th>
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
                                <img src="{{ $item->icon_url }}" class="item-thumb" alt="" style="{{ $compact ? 'width:36px;height:36px' : '' }}">
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
                        <td class="text-end">
                            @php $unitUsd = $item->buff_price_usd ?? Currency::cnyToUsd($item->buff_price_cny ?? null); @endphp
                            @if($unitUsd !== null)
                                {{ Currency::formatUsd($unitUsd) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end fw-semibold">
                            @php $lineUsd = $item->line_total_usd ?? Currency::cnyToUsd($item->line_total_cny ?? null); @endphp
                            @if($lineUsd !== null)
                                {{ Currency::formatUsd($lineUsd) }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="weapon-filter-empty d-none">
                    <td colspan="6" class="text-center text-muted py-4">Không có vật phẩm thuộc loại này.</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
