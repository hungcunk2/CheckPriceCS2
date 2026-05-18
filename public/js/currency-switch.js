(function () {
    var STORAGE_KEY = 'cpcs2-currency';

    function currentCurrency() {
        var c = document.documentElement.getAttribute('data-currency');
        return c === 'usd' ? 'usd' : 'vnd';
    }

    function syncButtons() {
        var active = currentCurrency();
        document.querySelectorAll('.currency-btn').forEach(function (btn) {
            var isActive = btn.getAttribute('data-currency') === active;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function applyCurrency(currency) {
        if (currency !== 'vnd' && currency !== 'usd') {
            currency = 'vnd';
        }
        document.documentElement.setAttribute('data-currency', currency);
        localStorage.setItem(STORAGE_KEY, currency);
        syncButtons();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.currency-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applyCurrency(btn.getAttribute('data-currency'));
            });
        });
        syncButtons();
    });
})();
