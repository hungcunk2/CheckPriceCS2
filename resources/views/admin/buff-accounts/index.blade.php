@extends('layouts.admin')

@section('title', 'Buff163 & cs.trade')
@section('page-title', 'Buff163 & nguồn kho cs.trade')

@section('content')
@if($empireEnabled ?? false)
    <div class="alert alert-info py-2 small mb-3">
        Empire đang <strong>bật</strong> trên site. Kho đồngng bộ trước đó có thể chưa có cột giá Empire — chạy lại <strong>Đồng bộ giá</strong> từng kho hoặc cron.
    </div>
@else
    <div class="alert alert-warning py-2 small mb-3">
        Empire đang <strong>tắt</strong> — thêm <code>EMPIRE_ENABLED=true</code> và <code>CSGOEMPIRE_API_KEY</code> vào <code>.env</code>, rồi <code>php artisan config:clear</code> (hoặc deploy lại).
    </div>
@endif
@php
    $rates = $exchangeRates ?? [];
    $cnyVnd = (float) ($rates['cny_to_vnd'] ?? 3750);
    $vndUsd = (float) ($rates['vnd_to_usd'] ?? 26700);
    $coinVnd = (float) ($rates['empire_coin_to_vnd'] ?? 16401);
    $coinUsd = (float) ($rates['empire_coin_to_usd'] ?? 0.6143);
@endphp

<div class="panel-admin rounded border mb-4">
    <div class="p-3 border-bottom">
        <h2 class="h6 mb-0">Tỷ giá quy đổi</h2>
        <p class="small text-muted mb-0 mt-1">Dùng cho Buff (¥→₫→$) và Empire (coin→₫). Lưu trong database — ưu tiên hơn .env.</p>
    </div>
    <form method="POST" action="{{ route('admin.buff-accounts.exchange-rates') }}" class="p-3">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small fw-semibold" for="cny_to_vnd">CNY → VND</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">1 ¥ =</span>
                    <input type="number" step="0.01" min="1" class="form-control" id="cny_to_vnd" name="cny_to_vnd"
                           value="{{ old('cny_to_vnd', $cnyVnd) }}" required>
                    <span class="input-group-text">₫</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold" for="vnd_to_usd">VND → USD</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">1 USD =</span>
                    <input type="number" step="0.01" min="1" class="form-control" id="vnd_to_usd" name="vnd_to_usd"
                           value="{{ old('vnd_to_usd', $vndUsd) }}" required>
                    <span class="input-group-text">₫</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold" for="empire_coin_to_vnd">Empire coin → VND</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">1 coin =</span>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="empire_coin_to_vnd" name="empire_coin_to_vnd"
                           value="{{ old('empire_coin_to_vnd', $coinVnd) }}" required>
                    <span class="input-group-text">₫</span>
                </div>
            </div>
        </div>
        <div class="small text-muted mt-3">
            Xem trước:
            <span class="ms-2">100 coin Empire ≈ <strong>{{ number_format($coinVnd * 100) }} ₫</strong> (≈ ${{ number_format($coinUsd * 100, 2) }})</span>
            <span class="ms-2">· 100 ¥ ≈ <strong>{{ number_format($cnyVnd * 100) }} ₫</strong></span>
            @if(($rates['source'] ?? '') === 'config')
                <span class="badge text-bg-secondary ms-1">đang dùng .env — chạy migrate để lưu DB</span>
            @endif
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fas fa-save me-1"></i> Lưu tỷ giá
            </button>
        </div>
    </form>
</div>

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

@php
    $empireCheck = $empire['last_check'] ?? null;
    $empireStatus = $empireCheck['status'] ?? null;
    $empireBadgeClass = match ($empireStatus) {
        'ok' => 'text-bg-success',
        'error' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
    $empireStatusLabel = match ($empireStatus) {
        'ok' => 'Hoạt động',
        'error' => 'Lỗi',
        default => 'Chưa check',
    };
@endphp

<div class="panel-admin rounded border mb-4">
    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">CSGOEmpire — giá withdraw market</h2>
        <form method="POST" action="{{ route('admin.buff-accounts.empire-probe') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-warning">
                <i class="fas fa-sync-alt me-1"></i> Kiểm tra Empire
            </button>
        </form>
    </div>
    <div class="p-3">
        <dl class="row mb-0 small">
            <dt class="col-sm-3">Bật</dt>
            <dd class="col-sm-9">{{ ($empire['enabled'] ?? false) ? 'EMPIRE_ENABLED=true' : 'Tắt — bật trong .env' }}</dd>
            <dt class="col-sm-3">API key</dt>
            <dd class="col-sm-9">{{ ($empire['configured'] ?? false) ? 'Đã cấu hình' : 'Thiếu CSGOEMPIRE_API_KEY' }}</dd>
            <dt class="col-sm-3">1 coin → VND</dt>
            <dd class="col-sm-9">{{ number_format($empire['coin_to_vnd'] ?? 0) }} ₫ (≈ ${{ number_format($empire['coin_to_usd'] ?? 0, 4) }})</dd>
            <dt class="col-sm-3">Giới hạn/lần tra</dt>
            <dd class="col-sm-9">{{ $empire['max_fetches'] ?? '—' }} item mới (cache giảm gọi API)</dd>
            <dt class="col-sm-3">Trạng thái</dt>
            <dd class="col-sm-9"><span class="badge {{ $empireBadgeClass }}">{{ $empireStatusLabel }}</span></dd>
            <dt class="col-sm-3">Lần check</dt>
            <dd class="col-sm-9">
                @if($empireCheck)
                    <div>{{ $empireCheck['message'] ?? '—' }}</div>
                    <div class="text-muted">
                        {{ \Carbon\Carbon::parse($empireCheck['checked_at'])->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                        @if(!empty($empireCheck['latency_ms']))
                            · {{ $empireCheck['latency_ms'] }} ms
                        @endif
                    </div>
                @else
                    <span class="text-muted">—</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

<div class="panel-admin rounded border mb-4">
    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">cs.trade — lấy danh sách kho</h2>
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
