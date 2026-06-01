<div class="currency-switch d-flex align-items-center gap-1" role="group" aria-label="Chọn đơn vị hiển thị giá">
    <button
        type="button"
        class="currency-btn"
        data-currency="vnd"
        title="Hiển thị VND (¥ × {{ number_format(\App\Support\ExchangeRateStore::cnyToVnd()) }})"
        aria-label="Giá VND"
        aria-pressed="false"
    >
        <span class="currency-flag" aria-hidden="true">🇻🇳</span>
    </button>
    <button
        type="button"
        class="currency-btn"
        data-currency="usd"
        title="Hiển thị USD (¥ → ₫ ÷ {{ number_format(\App\Support\ExchangeRateStore::vndToUsd()) }})"
        aria-label="Giá USD"
        aria-pressed="false"
    >
        <span class="currency-flag" aria-hidden="true">🇺🇸</span>
    </button>
</div>
