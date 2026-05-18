@extends('layouts.public')



@section('title', \App\Support\InventoryDisplay::title($inventory))



@section('content')
@php use App\Support\Currency; @endphp

<div class="mb-3">

    <a href="{{ route('public.index') }}" class="text-decoration-none"><i class="fas fa-arrow-left"></i> Tất cả kho</a>

</div>



<div class="card panel-card mb-4">

    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">

        <div>
            @include('partials.inventory-identity', ['inventory' => $inventory, 'heading' => 'h4', 'size' => 72])
        </div>

        <div class="text-end">

            @if(($inventory->last_total_cny ?? 0) > 0)

                <div class="h3 mb-0 text-primary">@include('partials.price-converted', ['cny' => $inventory->last_total_cny])</div>

                <div class="small text-muted mb-0">¥{{ number_format($inventory->last_total_cny, 2) }}</div>

            @else

                <div class="text-warning">Chưa có dữ liệu giá</div>

            @endif

            @include('partials.trade-countdown', [
                'inventory' => $inventory,
                'showLabel' => true,
                'label' => 'Còn lại',
                'class' => 'trade-countdown--card mt-2',
            ])

            @if(!empty($inventory->last_checked_at))

                <div class="small text-muted mt-1">

                    {{ $inventory->item_count ?? 0 }} skin tradable
                    @if(count($heldItems ?? []) > 0)
                        · {{ count($heldItems) }} hold
                    @endif
                    ·
                    {{ \Carbon\Carbon::parse($inventory->last_checked_at)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}

                </div>

            @endif

        </div>

    </div>

</div>



@if(count($items) === 0 && count($heldItems ?? []) === 0)

    <div class="alert alert-info">

        Chưa có danh sách skin. Admin vào <strong>/admin/inventories</strong> → bấm nút <strong>sync</strong> (cập nhật giá) để lưu danh sách.

    </div>

@else
    <div class="inventory-item-filter-root">
    @if(!empty($weaponStats))
        <div class="card panel-card mb-4">
            <div class="card-body">
                @include('partials.weapon-stats', ['stats' => $weaponStats])
            </div>
        </div>
    @endif

    @if(count($items) > 0)
    <div class="card panel-card inventory-collapse-card">

        <div class="card-header p-0 border-0 bg-transparent">

            <button

                type="button"

                class="inventory-collapse-toggle w-100 btn text-start border-0 rounded-3 d-flex justify-content-between align-items-center gap-2"

                data-bs-toggle="collapse"

                data-bs-target="#inventory-items-list"

                aria-expanded="true"

                aria-controls="inventory-items-list"

            >

                <span>

                    <span class="fw-semibold">Danh sách skin</span>


                </span>

                <i class="fas fa-chevron-down collapse-chevron text-muted" aria-hidden="true"></i>

            </button>

        </div>

        <div id="inventory-items-list" class="collapse show">

            <div class="card-body border-top inventory-collapse-body">

                @include('partials.item-table', ['items' => $items])

            </div>

        </div>

    </div>
    @endif

    @if(count($heldItems ?? []) > 0)
        <div class="card panel-card mb-4">
            <div class="card-body">
                @include('partials.held-items-section', [
                    'items' => $heldItems,
                    'heldTotalCny' => $heldTotalCny ?? null,
                    'compact' => false,
                ])
            </div>
        </div>
    @endif
    </div>

@endif

@endsection

@push('scripts')
<script src="{{ asset('js/inventory-weapon-filter.js') }}"></script>
@endpush

