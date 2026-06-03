@extends('layouts.member')

@section('title', 'Thông tin gói')
@section('page-title', 'Thông tin gói')

@section('content')
@if(session('register_magic_success'))
    <div class="alert alert-success py-2 small mb-3">{{ session('register_magic_success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-warning py-2 small mb-3">{{ session('error') }}</div>
@endif
<div class="row">
    <div class="col-lg-6">
        <div class="panel-admin rounded border p-4">
            <p class="small text-muted mb-3">{{ $user->email }}</p>

            <ul class="list-unstyled small mb-4">
                <li class="mb-2"><strong>Trạng thái:</strong>
                    @if($user->hasActiveSubscription())
                        <span class="badge text-bg-success">Hoạt động</span>
                    @else
                        <span class="badge text-bg-warning">Chờ kích hoạt</span>
                    @endif
                </li>
                <li class="mb-2"><strong>Gói:</strong> {{ $user->subscriptionPlanLabel() ?? '—' }}</li>
                <li class="mb-2"><strong>Hết hạn:</strong>
                    @if($user->paid_until)
                        {{ $user->paid_until->timezone(config('cs2price.timezone'))->format('d/m/Y H:i') }}
                    @else
                        chưa gán
                    @endif
                </li>
            </ul>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('member.inventories.index') }}" class="btn btn-primary btn-sm">Kho đồ Steam</a>
                <a href="{{ route('member.support.index') }}" class="btn btn-outline-primary btn-sm">Chat với Admin</a>
                <a href="{{ route('public.pricing') }}" class="btn btn-outline-secondary btn-sm">Nâng cấp gói</a>
            </div>
        </div>
    </div>
</div>
@endsection
