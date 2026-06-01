@extends('layouts.admin')

@section('title', $key ? 'Sửa API key Empire' : 'Thêm API key Empire')
@section('page-title', $key ? 'Sửa API key CSGOEmpire' : 'Thêm API key CSGOEmpire')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.buff-accounts.index') }}" class="text-decoration-none small">
        <i class="fas fa-arrow-left"></i> Buff & Empire
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="panel-admin rounded border p-4">
            <p class="small text-muted">
                Tạo key tại
                <a href="https://csgoempire.com/trading/apikey" target="_blank" rel="noopener">csgoempire.com/trading/apikey</a>
                (cần bật 2FA trên acc Empire).
            </p>

            <form method="POST" action="{{ $key ? route('admin.buff-accounts.empire-keys.update', $key->id) : route('admin.buff-accounts.empire-keys.store') }}">
                @csrf
                @if($key)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">Tên (label) <span class="text-danger">*</span></label>
                    <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                        value="{{ old('label', $key->label ?? '') }}" required maxlength="64"
                        placeholder="empire-1">
                    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">API key <span class="text-danger">{{ $key ? '' : '*' }}</span></label>
                    <input type="password" name="api_key" class="form-control font-monospace @error('api_key') is-invalid @enderror"
                        {{ $key ? '' : 'required' }}
                        autocomplete="new-password"
                        placeholder="{{ $key ? 'Để trống nếu không đổi — hiện tại: '.$key->api_key_hint : 'Dán API key Empire' }}">
                    @error('api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    @if($key)
                        <label class="form-label">Thứ tự ưu tiên</label>
                        <input type="number" name="sort_order" class="form-control @error('sort_order') is-invalid @enderror"
                            value="{{ old('sort_order', $key->sort_order) }}" min="0" max="999">
                        <div class="form-text">Số nhỏ hơn = ưu tiên trước khi xoay key.</div>
                        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @else
                        <label class="form-label">Thứ tự ưu tiên</label>
                        <p class="form-control-plaintext mb-0">
                            <strong>{{ old('sort_order', $nextSortOrder ?? 0) }}</strong>
                            <span class="text-muted small">— tự tăng (key thứ {{ ($nextSortOrder ?? 0) + 1 }})</span>
                        </p>
                        <input type="hidden" name="sort_order" value="{{ old('sort_order', $nextSortOrder ?? 0) }}">
                    @endif
                </div>

                <div class="form-check mb-4">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="empire_is_active"
                        @checked(old('is_active', $key->is_active ?? true))>
                    <label class="form-check-label" for="empire_is_active">Bật key (dùng khi lấy giá Empire)</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Lưu</button>
                    <a href="{{ route('admin.buff-accounts.index') }}" class="btn btn-outline-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
