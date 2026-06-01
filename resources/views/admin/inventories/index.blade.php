@extends('layouts.admin')

@section('title', 'Quản lý kho đồ')
@section('page-title', 'Quản lý kho Steam')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
@if(empty($buffConfigured))
    <div class="alert alert-warning py-2 small mb-3">
        Chưa có acc Buff hoạt động.
    </div>
@endif
@if($empireEnabled ?? false)
    <div class="alert alert-info py-2 small mb-3">
        Empire đang bật — cột <strong>Empire</strong> / <strong>Nên bán</strong> trong bảng skin. Kho chưa đồng bộ sau khi bật Empire: bấm <i class="fas fa-sync-alt"></i> trên từng kho.
    </div>
@endif

<div class="d-flex justify-content-end align-items-center mb-3">
    <a href="{{ route('admin.inventories.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Thêm kho
    </a>
</div>

<div class="panel-admin rounded border mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Kho</th>
                    <th>Giá Buff</th>
                    @if($empireEnabled ?? false)
                        <th>Giá Empire</th>
                    @endif
                    <th>Skin</th>
                    <th>Thời gian trade</th>
                    <th>Cập nhật</th>
                    <th>Public</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inventories as $inv)
                    @php
                        $items = $inv->display_items ?? [];
                        $snap = is_array($inv->last_snapshot ?? null)
                            ? $inv->last_snapshot
                            : json_decode($inv->last_snapshot ?? '[]', true);
                        $empireTotalCny = is_array($snap) ? ($snap['total_empire_cny'] ?? null) : null;
                        if ($empireTotalCny === null && ($empireEnabled ?? false)) {
                            $empireTotalCny = collect($items)->sum(fn ($row) => (float) (is_array($row) ? ($row['line_total_empire_cny'] ?? 0) : ($row->line_total_empire_cny ?? 0)));
                        }
                    @endphp
                    <tr
                        class="admin-inventory-summary-row"
                        role="button"
                        tabindex="0"
                        data-bs-target="#admin-inv-items-{{ $inv->id }}"
                        aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="admin-inv-items-{{ $inv->id }}"
                    >
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                            @if(!empty($inv->steam_avatar_url))
                                <img src="{{ $inv->steam_avatar_url }}" alt="" class="steam-avatar image-zoomable flex-shrink-0" width="48" height="48" loading="lazy" style="--steam-avatar-size: 48px">
                            @endif
                                <div class="min-w-0">
                                    <div class="fw-semibold">{{ \App\Support\InventoryDisplay::title($inv) }}</div>
                                    <div class="small text-muted text-truncate" style="max-width:240px">{{ $inv->url }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if(($inv->last_total_cny ?? 0) > 0)
                                <span class="text-success">@include('partials.price-converted', ['cny' => $inv->last_total_cny])</span><br>
                                <small class="text-muted">¥{{ number_format($inv->last_total_cny, 2) }}</small>
                            @else
                                <span class="text-warning">Chưa có giá</span>
                            @endif
                        </td>
                        @if($empireEnabled ?? false)
                            <td>
                                @if(($empireTotalCny ?? 0) > 0)
                                    <span class="text-warning">@include('partials.price-converted', ['cny' => $empireTotalCny])</span><br>
                                    <small class="text-muted">≈ ¥{{ number_format($empireTotalCny, 2) }}</small>
                                @else
                                    <span class="text-muted small">Chưa có / chưa sync</span>
                                @endif
                            </td>
                        @endif
                        <td>{{ count($items) ?: ($inv->item_count ?? 0) }}</td>
                        <td class="small" style="min-width: 140px">
                            @include('partials.trade-countdown', [
                                'inventory' => $inv,
                                'showLabel' => true,
                                'label' => 'Còn',
                                'class' => 'trade-countdown--table',
                            ])
                        </td>
                        <td class="small">
                            @if(!empty($inv->last_checked_at))
                                {{ \Carbon\Carbon::parse($inv->last_checked_at)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($inv->is_public ?? true)
                                <span class="badge text-bg-success">Hiện</span>
                            @else
                                <span class="badge text-bg-secondary">Ẩn</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap admin-inventory-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary admin-inventory-toggle-btn"
                                data-bs-toggle="collapse"
                                data-bs-target="#admin-inv-items-{{ $inv->id }}"
                                aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                title="Danh sách skin"
                            >
                                <i class="fas fa-chevron-down admin-inventory-chevron"></i>
                            </button>
                            <a href="{{ route('public.show', $inv->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="Xem trang công khai"><i class="fas fa-eye"></i></a>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-refresh" data-id="{{ $inv->id }}" title="Check giá"><i class="fas fa-sync-alt"></i></button>
                            <a href="{{ route('admin.inventories.edit', $inv->id) }}" class="btn btn-sm btn-outline-secondary" title="Sửa"><i class="fas fa-edit"></i></a>
                            <form action="{{ route('admin.inventories.destroy', $inv->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa kho này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <tr class="admin-inventory-detail-row">
                        <td colspan="{{ ($empireEnabled ?? false) ? 9 : 8 }}" class="p-0 border-0">
                            <div id="admin-inv-items-{{ $inv->id }}" class="collapse {{ $loop->first ? 'show' : '' }}">
                                <div class="p-4 border-top bg-light inventory-detail-panel-wrap">
                                    @include('partials.inventory-detail-panel', [
                                        'inventory' => $inv,
                                        'items' => $inv->display_items ?? $items,
                                        'weaponStats' => $inv->weapon_stats ?? [],
                                        'admin' => true,
                                    ])
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($empireEnabled ?? false) ? 9 : 8 }}" class="text-center text-muted py-4">Chưa có kho nào. Bấm "Thêm kho" để bắt đầu.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/inventory-weapon-filter.js') }}"></script>
<script>
document.querySelectorAll('.admin-inventory-actions').forEach(cell => {
    cell.addEventListener('click', e => e.stopPropagation());
});

document.querySelectorAll('.admin-inventory-summary-row').forEach(row => {
    const targetSel = row.getAttribute('data-bs-target');
    const toggle = () => {
        const el = document.querySelector(targetSel);
        if (el) bootstrap.Collapse.getOrCreateInstance(el).toggle();
    };
    row.addEventListener('click', e => {
        if (e.target.closest('.admin-inventory-actions')) return;
        toggle();
    });
    row.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggle();
        }
    });
});

