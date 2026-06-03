@extends('layouts.landing')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) ?: 1 }}">
<link rel="stylesheet" href="{{ asset('css/image-lightbox.css') }}">
@endpush

@section('content')
@include('landing.nav')
@include('partials.flash-alerts')

<section class="lp-container py-4" style="max-width:100%">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Kho đồ theo dõi</h1>
            <p class="small text-muted mb-0">Xin chào {{ $user->name }} — giao diện giống quản trị, chỉ xem.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('public.landing') }}#hero" class="btn btn-outline-secondary btn-sm">Tra giá Steam</a>
            <a href="{{ route('member.dashboard') }}" class="btn btn-outline-secondary btn-sm">Tài khoản</a>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Đăng xuất</button>
            </form>
        </div>
    </div>

    @unless($hasActiveSubscription)
        <div class="alert alert-warning py-2 small mb-3">
            Tài khoản chưa được admin kích hoạt gói. Bạn vẫn xem được danh sách kho; tra giá Empire đầy đủ khi gói được bật.
        </div>
    @endunless

    @include('partials.tracked-inventories-table', [
        'inventories' => $inventories,
        'buffConfigured' => $buffConfigured,
        'empireEnabled' => $empireEnabled,
        'tableMode' => 'member',
    ])
</section>
@endsection

@push('scripts')
@include('partials.tracked-inventories-table-scripts', ['tableMode' => 'member'])
<script src="{{ asset('js/image-lightbox.js') }}"></script>
<script src="{{ asset('js/trade-countdown.js') }}"></script>
@endpush
