<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.theme-init')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin</title>
    @include('partials.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page d-flex align-items-center min-vh-100">
    <button
        type="button"
        class="btn btn-sm theme-toggle-btn login-theme-toggle"
        aria-label="Bật giao diện tối"
    >
        <i class="fas fa-moon theme-icon-dark"></i>
        <i class="fas fa-sun theme-icon-light d-none"></i>
    </button>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card panel-card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center mb-3">
                            <img src="{{ asset('images/logo.png') }}" alt="CheckPrice CS2" class="site-logo site-logo--login">
                        </div>
                        <h4 class="mb-1">Đăng nhập Admin</h4>
                        <p class="text-muted small mb-4">Quản lý link kho Steam & cập nhật giá</p>
                        @if($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif
                        <form method="POST" action="{{ route('admin.login.submit') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Tên đăng nhập</label>
                                <input type="text" name="username" class="form-control" value="{{ old('username') }}" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mật khẩu</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/theme-toggle.js') }}"></script>
</body>
</html>
