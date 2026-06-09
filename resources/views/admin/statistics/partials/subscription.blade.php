<section id="subscription" class="section-anchor mb-4">
    <h2 class="h6 text-muted text-uppercase mb-3">Báo cáo gói & doanh thu</h2>
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Đơn pending &gt; 24h</div>
                <div class="fs-4 fw-semibold {{ ($sub['pending_over_24h'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $sub['pending_over_24h'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">Đơn pending &gt; 48h</div>
                <div class="fs-4 fw-semibold {{ ($sub['pending_over_48h'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $sub['pending_over_48h'] ?? 0 }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel-admin rounded border p-3">
                <div class="text-muted small">MRR ước tính</div>
                <div class="fs-4 fw-semibold">{{ \App\Support\SubscriptionPlans::formatVnd($sub['mrr_estimate_vnd'] ?? 0) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel-admin rounded border">
                <div class="p-3 border-bottom fw-semibold small">User sắp hết hạn (7 ngày tới)</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>User</th><th>Gói</th><th>Hết hạn</th></tr></thead>
                        <tbody>
                        @forelse($sub['expiring_this_week'] ?? [] as $user)
                            @php $planLabel = \App\Support\SubscriptionPlans::get($user->subscription_plan)['name'] ?? strtoupper($user->subscription_plan ?? ''); @endphp
                            <tr>
                                <td>
                                    <div>{{ $user->name }}</div>
                                    <div class="small text-muted">{{ $user->email }}</div>
                                </td>
                                <td>{{ $planLabel }}</td>
                                <td class="text-nowrap small">{{ $user->paid_until?->timezone(config('cs2price.timezone'))->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted small p-3">Không có user sắp hết hạn tuần này.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel-admin rounded border">
                <div class="p-3 border-bottom fw-semibold small">Đơn chờ duyệt</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light"><tr><th>Thời gian</th><th>User</th><th>Gói</th><th class="text-end">Tiền</th></tr></thead>
                        <tbody>
                        @forelse($sub['pending_orders'] ?? [] as $order)
                            @php $planLabel = \App\Support\SubscriptionPlans::get($order->plan)['name'] ?? strtoupper($order->plan); @endphp
                            <tr>
                                <td class="small text-nowrap">{{ $order->created_at->format('d/m H:i') }}</td>
                                <td class="small">{{ $order->user?->email ?? '—' }}</td>
                                <td>{{ $planLabel }} ×{{ $order->months }}</td>
                                <td class="text-end text-nowrap">{{ \App\Support\SubscriptionPlans::formatVnd($order->amount_vnd) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted small p-3">Không có đơn pending.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if(($sub['pending_orders'] ?? collect())->isNotEmpty())
                    <div class="p-2 border-top text-end">
                        <a href="{{ route('admin.plan-orders.index', ['status' => 'pending']) }}" class="small">Xem tất cả đơn →</a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
