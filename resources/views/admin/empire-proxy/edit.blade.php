@extends('layouts.admin')

@section('title', 'Proxy Empire')
@section('page-title', 'Proxy Empire')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="panel-admin rounded border p-4 mb-3">
            <form method="POST" action="{{ route('admin.empire-proxy.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3 form-check form-switch">
                    <input type="checkbox" name="enabled" value="1" class="form-check-input" id="enabled"
                           @checked(old('enabled', $settings->enabled))>
                    <label class="form-check-label" for="enabled">Bật proxy Empire</label>
                </div>

                <div class="mb-3">
                    <label class="form-label">Key xoay</label>
                    <input type="password" name="rotation_key" class="form-control font-monospace"
                           value="{{ old('rotation_key', $settings->rotation_key) }}" autocomplete="off">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nhà mạng</label>
                        <select name="nhamang" class="form-select">
                            @foreach(['Random', 'viettel', 'fpt', 'vnpt'] as $nm)
                                <option value="{{ $nm }}" @selected(old('nhamang', $settings->nhamang) === $nm)>{{ $nm }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tỉnh</label>
                        <input type="text" name="tinhthanh" class="form-control" maxlength="8"
                               value="{{ old('tinhthanh', $settings->tinhthanh) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Whitelist IP</label>
                    <input type="text" name="whitelist_ip" class="form-control font-monospace"
                           value="{{ old('whitelist_ip', $settings->whitelist_ip) }}">
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="use_socks5" value="1" class="form-check-input" id="use_socks5"
                           @checked(old('use_socks5', $settings->use_socks5))>
                    <label class="form-check-label" for="use_socks5">Dùng SOCKS5</label>
                </div>

                <button type="submit" class="btn btn-primary">Lưu</button>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.empire-proxy.test') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary">Kiểm tra lấy proxy</button>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="panel-admin rounded border p-3 small">
            <p class="mb-2"><strong>Test gần nhất</strong><br>
                {{ $settings->last_tested_at?->timezone(config('cs2price.timezone'))->format('d/m/Y H:i') ?? '—' }}
            </p>
            <p class="mb-0 text-muted">{{ $settings->last_test_message ?? '—' }}</p>
        </div>
    </div>
</div>
@endsection
