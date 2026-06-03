@extends('layouts.admin')

@section('title', 'Proxy Empire xoay')
@section('page-title', 'Proxy Empire (5Stars)')

@section('content')
<div class="mb-3">
    <p class="small text-muted mb-0">
        API lấy proxy:
        <a href="https://proxyxoay.shop/api/get.php" target="_blank" rel="noopener">proxyxoay.shop/api/get.php</a>
        · Tài liệu:
        <a href="https://5starsproxy.vn/?home=apixoay" target="_blank" rel="noopener">5starsproxy.vn</a>
    </p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="panel-admin rounded border p-4 mb-3">
            <form method="POST" action="{{ route('admin.empire-proxy.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3 form-check form-switch">
                    <input type="checkbox" name="enabled" value="1" class="form-check-input" id="enabled"
                           @checked(old('enabled', $settings->enabled))>
                    <label class="form-check-label" for="enabled">Bật proxy cho Empire API (member + admin + cron)</label>
                </div>

                <div class="mb-3">
                    <label class="form-label">Key xoay (keyxoay)</label>
                    <input type="password" name="rotation_key" class="form-control font-monospace"
                           value="{{ old('rotation_key', $settings->rotation_key) }}" autocomplete="off"
                           placeholder="Key nhận khi mua gói proxy xoay">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nhà mạng (nhamang)</label>
                        <select name="nhamang" class="form-select">
                            @foreach(['Random', 'viettel', 'fpt', 'vnpt'] as $nm)
                                <option value="{{ $nm }}" @selected(old('nhamang', $settings->nhamang) === $nm)>{{ $nm }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tỉnh (tinhthanh)</label>
                        <input type="text" name="tinhthanh" class="form-control" maxlength="8"
                               value="{{ old('tinhthanh', $settings->tinhthanh) }}" placeholder="0 = Random">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Whitelist IP (VPS — để trống = tự detect)</label>
                    <input type="text" name="whitelist_ip" class="form-control font-monospace"
                           value="{{ old('whitelist_ip', $settings->whitelist_ip) }}" placeholder="103.x.x.x">
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="use_socks5" value="1" class="form-check-input" id="use_socks5"
                           @checked(old('use_socks5', $settings->use_socks5))>
                    <label class="form-check-label" for="use_socks5">Dùng SOCKS5 thay HTTP</label>
                </div>

                <button type="submit" class="btn btn-primary">Lưu cấu hình</button>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.empire-proxy.test') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">Kiểm tra lấy proxy</button>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="panel-admin rounded border p-3 small text-muted">
            <p class="mb-2"><strong>Lần test gần nhất:</strong><br>
                {{ $settings->last_tested_at?->timezone(config('cs2price.timezone'))->format('d/m/Y H:i') ?? '—' }}
            </p>
            <p class="mb-0">{{ $settings->last_test_message ?? 'Chưa test.' }}</p>
            <hr>
            <p class="mb-0">Guest <strong>không</strong> dùng proxy — chỉ CS2Cap USD.</p>
        </div>
    </div>
</div>
@endsection
