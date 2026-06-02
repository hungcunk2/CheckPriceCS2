@extends('layouts.admin')

@section('title', 'Buff163 & nguồn kho')
@section('page-title', 'Buff163 & nguồn kho')

@section('content')
<div id="admin-probe-toast" class="position-fixed top-0 end-0 p-3" style="z-index: 1090; max-width: 420px;"></div>
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
                    <input type="text" inputmode="decimal" pattern="[0-9]*[.,]?[0-9]*" class="form-control font-monospace" id="empire_coin_to_usd" name="empire_coin_to_usd"
                           value="{{ old('empire_coin_to_usd', \App\Support\ExchangeRateStore::formatCoinToUsd($coinUsd)) }}" required
                           placeholder="0.615259" autocomplete="off">
                    <span class="input-group-text">USD</span>
                </div>
                <div class="form-text">Tối đa 6 số lẻ (vd. 0.615259). → ₫: <strong id="empire_coin_vnd_preview">{{ number_format($coinVnd) }}</strong>/coin</div>
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
    function roundCoinUsd(value) {
        const n = parseFloat(String(value).replace(',', '.'));
        if (Number.isNaN(n)) return '';
        return (Math.round(n * 1e6) / 1e6).toFixed(6).replace(/\.?0+$/, '');
    }
    function refresh() {
        const usd = parseFloat(String(coinUsd.value).replace(',', '.')) || 0;
        const rate = parseFloat(vndUsd.value) || 0;
        const vnd = Math.round(usd * rate);
        perCoin.textContent = vnd.toLocaleString('vi-VN');
        if (per100) per100.textContent = (vnd * 100).toLocaleString('vi-VN');
    }
    function normalizeCoinUsdField() {
        const rounded = roundCoinUsd(coinUsd.value);
        if (rounded !== '') coinUsd.value = rounded;
        refresh();
    }
    coinUsd.addEventListener('input', refresh);
    coinUsd.addEventListener('blur', normalizeCoinUsdField);
    vndUsd.addEventListener('input', refresh);
})();

