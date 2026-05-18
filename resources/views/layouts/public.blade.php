<!DOCTYPE html>
<html lang="vi">
<head>
    @include('partials.theme-init')
    @include('partials.currency-init')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Giá kho CS2') - CheckPrice CS2</title>
    @include('partials.favicon')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body class="app-shell">
    <header class="app-header">
        <div class="container-fluid px-4 d-flex align-items-center justify-content-between py-3">
            <a href="{{ route('public.index') }}" class="brand text-decoration-none">
                <img src="{{ asset('images/logo.png') }}" alt="CheckPrice CS2" class="site-logo">
            </a>
            @include('partials.header-actions')
        </div>
    </header>
    <main class="app-main container-fluid px-4 pb-4">
        @yield('content')
    </main>
    @include('partials.footer')
    @include('partials.image-lightbox')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/theme-toggle.js') }}"></script>
    <script src="{{ asset('js/currency-switch.js') }}"></script>
    <script src="{{ asset('js/image-lightbox.js') }}"></script>
    @stack('scripts')
</body>
</html>
