<script>
(function () {
    function switchPanel(scope, target) {
        var panels = ['login', 'register', 'forgot'];
        panels.forEach(function (name) {
            var el = document.getElementById('ma-panel-' + name + scope);
            if (el) {
                el.classList.toggle('is-hidden', name !== target);
            }
        });
    }

    document.querySelectorAll('[data-ma-switch]').forEach(function (el) {
        el.addEventListener('click', function () {
            var target = el.getAttribute('data-ma-switch');
            var scope = el.getAttribute('data-ma-scope') || '';
            if (target) {
                switchPanel(scope, target);
            }
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
        var authTab = @json(request('auth', session('auth_tab', request()->has('forgot') ? 'forgot' : 'login')));
        var scope = '-modal';
        if (authTab === 'register' || authTab === 'forgot' || authTab === 'login') {
            switchPanel(scope, authTab);
        }

        var shouldOpen = @json(
            $errors->any()
            || session('register_success')
            || session('register_otp_sent')
            || session('register_otp_message')
            || session('forgot_success')
            || request()->has('auth')
            || request()->has('forgot')
            || request()->query('openAuth')
        );
        if (shouldOpen) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        document.querySelectorAll('[data-open-auth-modal]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-auth-tab') || 'login';
                switchPanel(scope, tab);
            });
        });
    }
    @endif
})();
</script>
