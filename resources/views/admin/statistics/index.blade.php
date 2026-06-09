@extends('layouts.admin')

@section('title', 'Dashboard thống kê')
@section('page-title', 'Dashboard thống kê')

@push('styles')
<style>
    .stat-card { min-height: 100px; }
    .stat-card .stat-value { font-size: 1.35rem; font-weight: 600; line-height: 1.2; }
    .stat-card .stat-sub { font-size: .78rem; }
    .bar-chart-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .45rem; }
    .bar-chart-label { width: 4.5rem; font-size: .78rem; color: var(--bs-secondary-color); flex-shrink: 0; }
    .bar-chart-track { flex: 1; height: 10px; background: rgba(127,127,127,.15); border-radius: 999px; overflow: hidden; }
    .bar-chart-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #0d6efd, #6610f2); }
    .bar-chart-value { width: 5.5rem; text-align: right; font-size: .78rem; white-space: nowrap; }
    .section-anchor { scroll-margin-top: 1rem; }
</style>
@endpush

@section('content')
@php
    $o = $stats['overview'] ?? [];
    $sub = $stats['subscription'] ?? [];
    $sync = $stats['sync_quality'] ?? [];
    $aum = $stats['aum'] ?? [];
    $support = $stats['support'] ?? [];
    $api = $stats['api_ops'] ?? [];
    $fmtVnd = fn ($v) => \App\Support\SubscriptionPlans::formatVnd((int) $v);
    $maxPlan = max(1, max($sub['active_by_plan'] ?? [1]));
    $maxRevenue = max(1, max(array_column($sub['revenue_by_month'] ?? [['amount_vnd' => 1]], 'amount_vnd')));
@endphp

@if(! empty($api['critical_alert']))
    <div class="alert alert-danger py-2 small mb-3">
        <i class="fas fa-triangle-exclamation me-1"></i> {{ $api['alert_message'] }}
    </div>
@endif

<nav class="nav nav-pills flex-wrap gap-1 mb-3 small align-items-center">
    <a class="nav-link py-1 px-2" href="#overview">Tổng quan</a>
    <a class="nav-link py-1 px-2" href="#subscription">Gói & doanh thu</a>
    <a class="nav-link py-1 px-2" href="#sync">Sync & giá</a>
    <a class="nav-link py-1 px-2" href="#aum">Tài sản</a>
    <a class="nav-link py-1 px-2" href="#support">Hỗ trợ</a>
    <a class="nav-link py-1 px-2" href="#api">CS2Cap / API</a>
    <a class="nav-link py-1 px-2" href="{{ route('admin.portfolio-report.index') }}">Thống kê kho →</a>
    <span class="ms-auto">
        @include('admin.partials.export-buttons', [
            'csvUrl' => url('/admin/thong-ke/export/csv'),
            'pdfUrl' => url('/admin/thong-ke/export/pdf'),
        ])
    </span>
</nav>

{{-- 1. Overview --}}
<section id="overview" class="section-anchor mb-4">
    <h2 class="h6 text-muted text-uppercase mb-3">Tổng quan vận hành</h2>
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-4">
            <div class="panel-admin rounded border p-3 stat-card">
                <div class="text-muted small">User trả phí (active)</div>
                <div class="stat-value">{{ number_format($o['paid_active_users'] ?? 0) }}</div>
                <div class="stat-sub text-warning">
                    Hết hạn ≤7 ngày: {{ $o['expiring_7_days'] ?? 0 }}
                    · ≤30 ngày: {{ $o['expiring_30_days'] ?? 0 }}
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="panel-admin rounded border p-3 stat-card">
                <div class="text-muted small">Doanh thu tháng này</div>
                <div class="stat-value">{{ $fmtVnd($o['revenue_month_vnd'] ?? 0) }}</div>
                <div class="stat-sub">
                    Đơn chờ duyệt:
                    @if(($o['pending_orders'] ?? 0) > 0)
                        <a href="{{ route('admin.plan-orders.index', ['status' => 'pending']) }}" class="text-warning fw-medium">{{ $o['pending_orders'] }}</a>
                    @else
                        <span class="text-success">0</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="panel-admin rounded border p-3 stat-card">
                <div class="text-muted small">Tổng giá trị kho (Buff VND)</div>
                <div class="stat-value">{{ $fmtVnd($o['total_inventory_vnd'] ?? 0) }}</div>
                <div class="stat-sub">Admin: {{ $o['admin_inventories'] ?? 0 }} · Member: {{ $o['member_inventories'] ?? 0 }} kho</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="panel-admin rounded border p-3 stat-card">
                <div class="text-muted small">Kho sync trễ (quá chu kỳ gói)</div>
                <div class="stat-value {{ ($o['overdue_sync'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $o['overdue_sync'] ?? 0 }}</div>
                <div class="stat-sub">Pro 8h · Plus 4h · Max 2h · Admin 1h</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="panel-admin rounded border p-3 stat-card">
                <div class="text-muted small">Tỷ lệ skin có giá Buff</div>
                <div class="stat-value">{{ $o['pricing_coverage_pct'] !== null ? number_format($o['pricing_coverage_pct'], 1, ',', '.').'%' : '—' }}</div>
                <div class="stat-sub">{{ number_format($o['pricing_priced_items'] ?? 0) }} / {{ number_format($o['pricing_total_items'] ?? 0) }} skin</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-4">
            <div class="panel-admin rounded border p-3 stat-card">
                <div class="text-muted small">MRR ước tính</div>
                <div class="stat-value">{{ $fmtVnd($sub['mrr_estimate_vnd'] ?? 0) }}</div>
                <div class="stat-sub">Tổng giá gói 1 tháng × user active</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="panel-admin rounded border p-3 h-100">
                <div class="fw-semibold mb-2 small">User active theo gói</div>
                @foreach($sub['active_by_plan'] ?? [] as $plan => $count)
                    @php $planName = \App\Support\SubscriptionPlans::get($plan)['name'] ?? strtoupper($plan); @endphp
                    <div class="bar-chart-row">
                        <div class="bar-chart-label">{{ $planName }}</div>
                        <div class="bar-chart-track"><div class="bar-chart-fill" style="width: {{ min(100, ($count / $maxPlan) * 100) }}%"></div></div>
                        <div class="bar-chart-value">{{ $count }} user</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel-admin rounded border p-3 h-100">
                <div class="fw-semibold mb-2 small">Doanh thu 6 tháng (đơn đã duyệt)</div>
                @forelse($sub['revenue_by_month'] ?? [] as $row)
                    <div class="bar-chart-row">
                        <div class="bar-chart-label">{{ $row['label'] }}</div>
                        <div class="bar-chart-track"><div class="bar-chart-fill" style="width: {{ min(100, ($row['amount_vnd'] / $maxRevenue) * 100) }}%; opacity:.85"></div></div>
                        <div class="bar-chart-value">{{ $fmtVnd($row['amount_vnd']) }}</div>
                    </div>
                @empty
                    <p class="text-muted small mb-0">Chưa có đơn thanh toán.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>

@include('admin.statistics.partials.subscription')
@include('admin.statistics.partials.sync-quality')
@include('admin.statistics.partials.aum')
@include('admin.statistics.partials.support')
@include('admin.statistics.partials.api-ops')
@endsection
