<footer class="lp-footer">
    <div class="lp-container">
        <div class="lp-footer-inner">
            <a href="{{ route('public.landing') }}" class="lp-brand">
                <div class="lp-brand-icon">
                    <img src="{{ asset('images/logo.png') }}" alt="" width="18" height="18" style="border-radius:4px">
                </div>
                <span class="lp-brand-text">
                    CheckPrice<span class="lp-text-gradient-accent">CS2</span>
                </span>
            </a>
            <div class="lp-footer-copy">
                © {{ date('Y') }} CheckPrice CS2.
                <a href="https://www.facebook.com/2KNUC.H" target="_blank" rel="noopener noreferrer" style="color:inherit">Nguyễn Tuấn Hùng</a>
            </div>
            <div class="lp-footer-links">
                <a href="{{ route('public.index') }}">Bảng giá kho</a>
                <a href="{{ route('blog.index') }}">Blog</a>
                <a href="{{ route('login') }}">Đăng nhập</a>
                <a href="{{ route('public.landing') }}#faq">FAQ</a>
            </div>
        </div>
    </div>
</footer>
