@extends('layouts.landing')

@section('content')
@include('landing.nav')
@include('partials.flash-alerts')

<section class="lp-container" style="max-width:40rem;margin:3rem auto;padding:0 1rem">
    <div class="lp-glass-strong rounded-3 p-4">
        <h1 class="h4 mb-2">Xin chào, {{ $user->name }}</h1>
        <p class="small text-muted mb-3">{{ $user->email }}</p>

        @if(session('error'))
            <div class="alert alert-warning py-2 small">{{ session('error') }}</div>
        @endif

        <ul class="list-unstyled small mb-4">
            <li><strong>Trạng thái:</strong>
                @if($user->hasActiveSubscription())
                    <span class="text-success">Đang hoạt động</span>
                @else
                    <span class="text-warning">Chờ admin kích hoạt gói</span>
                @endif
            </li>
            <li><strong>Gói:</strong>
                @if($user->paid_until)
                    đến {{ $user->paid_until->timezone(config('cs2price.timezone'))->format('d/m/Y H:i') }}
                @else
                    chưa gán hạn
                @endif
            </li>
        </ul>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('member.inventories.index') }}" class="btn btn-primary btn-sm">Xem kho đồ</a>
            <a href="{{ route('public.landing') }}#hero" class="btn btn-outline-secondary btn-sm">Tra giá Steam</a>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Đăng xuất</button>
            </form>
        </div>
    </div>
</section>
@endsection
