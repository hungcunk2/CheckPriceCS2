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
                            <input
                                type="date"
                                name="trade_at_date"
                                id="trade_at_date"
                                class="form-control @error('trade_at_date') is-invalid @enderror"
                                value="{{ $tradeDate }}"
                            >
                            @error('trade_at_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-7">
                            <div class="input-group">
                                <select
                                    name="trade_at_hour"
                                    id="trade_at_hour"
                                    class="form-select @error('trade_at_hour') is-invalid @enderror"
                                    aria-label="Giờ (24h)"
                                >
                                    <option value="" @selected($tradeHour === '')>Giờ</option>
                                    @for($h = 0; $h < 24; $h++)
                                        <option value="{{ $h }}" @selected((string) $tradeHour === (string) $h)>
                                            {{ sprintf('%02d', $h) }}
                                        </option>
                                    @endfor
                                </select>
                                <span class="input-group-text">:</span>
                                <select
                                    name="trade_at_minute"
                                    id="trade_at_minute"
                                    class="form-select @error('trade_at_minute') is-invalid @enderror"
                                    aria-label="Phút"
                                >
                                    <option value="" @selected($tradeMinute === '')>Phút</option>
                                    @for($m = 0; $m < 60; $m++)
                                        <option value="{{ $m }}" @selected((string) $tradeMinute === (string) $m)>
                                            {{ sprintf('%02d', $m) }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                            @error('trade_at_hour')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('trade_at_minute')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_public" value="1" id="is_public"
                            {{ old('is_public', $inventory->is_public ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_public">Hiển thị trên trang công khai</label>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="check_now" value="1" id="check_now"
                        {{ old('check_now') ? 'checked' : '' }}>
                    <label class="form-check-label" for="check_now">Check giá ngay sau khi lưu</label>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Lưu</button>
                    <a href="{{ route('admin.inventories.index') }}" class="btn btn-outline-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
