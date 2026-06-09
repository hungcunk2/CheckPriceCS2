<section id="aum" class="section-anchor mb-4">
    <h2 class="h6 text-muted text-uppercase mb-3">Tài sản (AUM)</h2>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Kho public</div>
                <div class="fw-semibold">{{ $aum['public']['count'] ?? 0 }} kho</div>
                <div class="small">{{ \App\Support\SubscriptionPlans::formatVnd($aum['public']['total_vnd'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Kho private</div>
                <div class="fw-semibold">{{ $aum['private']['count'] ?? 0 }} kho</div>
                <div class="small">{{ \App\Support\SubscriptionPlans::formatVnd($aum['private']['total_vnd'] ?? 0) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Phân loại vũ khí</div>
                <div class="small text-muted">{{ count($aum['weapon_stats'] ?? []) }} loại có skin</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel-admin rounded border">
                <div class="p-3 border-bottom fw-semibold small">Top 10 kho giá trị cao nhất</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>#</th><th>Kho</th><th>Loại</th><th class="text-end">VND</th><th class="text-end">CNY</th><th class="text-end">Skin</th></tr></thead>
                        <tbody>
                        @foreach($aum['top_inventories'] ?? [] as $i => $row)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <a href="{{ route('admin.inventories.edit', $row['id']) }}">{{ $row['label'] }}</a>
                                    @if($row['is_public'])<span class="badge text-bg-info ms-1">Public</span>@endif
                                </td>
                                <td class="small">{{ $row['owner'] }}</td>
                                <td class="text-end text-nowrap">{{ \App\Support\SubscriptionPlans::formatVnd($row['total_vnd']) }}</td>
                                <td class="text-end">{{ number_format($row['total_cny'], 2, ',', '.') }} ¥</td>
                                <td class="text-end">{{ $row['item_count'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel-admin rounded border p-3 h-100">
                <div class="fw-semibold small mb-2">Phân bổ loại skin (tất cả kho)</div>
                @php $maxWeapon = max(1, max(array_column($aum['weapon_stats'] ?? [['count' => 1]], 'count'))); @endphp
                @forelse($aum['weapon_stats'] ?? [] as $row)
                    <div class="bar-chart-row">
                        <div class="bar-chart-label" title="{{ $row['label'] }}">{{ \Illuminate\Support\Str::limit($row['label'], 8) }}</div>
                        <div class="bar-chart-track"><div class="bar-chart-fill" style="width: {{ min(100, ($row['count'] / $maxWeapon) * 100) }}%; background: #198754"></div></div>
                        <div class="bar-chart-value">{{ $row['count'] }}</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Chưa có dữ liệu skin.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
