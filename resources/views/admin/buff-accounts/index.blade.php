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
    $coinUsd = (float) ($rates['empire_coin_to_usd'] ?? 0.6143);
    $coinVnd = (float) ($rates['empire_coin_to_vnd'] ?? \App\Support\ExchangeRateStore::coinToVndFromUsd($coinUsd, $vndUsd));
@endphp

<div id="exchange-rates" class="panel-admin rounded border mb-4">
    <div class="p-3 border-bottom">
        <h2 class="h6 mb-0">Tỷ giá quy đổi</h2>
        <p class="small text-muted mb-0 mt-1">Buff: ¥→₫→$. Empire: <strong>coin→USD</strong>, rồi USD→₫ theo tỷ giá <em>1 USD = ? ₫</em> bên cạnh.</p>
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
                <label class="form-label small fw-semibold" for="empire_coin_to_usd">Empire coin → USD</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">1 coin =</span>
                    <input type="number" step="0.0001" min="0.0001" class="form-control" id="empire_coin_to_usd" name="empire_coin_to_usd"
                           value="{{ old('empire_coin_to_usd', $coinUsd) }}" required>
                    <span class="input-group-text">USD</span>
                </div>
                <div class="form-text">→ ₫: <strong id="empire_coin_vnd_preview">{{ number_format($coinVnd) }}</strong>/coin (× tỷ giá USD)</div>
            </div>
        </div>
        <div class="small text-muted mt-3">
            Xem trước:
            <span class="ms-2">100 coin ≈ <strong>${{ number_format($coinUsd * 100, 2) }}</strong> ≈ <strong id="empire_100_vnd_preview">{{ number_format($coinVnd * 100) }} ₫</strong></span>
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
@push('scripts')
<script>
(function () {
    const coinUsd = document.getElementById('empire_coin_to_usd');
    const vndUsd = document.getElementById('vnd_to_usd');
    const perCoin = document.getElementById('empire_coin_vnd_preview');
    const per100 = document.getElementById('empire_100_vnd_preview');
    if (!coinUsd || !vndUsd || !perCoin) return;
    function refresh() {
        const usd = parseFloat(coinUsd.value) || 0;
        const rate = parseFloat(vndUsd.value) || 0;
        const vnd = Math.round(usd * rate);
        perCoin.textContent = vnd.toLocaleString('vi-VN');
        if (per100) per100.textContent = (vnd * 100).toLocaleString('vi-VN');
    }
    coinUsd.addEventListener('input', refresh);
    vndUsd.addEventListener('input', refresh);
})();
</script>
@endpush

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
        <h2 class="h6 mb-0">CSGOEmpire — API key & giá withdraw</h2>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.buff-accounts.empire-keys.create') }}" class="btn btn-sm btn-outline-warning">
                <i class="fas fa-plus me-1"></i> Thêm API key
            </a>
            @if(!$empireUsesDatabase && filled(config('cs2price.empire_api_key')))
                <form method="POST" action="{{ route('admin.buff-accounts.empire-keys.import-env') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Import key từ .env</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.buff-accounts.empire-probe') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-warning" title="Kiểm tra tất cả key trong bảng">
                    <i class="fas fa-sync-alt me-1"></i> Kiểm tra tất cả key
                </button>
            </form>
        </div>
    </div>
    <div class="p-3">
        @if($empireUsesDatabase && $empireKeys->isNotEmpty())
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Label</th>
                            <th>API key</th>
                            <th>Ưu tiên</th>
                            <th>Trạng thái</th>
                            <th>Lần check</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($empireKeys as $ek)
                            @php
                                $ekCheck = $ek->last_check ?? null;
                                $ekStatus = is_array($ekCheck) ? ($ekCheck['status'] ?? null) : null;
                                $ekBadge = match ($ekStatus) {
                                    'ok' => 'text-bg-success',
                                    'error' => 'text-bg-danger',
                                    default => 'text-bg-secondary',
                                };
                                $ekCooldown = $ek->cooldown_seconds ?? null;
                            @endphp
                            <tr>
                                <td><code>{{ $ek->label }}</code></td>
                                <td class="font-monospace small text-muted">{{ $ek->api_key_hint }}</td>
                                <td>{{ $ek->sort_order }}</td>
                                <td>
                                    @if($ek->is_active)
                                        <span class="badge text-bg-success">Bật</span>
                                    @else
                                        <span class="badge text-bg-secondary">Tắt</span>
                                    @endif
                                    @if($ekCooldown)
                                        <span class="badge text-bg-warning text-dark ms-1" title="Cooldown">⏳ {{ $ekCooldown }}s</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($ekCheck)
                                        <span class="badge {{ $ekBadge }}">{{ $ekStatus === 'ok' ? 'OK' : 'Lỗi' }}</span>
                                        <div class="text-muted">{{ \Illuminate\Support\Str::limit($ekCheck['message'] ?? '—', 48) }}</div>
                                        @if(!empty($ekCheck['checked_at']))
                                            <div class="text-muted">{{ \Carbon\Carbon::parse($ekCheck['checked_at'])->timezone('Asia/Ho_Chi_Minh')->format('d/m H:i') }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">Chưa check</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <form method="POST" action="{{ route('admin.buff-accounts.empire-keys.probe', $ek->id) }}" class="d-inline" title="Kiểm tra key này">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                    <a href="{{ route('admin.buff-accounts.empire-keys.edit', $ek->id) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                    <form method="POST" action="{{ route('admin.buff-accounts.empire-keys.destroy', $ek->id) }}" class="d-inline" onsubmit="return confirm('Xóa key {{ $ek->label }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($empireUsesDatabase)
            <p class="small text-muted mb-3">Chưa có API key — bấm <strong>Thêm API key</strong>.</p>
        @else
            <p class="small text-muted mb-3">Key đang lấy từ <code>.env</code> — chạy <code>php artisan migrate</code> rồi <strong>Import key từ .env</strong> để quản lý trong admin.</p>
        @endif

        <dl class="row mb-0 small">
            <dt class="col-sm-3">Bật giá Empire</dt>
            <dd class="col-sm-9">{{ ($empire['enabled'] ?? false) ? 'EMPIRE_ENABLED=true' : 'Tắt — bật EMPIRE_ENABLED trong .env' }}</dd>
            <dt class="col-sm-3">Key hoạt động</dt>
            <dd class="col-sm-9">
                @if(($empire['configured'] ?? false))
                    {{ $empire['api_keys_available'] ?? 0 }}/{{ $empire['api_key_count'] ?? 0 }} sẵn sàng
                @else
                    <span class="text-warning">Chưa có key</span>
                @endif
            </dd>
            <dt class="col-sm-3">1 coin Empire</dt>
            <dd class="col-sm-9">
                ≈ ${{ number_format($empire['coin_to_usd'] ?? 0, 4) }}
                → {{ number_format($empire['coin_to_vnd'] ?? 0) }} ₫
                <span class="text-muted small">(USD × {{ number_format($rates['vnd_to_usd'] ?? 26700) }} ₫)</span>
            </dd>
            <dt class="col-sm-3">Chế độ lấy giá</dt>
            <dd class="col-sm-9">
                <code>{{ config('cs2price.empire_fetch_mode', 'auto') }}</code>
                — bulk song song khi ≥2 key;
                ⟳ admin ≈ {{ config('cs2price.empire_http_max_pages', 12) }} trang/key
                + ~{{ config('cs2price.empire_http_max_searches_per_key', 10) }} search/key
            </dd>
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
