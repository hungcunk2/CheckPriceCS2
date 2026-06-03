<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php $meta = \App\Support\SiteMeta::noindex('Tài khoản — '.config('site.name')); @endphp
    @include('partials.site-meta', ['meta' => $meta])
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/member-auth.css') }}?v={{ @filemtime(public_path('css/member-auth.css')) ?: 1 }}">
    @stack('styles')
</head>
<body class="ma-page">
    <a href="{{ route('public.landing') }}" class="ma-back">
        <i class="fas fa-arrow-left"></i> Trang chủ
    </a>
    <main class="ma-main">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
