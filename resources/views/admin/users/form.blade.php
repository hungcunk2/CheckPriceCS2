@extends('layouts.admin')

@section('title', $user ? 'Sửa user' : 'Thêm user')
@section('page-title', $user ? 'Sửa user' : 'Thêm user')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.users.index') }}" class="text-decoration-none small"><i class="fas fa-arrow-left"></i> Danh sách user</a>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="panel-admin rounded border p-4">
            <form method="POST" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
                @csrf
                @if($user) @method('PUT') @endif

                <div class="mb-3">
                    <label class="form-label">Tên</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name ?? '') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email ?? '') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu {{ $user ? '(để trống = giữ)' : '' }}</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                           {{ $user ? '' : 'required' }} autocomplete="new-password">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Hết hạn gói (để trống = không giới hạn)</label>
                    <input type="datetime-local" name="paid_until" class="form-control"
                           value="{{ old('paid_until', $user && $user->paid_until ? $user->paid_until->format('Y-m-d\TH:i') : '') }}">
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                           @checked(old('is_active', $user->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Kích hoạt</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $user->notes ?? '') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>
</div>
@endsection
