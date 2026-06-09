<section id="sync" class="section-anchor mb-4">
    <h2 class="h6 text-muted text-uppercase mb-3">Chất lượng sync & giá</h2>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Kho stale (&gt;24h)</div>
                <div class="fs-4 fw-semibold {{ ($sync['stale_over_24h'] ?? 0) > 0 ? 'text-warning' : 'text-success' }}">{{ $sync['stale_over_24h'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Sync trễ (quá chu kỳ)</div>
                <div class="fs-4 fw-semibold {{ ($sync['overdue_sync'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $sync['overdue_sync'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Skin có giá Empire</div>
                <div class="fs-4 fw-semibold">{{ number_format($sync['empire_priced_skins'] ?? 0) }}</div>
                <div class="small text-muted">{{ $sync['empire_coverage_pct'] ?? 0 }}% tổng skin</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Nên bán Buff / Empire</div>
                <div class="fs-5 fw-semibold">
                    <span class="text-primary">{{ number_format($sync['buff_sell_wins'] ?? 0) }}</span>
                    <span class="text-muted mx-1">/</span>
                    <span class="text-info">{{ number_format($sync['empire_sell_wins'] ?? 0) }}</span>
                </div>
                <div class="small text-muted">skin (best_sell_venue)</div>
            </div>
        </div>
    </div>

    <div class="panel-admin rounded border">
        <div class="p-3 border-bottom fw-semibold small">Kho thiếu giá / failed cao</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Kho</th>
                    <th class="text-end">Có giá</th>
                    <th class="text-end">Failed</th>
                    <th class="text-end">Coverage</th>
                    <th>Sync lần cuối</th>
                </tr>
                </thead>
                <tbody>
                @forelse($sync['worst_inventories'] ?? [] as $row)
                    <tr>
                        <td><a href="{{ route('admin.inventories.edit', $row['id']) }}">{{ $row['label'] }}</a></td>
                        <td class="text-end">{{ $row['priced_count'] }}/{{ $row['item_count'] }}</td>
                        <td class="text-end {{ $row['failed_count'] > 0 ? 'text-danger' : '' }}">{{ $row['failed_count'] }}</td>
                        <td class="text-end">{{ number_format($row['coverage_pct'], 1, ',', '.') }}%</td>
                        <td class="small text-nowrap">{{ $row['last_checked_at'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted small p-3">Tất cả kho đều có coverage tốt.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
