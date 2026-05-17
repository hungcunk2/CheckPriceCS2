@props(['inventory', 'items' => [], 'weaponStats' => [], 'compact' => true, 'admin' => false, 'cnyToVnd' => null])

@if(count($items) === 0)
    <div class="alert alert-info mb-0 py-2 small">
        Chưa có danh sách skin. Bấm <strong>sync</strong> để lấy kho và giá Buff163.
    </div>
@else
    <div class="inventory-item-filter-root">
        @if(count($weaponStats) > 0)
            @include('partials.weapon-stats', ['stats' => $weaponStats, 'compact' => $compact])
        @endif
        @if($admin)
            @include('partials.item-table-admin', ['items' => $items, 'compact' => $compact, 'cnyToVnd' => $cnyToVnd])
        @else
            @include('partials.item-table', ['items' => $items, 'compact' => $compact])
        @endif
    </div>
@endif
