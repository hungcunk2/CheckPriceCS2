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

        <form action="{{ route('public.landing') }}" method="post" class="lp-hero-form lp-glass-strong" id="lp-check-form">
            @csrf
            <div class="lp-hero-input-wrap">
                <i class="fas fa-link lp-muted"></i>
                <input
                    type="url"
                    name="steam_url"
                    class="lp-hero-input"
                    value="{{ $submittedUrl ?? '' }}"
                    placeholder="Link Steam hoặc cs.trade (?steam_id=...)"
                    aria-label="Link kho Steam"
                    required
                >
            </div>
            <button type="submit" class="lp-btn-primary lp-glow-blue" id="lp-check-submit">
                <span class="lp-check-btn-label">Tra giá</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>

        @error('steam_url')
            <div class="lp-check-result lp-check-result--error lp-glass rounded-3 p-3 mt-3 text-start small" style="color:var(--lp-accent)">
                {{ $message }}
            </div>
        @enderror

        @include('landing.check-result')
    </div>
</section>

@push('scripts')
<script>
document.getElementById('lp-check-form')?.addEventListener('submit', function () {
    var btn = document.getElementById('lp-check-submit');
    var label = btn?.querySelector('.lp-check-btn-label');
    if (btn) btn.disabled = true;
    if (label) label.textContent = 'Đang quét kho…';
});
@if(!empty($checkResult) || !empty($checkError))
document.getElementById('ket-qua-tra-gia')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
@endif
</script>
@endpush
