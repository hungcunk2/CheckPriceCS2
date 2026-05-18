(function () {
    var STORAGE_KEY = 'cpcs2-theme';

    function currentTheme() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }

    function syncToggleButtons() {
        var isDark = currentTheme() === 'dark';
        document.querySelectorAll('.theme-toggle-btn').forEach(function (btn) {
            var moon = btn.querySelector('.theme-icon-dark');
            var sun = btn.querySelector('.theme-icon-light');
            if (moon) {
                moon.classList.toggle('d-none', isDark);
            }
            if (sun) {
                sun.classList.toggle('d-none', !isDark);
            }
            btn.setAttribute('aria-label', isDark ? 'Bật giao diện sáng' : 'Bật giao diện tối');
        });
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        if (document.body) {
            document.body.classList.toggle('dark-mode', theme === 'dark');
        }
        localStorage.setItem(STORAGE_KEY, theme);
        syncToggleButtons();
    }

    function toggleTheme() {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.theme-toggle-btn').forEach(function (btn) {
            btn.addEventListener('click', toggleTheme);
        });
        syncToggleButtons();
    });
})();