(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const toastHost = document.getElementById('admin-probe-toast');

    function escapeHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function formatCheckedAt(iso) {
        if (!iso) return '';
        try {
            const d = new Date(iso);
            const pad = (n) => String(n).padStart(2, '0');
            return `${pad(d.getDate())}/${pad(d.getMonth() + 1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
        } catch (e) {
            return '';
        }
    }

    function showToast(message, type) {
        if (!toastHost) return;
        const div = document.createElement('div');
        div.className = `alert alert-${type} alert-dismissible fade show shadow-sm mb-2`;
        div.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        toastHost.appendChild(div);
        setTimeout(() => div.remove(), 9000);
    }

    function statusBadge(status) {
        const map = {
            ok: ['OK', 'text-bg-success', 'Hoạt động'],
            warning: ['Cảnh báo', 'text-bg-warning', 'Cảnh báo'],
            error: ['Lỗi', 'text-bg-danger', 'Lỗi'],
            blocked: ['403', 'text-bg-danger', 'Bị chặn 403'],
            rate_limited: ['429', 'text-bg-warning', '429'],
            invalid_session: ['Session', 'text-bg-danger', 'Session lỗi'],
        };
        const [short, cls, long] = map[status] || ['—', 'text-bg-secondary', 'Chưa check'];
        return { short, cls, long };
    }

    function renderCheckBlock(result) {
        const b = statusBadge(result?.status);
        let html = `<span class="badge ${b.cls}">${b.short}</span>`;
        html += `<div class="text-muted">${escapeHtml(result?.message ?? '—')}</div>`;
        const at = formatCheckedAt(result?.checked_at);
        if (at) {
            const ms = result?.latency_ms ? ` · ${result.latency_ms} ms` : '';
            html += `<div class="text-muted">${at}${ms}</div>`;
        }
        return html;
    }

    function updateEmpireKeyRow(id, result) {
        const row = document.querySelector(`tr[data-empire-key-id="${id}"]`);
        const cell = row?.querySelector('.empire-key-check-cell');
        if (cell) cell.innerHTML = renderCheckBlock(result);
    }

    function updateCs2CapKeyRow(id, result) {
        const row = document.querySelector(`tr[data-cs2cap-key-id="${id}"]`);
        const cell = row?.querySelector('.cs2cap-key-check-cell');
        if (!cell) return;
        const ok = result?.ok === true;
        const badge = ok
            ? '<span class="badge text-bg-success">OK</span>'
            : '<span class="badge text-bg-danger">Lỗi</span>';
        cell.innerHTML = badge + `<div class="text-muted">${escapeHtml(result?.message ?? '—')}</div>`;
    }

    function updateBuffRow(label, result) {
        const row = document.querySelector(`tr[data-buff-label="${CSS.escape(label)}"]`);
        const checkCell = row?.querySelector('.buff-check-cell');
        const statusCell = row?.querySelector('.buff-status-cell');
        if (checkCell) {
            let html = `<div>${escapeHtml(result?.message ?? '—')}</div>`;
            const at = formatCheckedAt(result?.checked_at);
            if (at) html += `<div class="text-muted">${at}</div>`;
            checkCell.innerHTML = html;
        }
        if (statusCell) {
            const b = statusBadge(result?.status);
            statusCell.innerHTML = `<span class="badge ${b.cls}">${escapeHtml(b.long)}</span>`;
        }
    }

    function updateGlobalBlock(statusId, checkId, result) {
        const b = statusBadge(result?.status);
        const statusEl = statusId ? document.getElementById(statusId) : null;
        const checkEl = checkId ? document.getElementById(checkId) : null;
        if (statusEl) statusEl.innerHTML = `<span class="badge ${b.cls}">${escapeHtml(b.long)}</span>`;
        if (checkEl) {
            let html = `<div>${escapeHtml(result?.message ?? '—')}</div>`;
            const at = formatCheckedAt(result?.checked_at);
            if (at) {
                const ms = result?.latency_ms ? ` · ${result.latency_ms} ms` : '';
                html += `<div class="text-muted">${at}${ms}</div>`;
            }
            checkEl.innerHTML = html;
        }
    }

    document.querySelectorAll('form.js-ajax-probe').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button[type="submit"]');
            const icon = btn?.querySelector('i');
            if (btn) btn.disabled = true;
            if (icon) icon.classList.add('fa-spin');

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(form),
                });
                const json = await res.json().catch(() => ({}));
                const type = json.ok ? 'success' : 'danger';
                showToast(json.message || (json.ok ? 'OK' : 'Lỗi'), type);

                const mode = form.dataset.probeUpdate;
                if (mode === 'empire-key' && json.result) {
                    updateEmpireKeyRow(form.dataset.empireKeyId, json.result);
                } else if (mode === 'cs2cap-key') {
                    updateCs2CapKeyRow(form.dataset.cs2capKeyId, json);
                } else if (mode === 'cs2cap-all' && Array.isArray(json.keys)) {
                    json.keys.forEach((r) => {
                        if (r.cs2cap_key_id) updateCs2CapKeyRow(r.cs2cap_key_id, r);
                    });
                } else if (mode === 'buff' && json.result) {
                    updateBuffRow(form.dataset.buffLabel, json.result);
                } else if (mode === 'empire-global' && json.result) {
                    updateGlobalBlock('empire-global-status', 'empire-global-check', json.result);
                    (json.result.keys || []).forEach((r) => {
                        const rows = document.querySelectorAll('tr[data-empire-key-id]');
                        rows.forEach((tr) => {
                            const label = tr.querySelector('code')?.textContent?.trim();
                            if (label && r.label === label) {
                                updateEmpireKeyRow(tr.dataset.empireKeyId, r);
                            }
                        });
                    });
                } else if (mode === 'all') {
                    if (json.empire) {
                        updateGlobalBlock('empire-global-status', 'empire-global-check', json.empire);
                        (json.empire.keys || []).forEach((r) => {
                            document.querySelectorAll('tr[data-empire-key-id]').forEach((tr) => {
                                const label = tr.querySelector('code')?.textContent?.trim();
                                if (label && r.label === label) updateEmpireKeyRow(tr.dataset.empireKeyId, r);
                            });
                        });
                    }
                    (json.buff || []).forEach((r) => {
                        if (r.label) updateBuffRow(r.label, r);
                    });
                }
            } catch (err) {
                showToast(err.message || 'Lỗi kết nối', 'danger');
            } finally {
                if (btn) btn.disabled = false;
                if (icon) icon.classList.remove('fa-spin');
            }
        });
    });
})();
</script>
@endpush

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
            <form method="POST" action="{{ route('admin.buff-accounts.empire-probe') }}" class="js-ajax-probe" data-probe-update="empire-global">
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
                            <tr data-empire-key-id="{{ $ek->id }}">
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
                                <td class="small empire-key-check-cell">
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
                                    <form method="POST" action="{{ route('admin.buff-accounts.empire-keys.probe', $ek->id) }}"
                                          class="d-inline js-ajax-probe" data-probe-update="empire-key" data-empire-key-id="{{ $ek->id }}"
                                          title="Kiểm tra key này">
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
                ≈ ${{ \App\Support\ExchangeRateStore::formatCoinToUsd((float) ($empire['coin_to_usd'] ?? 0)) }}
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
            <dd class="col-sm-9" id="empire-global-status"><span class="badge {{ $empireBadgeClass }}">{{ $empireStatusLabel }}</span></dd>
            <dt class="col-sm-3">Lần check</dt>
            <dd class="col-sm-9" id="empire-global-check">
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

<div class="panel-admin rounded border mb-4" id="cs2cap-keys">
    <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h6 mb-0">CS2Cap — API key (lấy kho + giá Buff)</h2>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.buff-accounts.cs2cap-keys.create') }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus me-1"></i> Thêm API key
            </a>
            @if(($cs2capUsesDatabase ?? false) && filled(config('cs2price.cs2cap_api_key')))
                <form method="POST" action="{{ route('admin.buff-accounts.cs2cap-keys.import-env') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Import key từ .env</button>
                </form>
            @endif
            @if(($cs2capUsesDatabase ?? false) && ($cs2capKeys ?? collect())->isNotEmpty())
                <form method="POST" action="{{ route('admin.buff-accounts.cs2cap-keys.probe-all') }}" class="js-ajax-probe" data-probe-update="cs2cap-all">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Kiểm tra tất cả key trong bảng">
                        <i class="fas fa-sync-alt me-1"></i> Kiểm tra tất cả key
                    </button>
                </form>
            @endif
        </div>
    </div>
    <div class="p-3">
        @if(($cs2capUsesDatabase ?? false) && ($cs2capKeys ?? collect())->isNotEmpty())
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
                    @foreach($cs2capKeys as $k)
                        @php $cooldown = $k->cooldown_seconds ?? null; @endphp
                        <tr data-cs2cap-key-id="{{ $k->id }}">
                            <td><code>{{ $k->label }}</code></td>
                            <td class="font-monospace small text-muted">{{ $k->api_key_hint }}</td>
                            <td>{{ $k->sort_order }}</td>
                            <td>
                                @if($k->is_active)
                                    <span class="badge text-bg-success">Bật</span>
                                @else
                                    <span class="badge text-bg-secondary">Tắt</span>
                                @endif
                                @if($cooldown)
                                    <span class="badge text-bg-warning text-dark ms-1" title="Cooldown">⏳ {{ $cooldown }}s</span>
                                @endif
                            </td>
                            <td class="small cs2cap-key-check-cell text-muted">Chưa check</td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('admin.buff-accounts.cs2cap-keys.probe', $k->id) }}"
                                      class="d-inline js-ajax-probe" data-probe-update="cs2cap-key" data-cs2cap-key-id="{{ $k->id }}"
                                      title="Kiểm tra key này">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                                <a href="{{ route('admin.buff-accounts.cs2cap-keys.edit', $k->id) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('admin.buff-accounts.cs2cap-keys.destroy', $k->id) }}" class="d-inline" onsubmit="return confirm('Xóa key {{ $k->label }}?');">
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
        @elseif(($cs2capUsesDatabase ?? false))
            <p class="small text-muted mb-3">Chưa có API key — bấm <strong>Thêm API key</strong>.</p>
        @else
            <p class="small text-muted mb-3">Key đang lấy từ <code>.env</code> — chạy <code>php artisan migrate</code> rồi quản lý key trong admin.</p>
        @endif

        <dl class="row mb-0 small">
            <dt class="col-sm-3">Bật CS2Cap</dt>
            <dd class="col-sm-9">{{ config('cs2price.cs2cap_enabled', false) ? 'CS2CAP_ENABLED=true' : 'Tắt — bật CS2CAP_ENABLED trong .env' }}</dd>
            <dt class="col-sm-3">Dùng lấy kho</dt>
            <dd class="col-sm-9">{{ config('cs2price.cs2cap_use_inventory', false) ? 'CS2CAP_USE_INVENTORY=true' : 'Tắt' }}</dd>
            <dt class="col-sm-3">Dùng giá Buff</dt>
            <dd class="col-sm-9">{{ config('cs2price.cs2cap_use_buff', false) ? 'CS2CAP_USE_BUFF=true' : 'Tắt' }}</dd>
            <dt class="col-sm-3">Currency Buff</dt>
            <dd class="col-sm-9"><code>{{ config('cs2price.cs2cap_buff_currency', 'CNY') }}</code></dd>
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
        <form method="POST" action="{{ route('admin.buff-accounts.probe-all') }}" class="js-ajax-probe" data-probe-update="all">
            @csrf
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-stethoscope me-1"></i> Kiểm tra Buff
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
                        <tr data-buff-label="{{ $account['label'] }}">
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
                            <td class="small buff-check-cell">
                                @if($check)
                                    <div>{{ $check['message'] ?? '—' }}</div>
                                    <div class="text-muted">{{ \Carbon\Carbon::parse($check['checked_at'])->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}</div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="buff-status-cell">
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
                                <form method="POST" action="{{ route('admin.buff-accounts.probe', $account['label']) }}"
                                      class="d-inline js-ajax-probe" data-probe-update="buff" data-buff-label="{{ $account['label'] }}">
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
