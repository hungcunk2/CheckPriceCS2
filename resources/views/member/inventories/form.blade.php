@extends('layouts.member')

@section('title', $inventory ? 'Sửa kho' : 'Thêm kho')
@section('page-title', $inventory ? 'Sửa kho Steam' : 'Thêm kho Steam')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="panel-admin rounded border p-4">
            <form method="POST" action="{{ $inventory ? route('member.inventories.update', $inventory->id) : route('member.inventories.store') }}">
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
                    <label class="form-label">Chú thích</label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" maxlength="1000"
                        placeholder="VD: Kho trade, hold 7 ngày…">{{ old('notes', $inventory->notes ?? '') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Link kho Steam <span class="text-danger">*</span></label>
                    <textarea name="url" class="form-control @error('url') is-invalid @enderror" rows="3" required
                        placeholder="steamcommunity.com/id/.../inventory hoặc /profiles/.../inventory">{{ old('url', $inventory->url ?? '') }}</textarea>
                    @error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @php
                    $hasOldTradeInput = old('trade_at_date') !== null
                        || old('trade_at_hour') !== null
                        || old('trade_at_minute') !== null;

                    if ($hasOldTradeInput) {
                        $tradeDate = old('trade_at_date', '');
                        $tradeHour = old('trade_at_hour', '');
                        $tradeMinute = old('trade_at_minute', '');
                    } elseif (! empty($inventory->trade_at)) {
                        $tradeAtCarbon = \Carbon\Carbon::parse($inventory->trade_at)->timezone('Asia/Ho_Chi_Minh');
                        $tradeDate = $tradeAtCarbon->format('Y-m-d');
                        $tradeHour = $tradeAtCarbon->format('G');
                        $tradeMinute = $tradeAtCarbon->format('i');
                    } else {
                        $tradeDate = '';
                        $tradeHour = '';
                        $tradeMinute = '';
                    }
                @endphp
                <div class="mb-3">
                    <label class="form-label">Thời gian hết hold (trade)</label>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <input type="date" name="trade_at_date" class="form-control @error('trade_at_date') is-invalid @enderror" value="{{ $tradeDate }}">
                            @error('trade_at_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <div class="input-group">
                                <select name="trade_at_hour" class="form-select">
                                    <option value="">Giờ</option>
                                    @for($h = 0; $h < 24; $h++)
                                        <option value="{{ $h }}" @selected((string) $tradeHour === (string) $h)>{{ sprintf('%02d', $h) }}</option>
                                    @endfor
                                </select>
                                <span class="input-group-text">:</span>
                                <select name="trade_at_minute" class="form-select">
                                    <option value="">Phút</option>
                                    @for($m = 0; $m < 60; $m++)
                                        <option value="{{ $m }}" @selected((string) $tradeMinute === (string) $m)>{{ sprintf('%02d', $m) }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_public" value="1" id="is_public"
                            {{ old('is_public', $inventory->is_public ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_public">Hiển thị trên Kho công khai</label>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="check_now" value="1" id="check_now" {{ old('check_now') ? 'checked' : '' }}>
                    <label class="form-check-label" for="check_now">Check giá Buff/Empire ngay sau khi lưu</label>
                    <div class="form-text">Bỏ tick: chỉ tải kho, avatar và ảnh skin. Nút ⟳ trên danh sách luôn check giá.</div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Lưu</button>
                    <a href="{{ route('member.inventories.index') }}" class="btn btn-outline-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
