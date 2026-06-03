<section id="hero" class="lp-hero">
    <div class="lp-hero-bg">
        <div class="lp-hero-bg-overlay"></div>
        <div class="lp-grid-bg" style="position:absolute;inset:0;opacity:0.5"></div>
    </div>

    <div class="lp-container" style="max-width:72rem">
        <div class="lp-hero-badge lp-glass">
            <i class="fas fa-wand-magic-sparkles" style="color:var(--lp-accent);font-size:0.875rem"></i>
            <span>Định giá real-time từ Buff163</span>
            <span class="lp-pulse-dot"></span>
        </div>

        <h1 class="lp-hero-title">
            Kiểm tra giá trị<br>
            <span class="lp-text-gradient-primary">kho đồ CS2</span>
            <span class="lp-text-gradient-accent">trong vài giây</span>
        </h1>

        <p class="lp-hero-desc">
            Dán link Steam public — xem giá Buff163 ngay tại đây, không lưu, không cần đăng nhập.
        </p>

        <form
            action="#ket-qua-tra-gia"
            method="get"
            class="lp-hero-form lp-glass-strong"
            id="lp-check-form"
            data-progressive="1"
            data-start-url="{{ route('api.guest.check.start') }}"
            data-prices-url="{{ route('api.guest.check.prices') }}"
            data-item-image-url="{{ route('api.guest.item-image') }}"
            data-placeholder-image-url="{{ asset('images/logo.png') }}"
            data-empire-enabled="{{ ($empireEnabled ?? false) ? '1' : '0' }}"
            data-empire-usd-reference="1"
            data-batch-size="{{ config('cs2price.guest_check_batch_size', 12) }}"
        >
            @csrf
            <div class="lp-hero-input-wrap">
                <i class="fas fa-link lp-muted"></i>
                <input
                    type="url"
                    name="steam_url"
                    class="lp-hero-input"
                    value="{{ $submittedUrl ?? '' }}"
                    placeholder="Link Steam public (steamcommunity.com/id/.../inventory)"
                    aria-label="Link kho Steam"
                    required
                >
            </div>
            <button type="submit" class="lp-btn-primary lp-glow-blue" id="lp-check-submit">
                <span class="lp-check-btn-label">Tra giá</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        <script>
        (function () {
            var f = document.getElementById('lp-check-form');
            if (!f || f.getAttribute('data-progressive') !== '1') return;
            f.addEventListener('submit', function (e) { e.preventDefault(); }, true);
        })();
        </script>

        @error('steam_url')
            <div class="lp-check-result lp-check-result--error lp-glass rounded-3 p-3 mt-3 text-start small" style="color:var(--lp-accent)">
                {{ $message }}
            </div>
        @enderror

        <div id="lp-check-result-host">
            @include('landing.check-result')
        </div>
    </div>
</section>

@push('scripts')
@php
    $guestCheckJs = public_path('js/guest-check.js');
    $guestCheckVer = is_file($guestCheckJs) ? (string) filemtime($guestCheckJs) : '1';
@endphp
<script src="{{ asset('js/guest-check.js') }}?v={{ $guestCheckVer }}" defer></script>
@endpush
