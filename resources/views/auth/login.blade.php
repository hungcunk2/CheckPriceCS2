@extends('layouts.landing')

@section('content')
@include('landing.nav')
<section class="lp-container" style="max-width:28rem;margin:4rem auto;padding:0 1rem">
    <div class="lp-glass-strong rounded-3 p-4">
        <h1 class="h4 mb-3">Đăng nhập</h1>
        <p class="small text-muted mb-4">Tài khoản trả phí — giá Empire chính xác theo coin (API + proxy).</p>

        @if(session('error'))
            <div class="alert alert-danger small">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autofocus>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
        </form>

        <p class="small text-muted mt-4 mb-0">
            <a href="{{ route('public.landing') }}">← Về trang chủ</a> · Tra giá không cần đăng nhập (CS2Cap).
        </p>
    </div>
</section>
@endsection
