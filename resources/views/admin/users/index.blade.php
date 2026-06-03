@extends('layouts.admin')

@section('title', 'User trả phí')
@section('page-title', 'User trả phí')

@section('content')
<div class="d-flex justify-content-end align-items-center mb-3">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Thêm user</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="panel-admin rounded border">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Tên</th>
                <th>Email</th>
                <th>Gói</th>
                <th>Trạng thái</th>
                <th>Hết hạn</th>
                <th class="text-end">Thao tác</th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td>{{ $u->id }}</td>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->subscriptionPlanLabel() ?? '—' }}</td>
                    <td>
                        @if($u->is_active && $u->hasActiveSubscription())
                            <span class="badge text-bg-success">Hoạt động</span>
                        @else
                            <span class="badge text-bg-secondary">Tắt / hết hạn</span>
                        @endif
                    </td>
                    <td class="small">{{ $u->paid_until?->format('d/m/Y') ?? '—' }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Xóa user?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted p-4">Chưa có user.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
        <div class="p-3">{{ $users->links() }}</div>
    @endif
</div>
@endsection
