@extends('layouts.admin')

@section('title', 'Đơn thanh toán')
@section('page-title', 'Đơn thanh toán')

@section('content')
<div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        <a href="{{ route('admin.plan-orders.index', ['status' => 'pending']) }}"
           class="btn {{ $status === 'pending' ? 'btn-primary' : 'btn-outline-secondary' }}">
            Chờ duyệt @if($pendingCount > 0)<span class="badge text-bg-light ms-1">{{ $pendingCount }}</span>@endif
        </a>
        <a href="{{ route('admin.plan-orders.index', ['status' => 'confirmed']) }}"
           class="btn {{ $status === 'confirmed' ? 'btn-primary' : 'btn-outline-secondary' }}">Đã duyệt</a>
        <a href="{{ route('admin.plan-orders.index', ['status' => 'cancelled']) }}"
           class="btn {{ $status === 'cancelled' ? 'btn-primary' : 'btn-outline-secondary' }}">Đã hủy</a>
        <a href="{{ route('admin.plan-orders.index', ['status' => 'all']) }}"
           class="btn {{ $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">Tất cả</a>
    </div>
</div>

<div class="panel-admin rounded border">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Thời gian</th>
                <th>User</th>
                <th>Gói</th>
                <th>Số tiền</th>
                <th>Mã CK</th>
                <th>Ghi chú</th>
                <th>Trạng thái</th>
                <th class="text-end">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php
                    $planInfo = \App\Support\SubscriptionPlans::get($order->plan);
                    $planLabel = $planInfo['name'] ?? strtoupper($order->plan);
                @endphp
                <tr>
                    <td>{{ $order->id }}</td>
                    <td class="small text-nowrap">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @if($order->user)
                            <div>{{ $order->user->name }}</div>
                            <div class="small text-muted">{{ $order->user->email }}</div>
                            <a href="{{ route('admin.users.edit', $order->user) }}" class="small">Sửa user</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $planLabel }}</strong>
                        <span class="text-muted small">× {{ $order->months }} tháng</span>
                    </td>
                    <td class="text-nowrap">{{ \App\Support\SubscriptionPlans::formatVnd($order->amount_vnd) }}</td>
                    <td><code class="small">{{ $order->reference }}</code></td>
                    <td class="small" style="max-width:12rem">{{ $order->member_note ?: '—' }}</td>
                    <td>
                        @if($order->status === 'pending')
                            <span class="badge text-bg-warning">Chờ duyệt</span>
                        @elseif($order->status === 'confirmed')
                            <span class="badge text-bg-success">Đã duyệt</span>
                            @if($order->confirmed_at)
                                <div class="small text-muted">{{ $order->confirmed_at->format('d/m/Y H:i') }}</div>
                            @endif
                        @else
                            <span class="badge text-bg-secondary">Đã hủy</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if($order->status === 'pending')
                            <form method="POST" action="{{ route('admin.plan-orders.confirm', $order) }}" class="d-inline"
                                  onsubmit="return confirm('Duyệt đơn #{{ $order->id }} và gia hạn gói {{ $planLabel }} {{ $order->months }} tháng cho {{ $order->user?->email }}?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Duyệt</button>
                            </form>
                            <form method="POST" action="{{ route('admin.plan-orders.cancel', $order) }}" class="d-inline"
                                  onsubmit="return confirm('Hủy đơn #{{ $order->id }}?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Hủy</button>
                            </form>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-muted p-4">
                        @if($status === 'pending')
                            Không có đơn chờ duyệt.
                        @else
                            Không có đơn.
                        @endif
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
        <div class="p-3">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
