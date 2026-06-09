<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Theo dõi hệ thống</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 14px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 2px; }
        .meta { color: #666; font-size: 9px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 3px 5px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        td.num { text-align: right; }
        .grid { width: 100%; }
        .grid td { border: none; padding: 2px 8px 2px 0; vertical-align: top; }
        .alert { background: #fee; border: 1px solid #fcc; padding: 4px 6px; margin-bottom: 8px; font-size: 9px; }
    </style>
</head>
<body>
@php
    $o = $stats['overview'] ?? [];
    $sub = $stats['subscription'] ?? [];
    $sync = $stats['sync_quality'] ?? [];
    $aum = $stats['aum'] ?? [];
    $support = $stats['support'] ?? [];
    $api = $stats['api_ops'] ?? [];
    $fmtVnd = fn ($v) => number_format((int) $v, 0, ',', '.').' đ';
@endphp

<h1>Theo dõi hệ thống — {{ $site_name }}</h1>
<div class="meta">Xuất lúc: {{ $generated_at }}</div>

@if(! empty($api['critical_alert']))
    <div class="alert">{{ $api['alert_message'] }}</div>
@endif

<h2>Tổng quan</h2>
<table class="grid">
    <tr>
        <td><strong>User trả phí:</strong> {{ $o['paid_active_users'] ?? 0 }} (≤7d: {{ $o['expiring_7_days'] ?? 0 }}, ≤30d: {{ $o['expiring_30_days'] ?? 0 }})</td>
        <td><strong>Doanh thu tháng:</strong> {{ $fmtVnd($o['revenue_month_vnd'] ?? 0) }}</td>
    </tr>
    <tr>
        <td><strong>Đơn pending:</strong> {{ $o['pending_orders'] ?? 0 }}</td>
        <td><strong>MRR ước tính:</strong> {{ $fmtVnd($sub['mrr_estimate_vnd'] ?? 0) }}</td>
    </tr>
    <tr>
        <td><strong>Tổng kho VND:</strong> {{ $fmtVnd($o['total_inventory_vnd'] ?? 0) }}</td>
        <td><strong>Kho admin/member:</strong> {{ $o['admin_inventories'] ?? 0 }} / {{ $o['member_inventories'] ?? 0 }}</td>
    </tr>
    <tr>
        <td><strong>Sync trễ:</strong> {{ $o['overdue_sync'] ?? 0 }}</td>
        <td><strong>Có giá Buff:</strong> {{ $o['pricing_coverage_pct'] ?? '—' }}%</td>
    </tr>
</table>

<h2>User active theo gói</h2>
<table>
    <tr><th>Gói</th><th class="num">User</th></tr>
    @foreach($sub['active_by_plan'] ?? [] as $plan => $count)
        <tr><td>{{ \App\Support\SubscriptionPlans::get($plan)['name'] ?? strtoupper($plan) }}</td><td class="num">{{ $count }}</td></tr>
    @endforeach
</table>

<h2>Doanh thu 6 tháng</h2>
<table>
    <tr><th>Tháng</th><th class="num">VND</th></tr>
    @foreach($sub['revenue_by_month'] ?? [] as $row)
        <tr><td>{{ $row['label'] }}</td><td class="num">{{ $fmtVnd($row['amount_vnd']) }}</td></tr>
    @endforeach
</table>

<h2>Đơn pending & user sắp hết hạn</h2>
<p>Pending &gt;24h: {{ $sub['pending_over_24h'] ?? 0 }} · &gt;48h: {{ $sub['pending_over_48h'] ?? 0 }}</p>
<table>
    <tr><th>User</th><th>Gói</th><th>Hết hạn</th></tr>
    @foreach($sub['expiring_this_week'] ?? [] as $user)
        <tr>
            <td>{{ $user->name }}<br><small>{{ $user->email }}</small></td>
            <td>{{ $user->subscription_plan }}</td>
            <td>{{ $user->paid_until?->format('d/m/Y') }}</td>
        </tr>
    @endforeach
</table>

<h2>Sync & giá</h2>
<p>Stale &gt;24h: {{ $sync['stale_over_24h'] ?? 0 }} · Empire: {{ $sync['empire_priced_skins'] ?? 0 }}/{{ $sync['total_skins'] ?? 0 }} skin · Buff/Empire wins: {{ $sync['buff_sell_wins'] ?? 0 }}/{{ $sync['empire_sell_wins'] ?? 0 }}</p>
<table>
    <tr><th>Kho</th><th>Có giá</th><th>Failed</th><th>Coverage</th></tr>
    @foreach(array_slice($sync['worst_inventories'] ?? [], 0, 15) as $row)
        <tr>
            <td>{{ $row['label'] }}</td>
            <td>{{ $row['priced_count'] }}/{{ $row['item_count'] }}</td>
            <td class="num">{{ $row['failed_count'] }}</td>
            <td class="num">{{ $row['coverage_pct'] }}%</td>
        </tr>
    @endforeach
</table>

<h2>Top 10 kho (AUM)</h2>
<table>
    <tr><th>Kho</th><th class="num">VND</th><th class="num">CNY</th><th class="num">Skin</th></tr>
    @foreach($aum['top_inventories'] ?? [] as $row)
        <tr>
            <td>{{ $row['label'] }} ({{ $row['owner'] }})</td>
            <td class="num">{{ $fmtVnd($row['total_vnd']) }}</td>
            <td class="num">{{ number_format($row['total_cny'], 2, ',', '.') }}</td>
            <td class="num">{{ $row['item_count'] }}</td>
        </tr>
    @endforeach
</table>

@if(! empty($support['available']))
<h2>Hỗ trợ</h2>
<p>Chưa đọc: {{ $support['unread_conversations'] ?? 0 }} · TG phản hồi TB: {{ $support['avg_response_minutes'] ?? '—' }} phút</p>
@endif

<h2>CS2Cap / API</h2>
<p>Buff acc: {{ $api['buff_accounts'] ?? 0 }} · CS2Cap: {{ $api['cs2cap_active'] ?? 0 }}/{{ $api['cs2cap_total'] ?? 0 }} active · Hết quota: {{ $api['cs2cap_exhausted'] ?? 0 }}</p>
<table>
    <tr><th>Key</th><th>Tier</th><th>Quota</th><th>TT</th></tr>
    @foreach($api['cs2cap_keys'] ?? [] as $key)
        <tr>
            <td>{{ $key['label'] }}</td>
            <td>{{ $key['tier'] ?? '—' }}</td>
            <td>@if($key['quota_remaining'] !== null){{ $key['quota_remaining'] }}/{{ $key['quota_limit'] }}@else—@endif</td>
            <td>@if($key['exhausted'])Hết@elseif($key['active'])OK@elseTắt@endif</td>
        </tr>
    @endforeach
</table>
</body>
</html>
