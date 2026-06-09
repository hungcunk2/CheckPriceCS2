@extends('layouts.admin')

@section('title', 'Thống kê kho')
@section('page-title', 'Thống kê kho')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
@endpush

@section('content')
@php
    $summary = $report['summary'] ?? [];
    $current = $summary['current'] ?? [];
    $delta = $summary['delta'] ?? null;
    $deltaPct = $summary['delta_pct'] ?? null;
    $missingPast = ! empty($summary['missing_past']);

    $fmtPct = function ($v) {
        if ($v === null) {
            return '—';
        }
        $sign = $v > 0 ? '+' : '';
        return $sign . number_format($v, 2, ',', '.') . '%';
    };
    $fmtCny = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . ' ¥';
    $fmtSignedCny = function ($v) {
        if ($v === null) {
            return '—';
        }
        $sign = $v > 0 ? '+' : '';
        return $sign . number_format((float) $v, 2, ',', '.') . ' ¥';
    };
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted small mb-0">
        <strong>Chỉ kho của tài khoản admin đang đăng nhập</strong>
        ({{ (int) ($report['scope']['inventory_count'] ?? 0) }} kho): tổng giá trị Buff/Empire, skin tăng/giảm, thêm/mất.
        Dữ liệu ghi sau mỗi lần sync. Vận hành toàn site →
        <a href="{{ route('admin.statistics.index') }}">Theo dõi hệ thống</a>.
    </p>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="btn-group btn-group-sm">
            @foreach ([1 => '1 ngày', 7 => '7 ngày', 30 => '30 ngày'] as $d => $label)
                <a href="{{ route('admin.portfolio-report.index', ['days' => $d]) }}"
                   class="btn {{ $days === $d ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
        @include('admin.partials.export-buttons', [
            'csvUrl' => url('/admin/portfolio-report/export/csv?days='.$days),
            'pdfUrl' => url('/admin/portfolio-report/export/pdf?days='.$days),
        ])
    </div>
</div>

@if($missingPast)
    <div class="alert alert-info py-2 small">
        Chưa đủ dữ liệu snapshot cho khoảng <strong>{{ $summary['period_label'] ?? '' }}</strong>.
        Báo cáo đầy đủ sau vài lần đồng bộ (⟳ hoặc cron). Hiện chỉ hiện tổng hiện tại và skin tăng/giảm theo lịch sử giá Buff.
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="panel-admin rounded border p-3 h-100">
            <div class="text-muted small">Tổng Buff (hiện tại)</div>
            <div class="fs-5 fw-semibold">{{ $fmtCny($current['total_cny'] ?? null) }}</div>
            @if($delta)
                <div class="small {{ ($delta['total_cny'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $fmtSignedCny($delta['total_cny'] ?? null) }}
                    @if($deltaPct)<span class="ms-1">({{ $fmtPct($deltaPct['total_cny'] ?? null) }})</span>@endif
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        @include('partials.portfolio-empire-summary', compact('current', 'delta', 'deltaPct') + ['fmtPct' => $fmtPct])
    </div>
    <div class="col-md-3">
        <div class="panel-admin rounded border p-3 h-100">
            <div class="text-muted small">Tổng VND (Buff)</div>
            <div class="fs-5 fw-semibold">{{ isset($current['total_vnd']) ? number_format($current['total_vnd'], 0, ',', '.') . ' ₫' : '—' }}</div>
            @if($delta && isset($delta['total_vnd']))
                @php $dv = (int) $delta['total_vnd']; @endphp
                <div class="small {{ $dv >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ ($dv >= 0 ? '+' : '') . number_format($dv, 0, ',', '.') }} ₫
                </div>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel-admin rounded border p-3 h-100">
            <div class="text-muted small">Số skin (kho của bạn)</div>
            <div class="fs-5 fw-semibold">{{ number_format((int) ($current['item_count'] ?? 0), 0, ',', '.') }}</div>
            @if($delta && isset($delta['item_count']))
                @php $di = (int) $delta['item_count']; @endphp
                <div class="small {{ $di >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ ($di >= 0 ? '+' : '') . $di }} skin
                </div>
            @endif
        </div>
    </div>
</div>

@if(! empty($report['trend']))
    <div class="panel-admin rounded border mb-4">
        <div class="p-3 border-bottom">
            <strong>Biến động tổng giá trị theo ngày</strong>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Ngày</th>
                    <th class="text-end">Buff ¥</th>
                    <th class="text-end">Empire coin</th>
                    <th class="text-end">Buff VND</th>
                </tr>
                </thead>
                <tbody>
                @foreach($report['trend'] as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                        <td class="text-end">{{ $fmtCny($row['total_cny'] ?? null) }}</td>
                        <td class="text-end">
                            @if(isset($row['total_empire_coins']))
                                {{ number_format((float) $row['total_empire_coins'], 2, ',', '.') }} coin
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((int) ($row['total_vnd'] ?? 0), 0, ',', '.') }} ₫</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        @include('admin.portfolio-report.partials.item-table', [
            'title' => 'Skin tăng giá (' . ($summary['period_label'] ?? '') . ')',
            'icon' => 'fa-arrow-trend-up text-success',
            'rows' => $report['gainers'] ?? [],
            'mode' => 'mover',
            'empty' => 'Không có skin tăng giá rõ rệt trong kỳ này.',
        ])
    </div>
    <div class="col-lg-6">
        @include('admin.portfolio-report.partials.item-table', [
            'title' => 'Skin giảm giá (' . ($summary['period_label'] ?? '') . ')',
            'icon' => 'fa-arrow-trend-down text-danger',
            'rows' => $report['losers'] ?? [],
            'mode' => 'mover',
            'empty' => 'Không có skin giảm giá rõ rệt trong kỳ này.',
        ])
    </div>
    <div class="col-lg-6">
        @include('admin.portfolio-report.partials.item-table', [
            'title' => 'Skin mới thêm vào kho',
            'icon' => 'fa-plus-circle text-primary',
            'rows' => $report['added'] ?? [],
            'mode' => 'composition',
            'empty' => 'Không phát hiện skin mới (so với snapshot gần nhất trước đầu kỳ).',
        ])
    </div>
    <div class="col-lg-6">
        @include('admin.portfolio-report.partials.item-table', [
            'title' => 'Skin không còn trong kho',
            'icon' => 'fa-minus-circle text-secondary',
            'rows' => $report['removed'] ?? [],
            'mode' => 'composition',
            'empty' => 'Không phát hiện skin bị bán/chuyển (so với snapshot gần nhất trước đầu kỳ).',
        ])
    </div>
</div>
@endsection
