@extends('layouts.member')

@section('title', 'Cần cập nhật hệ thống')
@section('page-title', 'Kho đồ Steam')

@section('content')
<div class="alert alert-warning">
    <h2 class="h6 mb-2">Chưa cấu hình kho đồ theo tài khoản</h2>
    <p class="small mb-2">
        Database chưa có cột <code>user_id</code> trên bảng <code>tracked_inventories</code>.
        Trang kho user cần migration mới — nếu không chạy sẽ báo lỗi 500.
    </p>
    <p class="small mb-0 font-monospace">php artisan migrate --force</p>
</div>
@endsection
