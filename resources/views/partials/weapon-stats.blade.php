@props(['stats' => [], 'compact' => false])

@if(count($stats) > 0)
    <div class="weapon-stats {{ $compact ? 'weapon-stats--compact' : '' }}">
        @if(!$compact)
            <h6 class="text-muted mb-2">Thống kê theo loại</h6>
        @endif
        <div class="weapon-filter-status small text-primary mb-2 d-none" role="status">
            Đang lọc: <strong class="weapon-filter-label"></strong>
            <span class="weapon-filter-count text-muted"></span>
            <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline weapon-filter-clear">Bỏ lọc</button>
        </div>
        <div class="d-flex flex-wrap align-items-center weapon-stat-list">
            <button
                type="button"
                class="badge rounded-pill weapon-stat-badge weapon-stat-badge--active bg-primary text-white border-0"
                data-weapon-category="*"
                data-weapon-label="Tất cả"
                aria-pressed="true"
            >
                Tất cả
            </button>
            @foreach($stats as $row)
                @php $row = (object) $row; @endphp
                <button
                    type="button"
                    class="badge rounded-pill weapon-stat-badge bg-light text-dark border"
                    data-weapon-category="{{ $row->key }}"
                    data-weapon-label="{{ $row->label }}"
                    aria-pressed="false"
                >
                    <span class="weapon-stat-label">{{ $row->label }}</span>
                    <span class="weapon-stat-count ms-1 fw-semibold">{{ $row->count }}</span>
                </button>
            @endforeach
        </div>
    </div>
@endif
