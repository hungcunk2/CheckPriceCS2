@php
    $tableMode = $tableMode ?? 'admin';
    $colspan = ($empireEnabled ?? false) ? 10 : 9;
@endphp
<div id="admin-inv-toast" class="position-fixed top-0 end-0 p-3" style="z-index: 3100; max-width: 420px;"></div>
@if(empty($buffConfigured))
    <div class="alert alert-warning py-2 small mb-3">
        Chưa có acc Buff hoạt động.
    </div>
@endif
@if(($empireEnabled ?? false) && $tableMode === 'admin')
    <div class="alert alert-info py-2 small mb-3">
        Empire đang bật — cột <strong>Empire</strong> / <strong>Nên bán</strong> trong bảng skin.
        Kho chưa đồng bộ sau khi bật Empire: bấm <i class="fas fa-sync-alt"></i> trên từng kho.
    </div>
@endif

@if($tableMode === 'admin')
    <div class="d-flex justify-content-end align-items-center mb-3">
        <a href="{{ route('admin.inventories.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Thêm kho
        </a>
    </div>
@elseif($tableMode === 'member' && ($canAddInventory ?? true))
    <div class="d-flex justify-content-end align-items-center mb-3">
        <a href="{{ route('member.inventories.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Thêm kho
        </a>
    </div>
@endif

<div class="panel-admin rounded border mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Kho</th>
                    <th>Chú thích</th>
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
                        data-inventory-id="{{ $inv->id }}"
                        aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                        aria-controls="admin-inv-items-{{ $inv->id }}"
                    >
                        <td>{{ $loop->iteration }}</td>
                        <td class="inv-identity-cell">
                            @include('partials.inventory-list-identity', ['inventory' => $inv])
                        </td>
                        <td class="small text-muted" style="min-width: 120px; max-width: 220px;">
                            @if(filled($inv->notes ?? null))
                                <span title="{{ $inv->notes }}">{{ \Illuminate\Support\Str::limit($inv->notes, 80) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="inv-buff-price-cell">
                            @if(($inv->last_total_cny ?? 0) > 0)
                                <span class="text-success">@include('partials.price-converted', ['cny' => $inv->last_total_cny])</span><br>
                                <small class="text-muted">¥{{ number_format($inv->last_total_cny, 2) }}</small>
                            @else
                                <span class="text-warning">Chưa có giá</span>
                            @endif
                        </td>
                        @if($empireEnabled ?? false)
                            <td class="inv-empire-price-cell">
                                @if(($empireTotalCny ?? 0) > 0)
                                    <span class="text-warning">@include('partials.price-converted', ['cny' => $empireTotalCny])</span><br>
                                    <small class="text-muted">≈ ¥{{ number_format($empireTotalCny, 2) }}</small>
                                @else
                                    <span class="text-muted small">Chưa có / chưa sync</span>
                                @endif
                            </td>
                        @endif
                        <td class="inv-item-count-cell">
                            <span class="{{ \App\Support\InventoryDisplay::isInventoryEmpty($inv) ? 'text-muted small' : '' }}">
                                {{ \App\Support\InventoryDisplay::itemCountLabel($inv, count($items)) }}
                            </span>
                        </td>
                        <td class="small" style="min-width: 140px">
                            @include('partials.trade-countdown', [
                                'inventory' => $inv,
                                'showLabel' => true,
                                'label' => 'Còn',
                                'class' => 'trade-countdown--table',
                            ])
                        </td>
                        <td class="small inv-updated-cell">
                            @if(!empty($inv->last_checked_at))
                                {{ \Carbon\Carbon::parse($inv->last_checked_at)->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($inv->is_public ?? false)
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
                            @if($inv->is_public ?? false)
                                <a href="{{ route('public.inventories') }}#kho-{{ $inv->id }}" class="btn btn-sm btn-outline-secondary" target="_blank" title="Xem trên kho công khai"><i class="fas fa-eye"></i></a>
                            @endif
                            @if($tableMode === 'admin')
                                <button type="button" class="btn btn-sm btn-outline-primary btn-refresh" data-id="{{ $inv->id }}" title="Check giá"><i class="fas fa-sync-alt"></i></button>
                                <a href="{{ route('admin.inventories.edit', $inv->id) }}" class="btn btn-sm btn-outline-secondary" title="Sửa"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('admin.inventories.destroy', $inv->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa kho này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fas fa-trash"></i></button>
                                </form>
                            @elseif($tableMode === 'member')
                                <button type="button" class="btn btn-sm btn-outline-primary btn-refresh" data-id="{{ $inv->id }}" title="Check giá"><i class="fas fa-sync-alt"></i></button>
                                <a href="{{ route('member.inventories.edit', $inv->id) }}" class="btn btn-sm btn-outline-secondary" title="Sửa"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('member.inventories.destroy', $inv->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Xóa kho này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fas fa-trash"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    <tr class="admin-inventory-detail-row">
                        <td colspan="{{ $colspan }}" class="p-0 border-0">
                            <div id="admin-inv-items-{{ $inv->id }}" class="collapse {{ $loop->first ? 'show' : '' }}">
                                <div class="p-4 border-top bg-light inventory-detail-panel-wrap">
                                    @include('partials.inventory-detail-panel', [
                                        'inventory' => $inv,
                                        'items' => $inv->display_items ?? $items,
                                        'weaponStats' => $inv->weapon_stats ?? [],
                                        'admin' => $tableMode === 'admin',
                                    ])
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $colspan }}" class="text-center text-muted py-4">
                            @if($tableMode === 'admin')
                                Chưa có kho nào. Bấm "Thêm kho" để bắt đầu.
                            @elseif($tableMode === 'member')
                                Chưa có kho nào. Bấm "Thêm kho" để thêm link Steam của bạn.
                            @else
                                Chưa có kho nào được theo dõi.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
