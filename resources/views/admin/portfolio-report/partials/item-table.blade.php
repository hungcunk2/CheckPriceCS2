<div class="panel-admin rounded border h-100">
    <div class="p-3 border-bottom d-flex align-items-center gap-2">
        <i class="fas {{ $icon ?? 'fa-list' }}"></i>
        <strong>{{ $title }}</strong>
        <span class="badge text-bg-secondary ms-auto">{{ count($rows) }}</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            @if(($mode ?? '') === 'mover')
                <thead class="table-light">
                <tr>
                    <th>Skin</th>
                    <th class="text-end">Giá hiện tại</th>
                    <th class="text-end">Biến động</th>
                    <th>Kho</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    @php
                        $delta = (float) ($row['delta_cny'] ?? 0);
                        $cls = $delta >= 0 ? 'text-success' : 'text-danger';
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $row['display_name'] ?? $row['market_hash_name'] }}</div>
                            @if(($row['amount'] ?? 1) > 1)
                                <div class="small text-muted">×{{ (int) $row['amount'] }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            {{ number_format((float) ($row['current_cny'] ?? 0), 2, ',', '.') }} ¥
                        </td>
                        <td class="text-end text-nowrap {{ $cls }}">
                            {{ ($delta >= 0 ? '+' : '') . number_format($delta, 2, ',', '.') }} ¥
                            @if(isset($row['delta_pct']) && $row['delta_pct'] !== null)
                                <div class="small">({{ ($row['delta_pct'] >= 0 ? '+' : '') . number_format((float) $row['delta_pct'], 2, ',', '.') }}%)</div>
                            @endif
                        </td>
                        <td class="small">{{ $row['inventory_label'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted small p-3">{{ $empty }}</td></tr>
                @endforelse
                </tbody>
            @else
                <thead class="table-light">
                <tr>
                    <th>Skin</th>
                    <th class="text-end">Giá trị Buff</th>
                    <th class="text-end">Empire ¥</th>
                    <th>Kho</th>
                </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <div class="fw-medium">{{ $row['display_name'] ?? $row['market_hash_name'] }}</div>
                            @if(($row['amount'] ?? 1) > 1)
                                <div class="small text-muted">×{{ (int) $row['amount'] }}</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if(isset($row['line_total_cny']))
                                {{ number_format((float) $row['line_total_cny'], 2, ',', '.') }} ¥
                            @elseif(isset($row['buff_price_cny']))
                                {{ number_format((float) $row['buff_price_cny'], 2, ',', '.') }} ¥
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if(isset($row['line_total_empire_cny']))
                                {{ number_format((float) $row['line_total_empire_cny'], 2, ',', '.') }} ¥
                            @else
                                —
                            @endif
                        </td>
                        <td class="small">{{ $row['inventory_label'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted small p-3">{{ $empty }}</td></tr>
                @endforelse
                </tbody>
            @endif
        </table>
    </div>
</div>
