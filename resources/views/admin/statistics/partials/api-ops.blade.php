<section id="api" class="section-anchor mb-4">
    <h2 class="h6 text-muted text-uppercase mb-3">CS2Cap & API vận hành</h2>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Acc Buff163</div>
                <div class="fs-4 fw-semibold">{{ $api['buff_accounts'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Key CS2Cap active</div>
                <div class="fs-4 fw-semibold text-success">{{ $api['cs2cap_active'] ?? 0 }}<span class="fs-6 text-muted">/{{ $api['cs2cap_total'] ?? 0 }}</span></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Key hết quota</div>
                <div class="fs-4 fw-semibold {{ ($api['cs2cap_exhausted'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $api['cs2cap_exhausted'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Kho stale &gt;24h</div>
                <div class="fs-4 fw-semibold {{ ($api['stale_inventories'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $api['stale_inventories'] ?? 0 }}</div>
            </div>
        </div>
    </div>

    <div class="panel-admin rounded border">
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="fw-semibold small">Trạng thái key CS2Cap</span>
            <a href="{{ route('admin.buff-accounts.index') }}" class="small">Quản lý key →</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                <tr><th>Label</th><th>Active</th><th>Tier</th><th>Quota</th><th>Trạng thái</th></tr>
                </thead>
                <tbody>
                @forelse($api['cs2cap_keys'] ?? [] as $key)
                    <tr>
                        <td><code>{{ $key['label'] }}</code></td>
                        <td>@if($key['active'])<span class="text-success">Yes</span>@else<span class="text-muted">No</span>@endif</td>
                        <td class="small">{{ $key['tier'] ?? '—' }}</td>
                        <td class="small text-nowrap">
                            @if($key['quota_remaining'] !== null && $key['quota_limit'] !== null)
                                {{ $key['quota_remaining'] }}/{{ $key['quota_limit'] }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if(! empty($key['exhausted']))
                                <span class="badge text-bg-danger">Hết quota</span>
                            @elseif($key['active'])
                                <span class="badge text-bg-success">OK</span>
                            @else
                                <span class="badge text-bg-secondary">Tắt</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted small p-3">Chưa cấu hình key CS2Cap.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
