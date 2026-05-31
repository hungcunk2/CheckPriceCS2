@extends('layouts.admin')

@section('title', 'Acc Buff163')
@section('page-title', 'Quản lý acc Buff163')

@section('content')
@if($usesDatabase)
    <div class="alert alert-success py-2 small mb-3">
        Acc Buff lưu trong <strong>database (mã hóa)</strong> — session hết hạn thì bấm Sửa, dán cookie mới, không cần SSH.
    </div>
@elseif($configured)
    <div class="alert alert-warning py-2 small mb-3">
        Đang dùng acc từ <code>.env</code>.
        <form method="POST" action="{{ route('admin.buff-accounts.import-env') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-dark ms-1">Import sang DB</button>
        </form>
        để quản lý trên web.
    </div>
@else
    <div class="alert alert-warning">
        Chưa có acc Buff. Thêm acc mới hoặc import từ <code>.env</code>.
        <form method="POST" action="{{ route('admin.buff-accounts.import-env') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-dark ms-1">Import từ .env</button>
        </form>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <p class="text-muted mb-0">
        Probe gọi API Buff kiểm tra session — không ghi cooldown sync giá.
    </p>
    <div class="d-flex gap-2">
        @if($usesDatabase)
            <a href="{{ route('admin.buff-accounts.create') }}" class="btn btn-outline-primary">
                <i class="fas fa-plus me-1"></i> Thêm acc
            </a>
        @endif
        @if($configured)
            <form method="POST" action="{{ route('admin.buff-accounts.probe-all') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-stethoscope me-1"></i> Kiểm tra tất cả
                </button>
            </form>
        @endif
    </div>
</div>

@if($configured)
    <div class="panel-admin rounded border">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Acc</th>
                        <th>Session</th>
                        <th>CSRF</th>
                        <th>Pool</th>
                        <th>Cooldown</th>
                        <th>Lần check gần nhất</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        @php
                            $check = $account['last_check'] ?? null;
                            $status = $check['status'] ?? null;
                            $badgeClass = match ($status) {
                                'ok' => 'text-bg-success',
                                'blocked', 'rate_limited', 'invalid_session' => 'text-bg-danger',
                                'error' => 'text-bg-warning',
                                default => 'text-bg-secondary',
                            };
                            $statusLabel = match ($status) {
                                'ok' => 'Hoạt động',
                                'blocked' => 'Bị chặn 403',
                                'rate_limited' => '429',
                                'invalid_session' => 'Session lỗi',
                                'error' => 'Lỗi',
                                default => 'Chưa check',
                            };
                            $managed = $managedAccounts->firstWhere('label', $account['label']);
                        @endphp
                        <tr>
                            <td>
                                <code>{{ $account['label'] }}</code>
                                @if($managed && ! $managed->is_active)
                                    <span class="badge text-bg-secondary ms-1">Tắt</span>
                                @endif
                            </td>
                            <td class="small font-monospace text-muted">{{ $managed->session_hint ?? '—' }}</td>
                            <td>
                                @if($account['has_csrf'])
                                    <span class="badge text-bg-success">Có</span>
                                @else
                                    <span class="badge text-bg-secondary">Không</span>
                                @endif
                            </td>
                            <td>
                                @if($account['available'])
                                    <span class="badge text-bg-success">Sẵn sàng</span>
                                @else
                                    <span class="badge text-bg-warning">Cooldown</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($account['in_cooldown'])
                                    {{ (int) ceil(($account['cooldown_seconds'] ?? 0) / 60) }} phút
                                @else
                                    —
                                @endif
                            </td>
                            <td class="small">
                                @if($check)
                                    <div>{{ $check['message'] ?? '—' }}</div>
                                    <div class="text-muted">{{ \Carbon\Carbon::parse($check['checked_at'])->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                @if($managed)
                                    <a href="{{ route('admin.buff-accounts.edit', $managed->id) }}" class="btn btn-sm btn-outline-secondary" title="Cập nhật session">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.buff-accounts.destroy', $managed->id) }}" class="d-inline" onsubmit="return confirm('Xóa acc {{ $account['label'] }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.buff-accounts.probe', $account['label']) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
