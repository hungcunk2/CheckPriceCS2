<script>
(function () {
    document.querySelectorAll('[data-ma-switch]').forEach(function (el) {
        el.addEventListener('click', function () {
            var target = el.getAttribute('data-ma-switch');
            var scope = el.getAttribute('data-ma-scope') || '';
            var loginPanel = document.getElementById('ma-panel-login' + scope);
            var registerPanel = document.getElementById('ma-panel-register' + scope);
            if (!loginPanel || !registerPanel) return;
            loginPanel.classList.toggle('is-hidden', target !== 'login');
            registerPanel.classList.toggle('is-hidden', target !== 'register');
        });
    });

    document.querySelectorAll('[data-ma-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-ma-toggle-password'));
            if (!input) return;
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', isPass);
                icon.classList.toggle('fa-eye-slash', !isPass);
            }
        });
    });

    @if(!empty($autoOpenModal))
    var modalEl = document.getElementById('memberAuthModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        var shouldOpen = @json(
            $errors->any()
            || session('register_success')
            || session('register_otp_sent')
            || session('register_otp_message')
            || request()->has('auth')
            || request()->query('openAuth')
        );
        if (shouldOpen) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        document.querySelectorAll('[data-open-auth-modal]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (btn.tagName === 'A' && btn.getAttribute('href') === '#') {
                    e.preventDefault();
                }
                var tab = btn.getAttribute('data-auth-tab');
                if (tab === 'register') {
                    var scope = '-modal';
                    var loginPanel = document.getElementById('ma-panel-login' + scope);
                    var registerPanel = document.getElementById('ma-panel-register' + scope);
                    if (loginPanel && registerPanel) {
                        loginPanel.classList.add('is-hidden');
                        registerPanel.classList.remove('is-hidden');
                    }
                }
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });
    }
    @endif
})();
</script>
