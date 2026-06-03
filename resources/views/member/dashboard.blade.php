@extends('layouts.landing')

@section('content')
@include('landing.nav')
<section class="lp-container" style="max-width:40rem;margin:3rem auto;padding:0 1rem">
    <div class="lp-glass-strong rounded-3 p-4">
        <h1 class="h4 mb-2">Xin chào, {{ $user->name }}</h1>
        <p class="small text-muted mb-3">{{ $user->email }}</p>

        <ul class="list-unstyled small mb-4">
            <li><strong>Gói:</strong>
                @if($user->paid_until)
                    đến {{ $user->paid_until->timezone(config('cs2price.timezone'))->format('d/m/Y H:i') }}
                @else
                    không giới hạn thời hạn
                @endif
            </li>
            <li><strong>Empire:</strong> coin chính xác (API + proxy xoay khi bật)</li>
            <li><strong>Buff:</strong> CS2Cap / Buff163 theo cấu hình site</li>
        </ul>

        <p class="small mb-3">Tra giá trên <a href="{{ route('public.landing') }}">trang chủ</a> khi đã đăng nhập sẽ dùng quyền thành viên.</p>

        <form method="POST" action="{{ route('logout') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Đăng xuất</button>
        </form>
    </div>
</section>
@endsection
