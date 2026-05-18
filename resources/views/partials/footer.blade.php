@php $compact = $compact ?? false; @endphp
<footer class="app-footer {{ $compact ? 'app-footer--compact' : '' }}">
    <div class="container-fluid px-4">
        <div class="app-footer-inner">
            <div class="app-footer-brand">
                <a href="{{ route('public.index') }}" class="app-footer-logo-link" aria-label="CheckPrice CS2">
                    <img src="{{ asset('images/logo.png') }}" alt="" class="app-footer-logo">
                </a>
                @unless($compact)
                    <p class="app-footer-tagline mb-0">Tra cứu giá kho CS2 theo Buff 163</p>
                @endunless
            </div>
            @unless($compact)
                <nav class="app-footer-nav" aria-label="Liên kết chân trang">
                    <a href="{{ route('public.index') }}">Bảng giá kho</a>
                </nav>
            @endunless
            <div class="app-footer-meta">
                <span>
                    Bản quyền bởi
                    <a
                        href="https://www.facebook.com/2KNUC.H"
                        class="app-footer-credit"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Nguyễn Tuấn Hùng</a>
                </span>
                @unless($compact)
                    <span class="app-footer-sep" aria-hidden="true">·</span>
                    <span class="app-footer-note">Giá tham khảo từ Buff 163</span>
                @endunless
            </div>
        </div>
    </div>
</footer>
