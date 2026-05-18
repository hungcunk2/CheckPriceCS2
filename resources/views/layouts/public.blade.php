<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Giá kho CS2') - CheckPrice Buff163</title>
    @include('partials.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
    <header class="app-header">
        <div class="container-fluid px-4 d-flex align-items-center justify-content-between py-3">
            <a href="{{ route('public.index') }}" class="brand text-decoration-none">
                <img src="{{ asset('images/logo.png') }}" alt="CheckPrice CS2" class="site-logo">
                <small class="text-muted">Buff163</small>
            </a>
            <span class="badge bg-light text-dark border">CNY → VND: {{ number_format($cnyToVnd ?? config('cs2price.cny_to_vnd')) }}</span>
        </div>
    </header>
    <main class="app-main container-fluid px-4 pb-5">
        @yield('content')
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
