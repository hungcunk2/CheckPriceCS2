@extends('layouts.admin')

@section('title', $inventory ? 'Sửa kho' : 'Thêm kho')
@section('page-title', $inventory ? 'Sửa kho Steam' : 'Thêm kho Steam')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="panel-admin rounded border p-4">
            <form method="POST" action="{{ $inventory ? route('admin.inventories.update', $inventory->id) : route('admin.inventories.store') }}">
                @csrf
                @if($inventory)
                    @method('PUT')
                @endif

                <div class="mb-3">
                    <label class="form-label">Tên hiển thị <span class="text-danger">*</span></label>
                    <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                        value="{{ old('label', $inventory->label ?? '') }}" required maxlength="120"
                        placeholder="VD: Kho đồ 1">
                    @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Link kho Steam <span class="text-danger">*</span></label>
                    <textarea name="url" class="form-control @error('url') is-invalid @enderror" rows="3" required
                        placeholder="https://steamcommunity.com/id/.../inventory/">{{ old('url', $inventory->url ?? '') }}</textarea>
                    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Kho phải để public.</div>
                </div>

                @php
                    $tradeAtValue = old('trade_at');
                    if ($tradeAtValue === null && !empty($inventory->trade_at)) {
                        $tradeAtValue = \Carbon\Carbon::parse($inventory->trade_at)
                            ->timezone('Asia/Ho_Chi_Minh')
                            ->format('Y-m-d\TH:i');
                    }
                @endphp
                <div class="mb-3">
                    <label class="form-label" for="trade_at">Thời gian trade</label>
                    <input
                        type="datetime-local"
                        name="trade_at"
                        id="trade_at"
                        class="form-control @error('trade_at') is-invalid @enderror"
                        value="{{ $tradeAtValue }}"
                    >
                    @error('trade_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Giờ Việt Nam (GMT+7). Để trống nếu chưa có — trang công khai sẽ hiện đếm ngược.</div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_public" value="1" id="is_public"
                            {{ old('is_public', $inventory->is_public ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_public">Hiển thị trên trang công khai</label>
                    </div>
                    <div class="form-text">Danh sách kho tự sắp theo tổng giá Buff từ cao xuống thấp.</div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="check_now" value="1" id="check_now"
                        {{ old('check_now') ? 'checked' : '' }}>
                    <label class="form-check-label" for="check_now">Check giá ngay sau khi lưu</label>
                </div>
                <p class="small text-muted mb-4">Kho lớn: trên VPS nên bỏ tick, lưu xong bấm sync hoặc chờ auto 10 phút.</p>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Lưu</button>
                    <a href="{{ route('admin.inventories.index') }}" class="btn btn-outline-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
