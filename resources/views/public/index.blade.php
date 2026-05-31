@extends('layouts.public')

@section('title', 'Bảng giá kho CS2')

@section('content')
@php use App\Support\Currency; @endphp
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <h2 class="h4 mb-0">Bảng giá kho CS2</h2>
    <a href="{{ route('public.landing') }}" class="text-decoration-none small text-muted">
        <i class="fas fa-arrow-left"></i> Trang chủ
    </a>
</div>

<form action="{{ route('public.index') }}" method="get" class="row g-2 mb-4">
    <div class="col-md-9">
        <input
            type="search"
            name="q"
            class="form-control"
            value="{{ $searchQuery ?? '' }}"
            placeholder="Tìm theo tên kho..."
        >
    </div>
    <div class="col-md-3">
        <button type="submit" class="btn btn-primary w-100">Tìm kho</button>
    </div>
</form>

@if(!empty($searchQuery))
    <p class="text-muted small mb-3">Kết quả tìm "<strong>{{ $searchQuery }}</strong>"</p>
@endif

@if($inventories->isEmpty())
    <div class="text-center text-muted py-5">
        <i class="fas fa-box-open fa-3x mb-3 opacity-50"></i>
        @if(!empty($searchQuery))
            <p>Không tìm thấy kho phù hợp.</p>
        @else
            <p>Chưa có kho nào được công bố.</p>
        @endif
    </div>
@else
    @foreach($inventories as $inv)
        @php $items = $inv->display_items ?? []; @endphp
        <div class="card panel-card mb-4 inventory-collapse-card" id="kho-{{ $inv->id }}">
            @if(count($items) > 0)
                <div class="card-header p-0 border-0 bg-transparent">
                    <button
                        type="button"
                        class="inventory-collapse-toggle w-100 btn text-start border-0 rounded-top-3 d-flex flex-wrap justify-content-between align-items-center gap-3 {{ $loop->first ? '' : 'collapsed' }}"
                        data-bs-toggle="collapse"
                        data-bs-target="#inv-items-{{ $inv->id }}"
                        aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="inv-items-{{ $inv->id }}"
                    >
                        <div class="pe-2 flex-grow-1 min-w-0">
                            @include('partials.inventory-identity', ['inventory' => $inv, 'size' => 60])
                        </div>
                        <div class="d-flex align-items-start gap-3">
                            <div class="text-end">
                                @if(($inv->last_total_cny ?? 0) > 0)
                                    <div class="h4 mb-0 text-primary">@include('partials.price-converted', ['cny' => $inv->last_total_cny])</div>
                                    <div class="small text-muted">¥{{ number_format($inv->last_total_cny, 2) }}</div>
                                @else
                                    <div class="text-warning">Chưa có giá Buff</div>
                                @endif
                                @include('partials.trade-countdown', [
                                    'inventory' => $inv,
                                    'showLabel' => true,
                                    'label' => 'Còn lại',
                                    'class' => 'trade-countdown--card mt-2',
                                ])
                                @if(!empty($inv->last_checked_at))
                                    <div class="small text-muted mt-1">
                                        {{ count($items) }} skin ·
                                        {{ \Carbon\Carbon::parse($inv->last_checked_at)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                            </div>
                            <i class="fas fa-chevron-down collapse-chevron text-muted mt-1" aria-hidden="true"></i>
                        </div>
                    </button>
                </div>
                <div id="inv-items-{{ $inv->id }}" class="collapse {{ $loop->first ? 'show' : '' }}">
                    <div class="card-body border-top inventory-collapse-body inventory-item-filter-root">
                        @if(!empty($inv->weapon_stats))
                            @include('partials.weapon-stats', ['stats' => $inv->weapon_stats, 'compact' => true])
                        @endif
                        @include('partials.item-table', ['items' => $items, 'compact' => true])
                    </div>
                </div>
            @else
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                        <div>
                            @include('partials.inventory-identity', ['inventory' => $inv, 'size' => 60])
                        </div>
                        <div class="text-end">
                            @if(($inv->last_total_cny ?? 0) > 0)
                                <div class="h4 mb-0 text-primary">@include('partials.price-converted', ['cny' => $inv->last_total_cny])</div>
                                <div class="small text-muted">¥{{ number_format($inv->last_total_cny, 2) }}</div>
                            @else
                                <div class="text-warning">Chưa có giá Buff</div>
                            @endif
                            @include('partials.trade-countdown', [
                                'inventory' => $inv,
                                'showLabel' => true,
                                'label' => 'Còn lại',
                                'class' => 'trade-countdown--card mt-2',
                            ])
                        </div>
                    </div>
                    <div class="alert alert-info mb-0 py-2 small">
                        Chưa có danh sách skin.
                    </div>
                </div>
            @endif
        </div>
    @endforeach
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/inventory-weapon-filter.js') }}"></script>
@endpush
