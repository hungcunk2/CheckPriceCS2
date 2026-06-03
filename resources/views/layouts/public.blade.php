<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('partials.site-meta')
    @include('partials.theme-init')
    @include('partials.currency-init')
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ @filemtime(public_path('css/landing.css')) ?: 1 }}">
    <link rel="stylesheet" href="{{ asset('css/image-lightbox.css') }}">
    @stack('styles')
</head>
<body class="app-shell has-lp-nav">
    @include('landing.nav', ['showHeaderActions' => true])
    <main class="app-main container-fluid px-4 pb-4">
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
            </div>
        @endif
        @yield('content')
    </main>
    @include('partials.footer')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{{ asset('js/theme-toggle.js') }}"></script>
    <script src="{{ asset('js/currency-switch.js') }}"></script>
    <script src="{{ asset('js/image-lightbox.js') }}"></script>
    <script src="{{ asset('js/trade-countdown.js') }}"></script>
    <script>
    (function () {
        var endpoint = @json(route('api.guest.item-image'));
        var placeholder = @json(asset('images/logo.png'));

        function hydrateImg(imgEl) {
            try {
                if (!imgEl || imgEl.dataset.fallbackTried === '1') return;
                var hash = imgEl.getAttribute('data-hash') || '';
                if (!hash || !endpoint) return;
                imgEl.dataset.fallbackTried = '1';

                fetch(endpoint + '?market_hash_name=' + encodeURIComponent(hash), {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }).then(function (r) { return r.json(); })
                  .then(function (j) {
                      if (j && j.ok && j.image_url) {
                          imgEl.src = j.image_url;
                      } else if (placeholder) {
                          imgEl.src = placeholder;
                      }
                  }).catch(function () {
                      if (placeholder) imgEl.src = placeholder;
                  });
            } catch (e) {}
        }

        window.__cpcs2CatalogImgFallback = function (imgEl) {
            hydrateImg(imgEl);
        };

        document.addEventListener('DOMContentLoaded', function () {
            var imgs = document.querySelectorAll('img.item-thumb[data-hash]');
            if (!imgs || !imgs.length) return;

            var queue = Array.prototype.slice.call(imgs);
            var concurrency = 4;
            var active = 0;

            function next() {
                while (active < concurrency && queue.length) {
                    var imgEl = queue.shift();
                    active++;
                    Promise.resolve()
                        .then(function () { hydrateImg(imgEl); })
                        .finally(function () {
                            active--;
                            next();
                        });
                }
            }

            next();
        });
    })();
    </script>
    @stack('scripts')
</body>
</html>
