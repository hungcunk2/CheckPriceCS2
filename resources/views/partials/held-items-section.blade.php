@props(['items' => [], 'heldTotalCny' => null, 'admin' => false, 'compact' => true])

@if(count($items) > 0)
    <div class="held-items-section mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h6 class="mb-0 fw-semibold">
                <i class="fas fa-lock text-warning me-1" aria-hidden="true"></i>
                Đang hold Steam
                <span class="badge text-bg-warning ms-1">{{ count($items) }}</span>
            </h6>
            @if(($heldTotalCny ?? 0) > 0)
                <span class="small text-muted">
                    Tổng tham khảo: ¥{{ number_format($heldTotalCny, 2) }}
                </span>
            @endif
        </div>
        @if($admin)
            @include('partials.item-table-admin', ['items' => $items, 'compact' => $compact, 'showUnlock' => true])
        @else
            @include('partials.item-table', ['items' => $items, 'compact' => $compact, 'showUnlock' => true])
        @endif
    </div>
@endif
