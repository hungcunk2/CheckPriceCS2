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

    function maEscapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function maRegisterOtpSuccess(form, message) {
        var scope = form.id.replace('ma-register-form', '');
        var alertEl = document.getElementById('ma-register-alert' + scope);
        if (alertEl) {
            alertEl.innerHTML = '<div class="ma-alert ma-alert--info">' + maEscapeHtml(message) + '</div>';
        }
        form.dataset.maOtpSent = '1';
        var emailInput = form.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.readOnly = true;
        }
        var cancelRow = form.closest('.ma-panel').querySelector('[data-ma-register-cancel]');
        if (cancelRow) {
            cancelRow.classList.remove('is-hidden');
        }
        var otpInput = form.querySelector('input[name="otp"]');
        if (otpInput) {
            otpInput.focus();
        }
    }

    function maRegisterOtpError(form, payload) {
        var msg = payload && payload.message ? payload.message : 'Không gửi được OTP. Thử lại.';
        if (payload && payload.errors) {
            var firstKey = Object.keys(payload.errors)[0];
            if (firstKey && payload.errors[firstKey] && payload.errors[firstKey][0]) {
                msg = payload.errors[firstKey][0];
            }
        }
        var scope = form.id.replace('ma-register-form', '');
        var alertEl = document.getElementById('ma-register-alert' + scope);
        if (alertEl) {
            alertEl.innerHTML = '<div class="ma-alert ma-alert--danger">' + maEscapeHtml(msg) + '</div>';
        }
    }

    document.querySelectorAll('[data-ma-send-otp]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.closest('form.ma-register-form');
            if (!form || !form.dataset.sendOtpUrl) {
                return;
            }
            var alertScope = form.id.replace('ma-register-form', '');
            var alertEl = document.getElementById('ma-register-alert' + alertScope);
            if (alertEl) {
                alertEl.innerHTML = '';
            }
            form.querySelectorAll('[data-ma-otp-error]').forEach(function (el) {
                el.textContent = '';
                el.classList.add('is-hidden');
            });

            if (!form.reportValidity()) {
                return;
            }

            btn.disabled = true;
            var label = btn.textContent;
            btn.textContent = 'Đang gửi…';

            fetch(form.dataset.sendOtpUrl, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { ok: res.ok, data: data };
                    }).catch(function () {
                        return { ok: false, data: { message: 'Phản hồi không hợp lệ từ máy chủ.' } };
                    });
                })
                .then(function (result) {
                    if (result.ok && result.data && result.data.ok) {
                        maRegisterOtpSuccess(form, result.data.message);
                        return;
                    }
                    maRegisterOtpError(form, result.data);
                })
                .catch(function () {
                    maRegisterOtpError(form, { message: 'Lỗi kết nối. Thử lại.' });
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = label;
                });
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
        var authTab = @json(
            request()->has('forgot') || request('auth') === 'forgot' ? 'forgot'
            : (session('register_otp_sent') || session('register_otp_message') || request('auth') === 'register' || session('auth_tab') === 'register' ? 'register'
            : (session('register_success') ? 'login' : (request('auth', session('auth_tab', 'login')))))
        );
        var scope = '-modal';
        switchPanel(scope, authTab);

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
