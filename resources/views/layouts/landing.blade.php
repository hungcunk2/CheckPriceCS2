<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark" class="lp-page">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.site-meta')
    @include('partials.currency-init')
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto:wght@400;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/member-auth.css') }}?v={{ @filemtime(public_path('css/member-auth.css')) ?: 1 }}">
    @stack('styles')
</head>
<body class="landing">
    @yield('content')
    @guest
        @include('partials.member-auth-modal', [
            'authRedirectTo' => $authRedirectTo ?? route('public.landing'),
        ])
    @endguest
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
