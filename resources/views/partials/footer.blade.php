@php $compact = $compact ?? false; @endphp
<footer class="app-footer {{ $compact ? 'app-footer--compact' : '' }}">
    <div class="container-fluid px-4">
        <div class="app-footer-inner">
            <div class="app-footer-brand">
                <a href="{{ route('public.index') }}" class="app-footer-logo-link" aria-label="CheckPrice CS2">
                    <img src="{{ asset('images/logo.png') }}" alt="" class="app-footer-logo">
                </a>
            </div>
            <div class="app-footer-meta">
                <span>
                    Bản quyền &copy; {{ date('Y') }}.
                    <a
                        href="https://www.facebook.com/2KNUC.H"
                        class="app-footer-credit"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Nguyễn Tuấn Hùng</a>.
                </span>
            </div>
        </div>
    </div>
</footer>
