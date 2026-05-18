<script>
(function () {
    var key = 'cpcs2-currency';
    var saved = localStorage.getItem(key);
    var currency = saved === 'usd' || saved === 'vnd' ? saved : 'vnd';
    document.documentElement.setAttribute('data-currency', currency);
})();
</script>
