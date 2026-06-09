<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thống kê kho {{ $days }} ngày</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        h2 { font-size: 11px; margin: 12px 0 5px; border-bottom: 1px solid #ccc; }
        .meta { color: #666; font-size: 9px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #ddd; padding: 2px 4px; text-align: left; }
        th { background: #f0f0f0; }
        td.num { text-align: right; }
        .up { color: #080; }
        .down { color: #c00; }
    </style>
</head>
<body>
@php
    $summary = $report['summary'] ?? [];
    $current = $summary['current'] ?? [];
    $delta = $summary['delta'] ?? [];
    $deltaPct = $summary['delta_pct'] ?? [];
    $fmtVnd = fn ($v) => number_format((int) $v, 0, ',', '.').' đ';
@endphp

<h1>Thống kê kho — {{ $days }} ngày</h1>
<div class="meta">{{ $site_name }} · Xuất lúc: {{ $generated_at }}</div>

<h2>Tổng quan</h2>
<table>
    <tr><th></th><th class="num">Hiện tại</th><th class="num">Biến động</th><th class="num">%</th></tr>
    <tr>
        <td>Buff CNY</td>
        <td class="num">{{ number_format($current['total_cny'] ?? 0, 2, ',', '.') }} ¥</td>
        <td class="num">{{ isset($delta['total_cny']) ? (($delta['total_cny'] >= 0 ? '+' : '').number_format($delta['total_cny'], 2, ',', '.')) : '—' }}</td>
        <td class="num">{{ $deltaPct['total_cny'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Buff VND</td>
        <td class="num">{{ $fmtVnd($current['total_vnd'] ?? 0) }}</td>
        <td class="num">{{ isset($delta['total_vnd']) ? $fmtVnd($delta['total_vnd']) : '—' }}</td>
        <td></td>
    </tr>
    <tr>
        <td>Empire coin</td>
        <td class="num">{{ number_format($current['total_empire_coins'] ?? 0, 2, ',', '.') }} coin</td>
        <td class="num">{{ isset($delta['total_empire_coins']) ? (($delta['total_empire_coins'] >= 0 ? '+' : '').number_format($delta['total_empire_coins'], 2, ',', '.')) : '—' }}</td>
        <td class="num">{{ $deltaPct['total_empire_coins'] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Empire USD</td>
        <td class="num">{{ \App\Support\Currency::formatUsd($current['total_empire_usd'] ?? null) }}</td>
        <td class="num">{{ isset($delta['total_empire_usd']) ? \App\Support\Currency::formatUsd($delta['total_empire_usd']) : '—' }}</td>
        <td></td>
    </tr>
    <tr>
        <td>Số skin</td>
        <td class="num">{{ $current['item_count'] ?? 0 }}</td>
        <td class="num">{{ $delta['item_count'] ?? '—' }}</td>
        <td></td>
    </tr>
</table>

@if(! empty($report['trend']))
<h2>Biến động theo ngày</h2>
<table>
    <tr><th>Ngày</th><th class="num">Buff ¥</th><th class="num">Empire coin</th><th class="num">Empire USD</th><th class="num">VND</th></tr>
    @foreach($report['trend'] as $row)
        <tr>
            <td>{{ $row['date'] }}</td>
            <td class="num">{{ number_format($row['total_cny'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($row['total_empire_coins'] ?? 0, 2, ',', '.') }}</td>
            <td class="num">{{ \App\Support\Currency::formatUsd($row['total_empire_usd'] ?? null) }}</td>
            <td class="num">{{ $fmtVnd($row['total_vnd']) }}</td>
        </tr>
    @endforeach
</table>
@endif

<h2>Skin tăng giá (top {{ min(30, count($report['gainers'] ?? [])) }})</h2>
<table>
    <tr><th>Skin</th><th class="num">Giá ¥</th><th class="num">±¥</th><th class="num">%</th><th>Kho</th></tr>
    @foreach(array_slice($report['gainers'] ?? [], 0, 30) as $row)
        <tr>
            <td>{{ $row['display_name'] ?? $row['market_hash_name'] }}</td>
            <td class="num">{{ number_format($row['current_cny'], 2, ',', '.') }}</td>
            <td class="num up">+{{ number_format($row['delta_cny'], 2, ',', '.') }}</td>
            <td class="num">{{ $row['delta_pct'] ?? '—' }}</td>
            <td>{{ $row['inventory_label'] }}</td>
        </tr>
    @endforeach
</table>

<h2>Skin giảm giá (top {{ min(30, count($report['losers'] ?? [])) }})</h2>
<table>
    <tr><th>Skin</th><th class="num">Giá ¥</th><th class="num">±¥</th><th class="num">%</th><th>Kho</th></tr>
    @foreach(array_slice($report['losers'] ?? [], 0, 30) as $row)
        <tr>
            <td>{{ $row['display_name'] ?? $row['market_hash_name'] }}</td>
            <td class="num">{{ number_format($row['current_cny'], 2, ',', '.') }}</td>
            <td class="num down">{{ number_format($row['delta_cny'], 2, ',', '.') }}</td>
            <td class="num">{{ $row['delta_pct'] ?? '—' }}</td>
            <td>{{ $row['inventory_label'] }}</td>
        </tr>
    @endforeach
</table>

<h2>Skin mới thêm</h2>
<table>
    <tr><th>Skin</th><th class="num">Giá trị ¥</th><th>Kho</th></tr>
    @foreach(array_slice($report['added'] ?? [], 0, 30) as $row)
        <tr>
            <td>{{ $row['display_name'] ?? $row['market_hash_name'] }}</td>
            <td class="num">{{ $row['line_total_cny'] ?? $row['buff_price_cny'] ?? '—' }}</td>
            <td>{{ $row['inventory_label'] }}</td>
        </tr>
    @endforeach
</table>

<h2>Skin mất</h2>
<table>
    <tr><th>Skin</th><th class="num">Giá trị ¥</th><th>Kho</th></tr>
    @foreach(array_slice($report['removed'] ?? [], 0, 30) as $row)
        <tr>
            <td>{{ $row['display_name'] ?? $row['market_hash_name'] }}</td>
            <td class="num">{{ $row['line_total_cny'] ?? $row['buff_price_cny'] ?? '—' }}</td>
            <td>{{ $row['inventory_label'] }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
