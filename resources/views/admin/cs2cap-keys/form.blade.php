@extends('layouts.admin')

@section('page-title', $key ? 'Sửa API key CS2Cap' : 'Thêm API key CS2Cap')

@section('content')
    <div class="container py-4" style="max-width: 860px">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h4 mb-0">{{ $key ? 'Sửa API key CS2Cap' : 'Thêm API key CS2Cap' }}</h1>
            <a href="{{ route('admin.buff-accounts.index') }}" class="btn btn-outline-secondary btn-sm">← Quay lại</a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ $key ? route('admin.buff-accounts.cs2cap-keys.update', $key->id) : route('admin.buff-accounts.cs2cap-keys.store') }}">
                    @csrf
                    @if($key)
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Label</label>
                            <input name="label" class="form-control @error('label') is-invalid @enderror"
                                   value="{{ old('label', $key->label ?? '') }}"
                                   placeholder="cs2cap-1">
                            @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Ưu tiên (sort_order)</label>
                            <input name="sort_order" type="number" class="form-control @error('sort_order') is-invalid @enderror"
                                   value="{{ old('sort_order', $key->sort_order ?? $nextSortOrder ?? 1) }}">
                            @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold">API key</label>
                            <input name="api_key" class="form-control font-monospace @error('api_key') is-invalid @enderror"
                                   value="{{ old('api_key') }}"
                                   placeholder="{{ $key ? 'Để trống nếu không đổi key' : 'sk_live_...' }}">
                            @error('api_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if($key)
                                <div class="form-text">Key hiện tại: <span class="text-muted font-monospace">{{ $key->api_key_hint }}</span></div>
                            @endif
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                       id="is_active" {{ old('is_active', $key->is_active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Bật trong pool</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary">{{ $key ? 'Lưu' : 'Thêm' }}</button>
                        <a class="btn btn-outline-secondary" href="{{ route('admin.buff-accounts.index') }}">Hủy</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