document.querySelectorAll('[id^="admin-inv-items-"]').forEach(panel => {
    panel.addEventListener('show.bs.collapse', () => syncAdminInventoryChevron(panel.id, true));
    panel.addEventListener('hide.bs.collapse', () => syncAdminInventoryChevron(panel.id, false));
});

function syncAdminInventoryChevron(panelId, open) {
    const btn = document.querySelector('[data-bs-target="#' + panelId + '"].admin-inventory-toggle-btn');
    const row = document.querySelector('[data-bs-target="#' + panelId + '"].admin-inventory-summary-row');
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (row) row.setAttribute('aria-expanded', open ? 'true' : 'false');
}

document.querySelectorAll('.btn-refresh').forEach(btn => {
    btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        const loading = document.getElementById('admin-loading');
        loading.style.display = 'flex';
        btn.disabled = true;
        try {
            const res = await fetch(@json(route('admin.inventories.refresh', ['inventory' => 0])).replace('/0/', '/' + id + '/'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            let json = {};
            try {
                json = await res.json();
            } catch (parseErr) {
                throw new Error(res.status === 504 ? 'Timeout server (quá 60s) — thử lại hoặc chờ cron' : 'Phản hồi không hợp lệ (HTTP ' + res.status + ')');
            }
            if (json.ok) location.reload();
            else alert(json.message || ('Lỗi check giá (HTTP ' + res.status + ')'));
        } catch (e) {
            alert(e.message || 'Lỗi kết nối');
        } finally {
            loading.style.display = 'none';
            btn.disabled = false;
        }
    });
});
</script>
@endpush
