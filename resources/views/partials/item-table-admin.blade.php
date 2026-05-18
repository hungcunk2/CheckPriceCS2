@props(['items' => [], 'compact' => true])

@php
    use App\Support\Currency;
    use App\Support\InventoryWeaponStats;
@endphp

@if(count($items) > 0)
    <div class="item-table-wrap table-responsive {{ $compact ? 'mt-2' : '' }}" data-total-items="{{ count($items) }}">
        <table class="table table-hover mb-0 align-middle item-table table-sm">
            <thead class="table-light">
                <tr>
                    <th></th>
                    <th>Item</th>
                    <th class="text-center">SL</th>
                    <th class="text-end">Hiện tại<br><span class="fw-normal small text-muted">(2h)</span></th>
                    <th class="text-end">Hôm qua<br><span class="fw-normal small text-muted">(± listing)</span></th>
                    <th class="text-end">0h hôm nay</th>
                    <th class="text-end">7 ngày trước</th>
                    <th class="text-end">USD (2h)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    @php
                        $item = (object) $item;
                        $weaponCategory = InventoryWeaponStats::categoryKey($item->market_hash_name ?? $item->name ?? '');
                        $hist = $item->price_history ?? null;
                        $cur = is_array($hist) ? ($hist['current_2h'] ?? null) : null;
                        $yesterday = is_array($hist) ? ($hist['yesterday'] ?? null) : null;
                        $todayOpen = is_array($hist) ? ($hist['today_open'] ?? null) : null;
                        $days7 = is_array($hist) ? ($hist['days_7'] ?? null) : null;
                        $delta = is_array($hist) ? ($hist['sell_num_delta'] ?? null) : null;
                        $price2h = $cur['price_cny'] ?? $item->buff_price_cny ?? null;
                        $stale = ! empty($cur['stale']);
                    @endphp
                    <tr data-weapon-category="{{ $weaponCategory }}">
                        <td>
                            @if(!empty($item->icon_url))
                                <img src="{{ $item->icon_url }}" class="item-thumb" alt="" style="width:36px;height:36px">
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $item->name ?? '' }}</div>
                            @if(!empty($item->buff_error))
                                <div class="small text-danger">{{ $item->buff_error }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->amount ?? 1 }}</td>
                        <td class="text-end">
                            @if($price2h !== null)
                                <span class="{{ $stale ? 'text-warning' : '' }}">¥{{ number_format($price2h, 2) }}</span>
                                @if($stale)
                                    <div class="small text-warning d-block">ngoài 2h</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">
                            @if(!empty($yesterday['price_cny']))
                                ¥{{ number_format($yesterday['price_cny'], 2) }}
                                @if($delta !== null)
                                    <div class="small {{ $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-muted') }}">
                                        {{ $delta > 0 ? '+' : '' }}{{ $delta }}
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">
                            @if(!empty($todayOpen['price_cny']))
                                ¥{{ number_format($todayOpen['price_cny'], 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">
                            @if(!empty($days7['price_cny']))
                                ¥{{ number_format($days7['price_cny'], 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">
                            @if($price2h !== null)
                                {{ Currency::formatUsd(Currency::cnyToUsd($price2h)) }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="weapon-filter-empty d-none">
                    <td colspan="8" class="text-center text-muted py-4">Không có vật phẩm thuộc loại này.</td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
