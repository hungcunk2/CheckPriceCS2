@extends('layouts.admin')

@section('title', $account ? 'Sửa acc Buff' : 'Thêm acc Buff')
@section('page-title', $account ? 'Sửa acc Buff163' : 'Thêm acc Buff163')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="alert alert-info small">
            <strong>Lấy session mới:</strong> đăng nhập <a href="https://buff.163.com" target="_blank" rel="noopener">buff.163.com</a>
            → F12 → Application → Cookies → copy giá trị <code>session</code> và <code>csrf_token</code> (nếu có).
            Dán vào form rồi bấm Check — không cần SSH sửa <code>.env</code>.
        </div>

        <div class="panel-admin rounded border p-4">
            <form method="POST" action="{{ $account ? route('admin.buff-accounts.update', $account->id) : route('admin.buff-accounts.store') }}">
                @csrf
                @if($account)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">Tên acc (label) <span class="text-danger">*</span></label>
                    <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                        value="{{ old('label', $account->label ?? '') }}" required maxlength="64"
                        placeholder="acc-1">
                    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Session cookie <span class="text-danger">{{ $account ? '' : '*' }}</span></label>
                    <textarea name="session" class="form-control font-monospace @error('session') is-invalid @enderror" rows="3"
                        {{ $account ? '' : 'required' }}
                        placeholder="{{ $account ? 'Để trống nếu không đổi — hiện tại: '.$account->session_hint : 'session=1-xxxx... hoặc chỉ phần giá trị session' }}">{{ old('session') }}</textarea>
                    @error('session')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">CSRF token</label>
                    <input type="text" name="csrf_token" class="form-control font-monospace @error('csrf_token') is-invalid @enderror"
                        value="{{ old('csrf_token') }}"
                        placeholder="{{ $account && $account->has_csrf ? 'Để trống nếu không đổi' : 'Tùy chọn — nếu Buff yêu cầu' }}">
                    @error('csrf_token')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Thứ tự ưu tiên</label>
                        <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror"
                            value="{{ old('sort_order', $account->sort_order ?? 0) }}" min="0" max="999">
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active"
                        @checked(old('is_active', $account->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Bật acc (tham gia pool sync giá)</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Lưu acc</button>
                    <a href="{{ route('admin.buff-accounts.index') }}" class="btn btn-outline-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
