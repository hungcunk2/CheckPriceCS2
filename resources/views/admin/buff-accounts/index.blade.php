@extends('layouts.admin')

@section('title', 'Buff163 & cs.trade')
@section('page-title', 'Buff163 & nguồn kho cs.trade')

@section('content')
@php
    $csCheck = $csTrade['last_check'] ?? null;
    $csStatus = $csCheck['status'] ?? null;
    $csBadgeClass = match ($csStatus) {
        'ok' => 'text-bg-success',
        'error' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
    $csStatusLabel = match ($csStatus) {
        'ok' => 'Hoạt động',
        'error' => 'Lỗi',
        default => 'Chưa check',
    };
@endphp

<div class="panel-admin rounded border mb-4">
    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2 class="h6 mb-1">cs.trade — lấy danh sách kho</h2>
            <p class="small text-muted mb-0">
                Thử <code>cs.trade</code> trước
                @if($csTrade['fallback_steam'] ?? true)
                    , lỗi thì fallback Steam
                @endif
                · Probe: <code>{{ $csTrade['probe_steam_id'] ?? '—' }}</code>
            </p>
        </div>
        <form method="POST" action="{{ route('admin.buff-accounts.cstrade-probe') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-sync-alt me-1"></i> Kiểm tra cs.trade
            </button>
        </form>
    </div>
    <div class="p-3">
        <dl class="row mb-0 small">
            <dt class="col-sm-3">API</dt>
            <dd class="col-sm-9 font-monospace text-break">{{ $csTrade['api_url'] ?? '—' }}</dd>
            <dt class="col-sm-3">Trạng thái</dt>
            <dd class="col-sm-9"><span class="badge {{ $csBadgeClass }}">{{ $csStatusLabel }}</span></dd>
            <dt class="col-sm-3">Lần check</dt>
            <dd class="col-sm-9">
                @if($csCheck)
                    <div>{{ $csCheck['message'] ?? '—' }}</div>
                    <div class="text-muted">
                        {{ \Carbon\Carbon::parse($csCheck['checked_at'])->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                        @if(!empty($csCheck['latency_ms']))
                            · {{ $csCheck['latency_ms'] }} ms
                        @endif
                    </div>
                @else
                    <span class="text-muted">—</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

<div class="d-flex justify-content-end align-items-center mb-3 flex-wrap gap-2">
    @if(!$usesDatabase && $configured)
        <form method="POST" action="{{ route('admin.buff-accounts.import-env') }}">
            @csrf
            <button type="submit" class="btn btn-outline-dark">
                <i class="fas fa-file-import me-1"></i> Import từ .env
            </button>
        </form>
    @endif
    @if($usesDatabase)
        <a href="{{ route('admin.buff-accounts.create') }}" class="btn btn-outline-primary">
            <i class="fas fa-plus me-1"></i> Thêm acc
        </a>
    @endif
    @if($configured)
        <form method="POST" action="{{ route('admin.buff-accounts.probe-all') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-stethoscope me-1"></i> Kiểm tra cs.trade + Buff
            </button>
        </form>
    @endif
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
                                    <a href="{{ route('admin.buff-accounts.edit', $managed->id) }}" class="btn btn-sm btn-outline-secondary" title="Sửa">
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
@else
    <div class="text-muted">Chưa có acc Buff.</div>
@endif
@endsection
