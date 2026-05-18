(function () {
    function formatUnit(value, label) {
        return value + ' ' + label;
    }

    function formatRemaining(ms) {
        if (ms <= 0) {
            return { text: 'Đã đến hạn', past: true };
        }

        var totalSeconds = Math.floor(ms / 1000);
        var days = Math.floor(totalSeconds / 86400);
        var hours = Math.floor((totalSeconds % 86400) / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;
        var parts = [];

        if (days > 0) {
            parts.push(formatUnit(days, 'ngày'));
        }
        if (hours > 0 || days > 0) {
            parts.push(formatUnit(hours, 'giờ'));
        }
        if (minutes > 0 || hours > 0 || days > 0) {
            parts.push(formatUnit(minutes, 'phút'));
        }
        parts.push(formatUnit(seconds, 'giây'));

        return { text: parts.join(' '), past: false };
    }

    function tick(element) {
        var iso = element.dataset.tradeAt;
        if (!iso) {
            return;
        }

        var targetMs = Date.parse(iso);
        if (Number.isNaN(targetMs)) {
            return;
        }

        var valueEl = element.querySelector('.trade-countdown-value');
        if (!valueEl) {
            return;
        }

        var result = formatRemaining(targetMs - Date.now());
        valueEl.textContent = result.text;
        element.classList.toggle('trade-countdown--past', result.past);
        element.classList.toggle('trade-countdown--active', !result.past);
    }

    function refreshAll() {
        document.querySelectorAll('.trade-countdown[data-trade-at]').forEach(tick);
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshAll();
        window.setInterval(refreshAll, 1000);
    });
})();
