<div class="header-actions d-flex align-items-center gap-2">
    <span
        class="fx-flags"
        title="¥ → ₫ {{ number_format(config('cs2price.cny_to_vnd')) }} · ₫ → $ {{ number_format(config('cs2price.vnd_to_usd')) }}"
        aria-label="Quy đổi qua VND sang USD"
    >
        <span class="fx-flag" role="img" aria-hidden="true">🇻🇳</span>
        <i class="fas fa-arrow-right fx-flag-arrow" aria-hidden="true"></i>
        <span class="fx-flag" role="img" aria-hidden="true">🇺🇸</span>
    </span>
    <button
        type="button"
        class="btn btn-sm theme-toggle-btn"
        aria-label="Bật giao diện tối"
    >
        <i class="fas fa-moon theme-icon-dark"></i>
        <i class="fas fa-sun theme-icon-light d-none"></i>
    </button>
</div>
