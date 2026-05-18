<script>
(function () {
    var key = 'cpcs2-theme';
    var saved = localStorage.getItem(key);
    var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.documentElement.classList.toggle('dark-mode', theme === 'dark');
})();
</script>
