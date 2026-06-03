(function () {
    'use strict';

    var root = document.getElementById('support-chat-root');
    if (!root) {
        return;
    }

    var messagesEl = document.getElementById('support-chat-messages');
    var form = document.getElementById('support-chat-form');
    var input = document.getElementById('support-chat-input');
    var sendBtn = document.getElementById('support-chat-send');
    var statusEl = document.getElementById('support-chat-status');
    var messagesUrl = root.getAttribute('data-messages-url') || '';
    var postUrl = root.getAttribute('data-post-url') || '';
    var pollMs = 5000;
    var lastId = 0;
    var polling = true;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function parseInitial() {
        try {
            var raw = root.getAttribute('data-initial') || '[]';
            return JSON.parse(raw);
        } catch (e) {
            return [];
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderMessage(msg) {
        var wrap = document.createElement('div');
        wrap.className = 'support-chat-bubble ' + (msg.is_mine ? 'mine' : 'theirs');
        wrap.setAttribute('data-id', String(msg.id));
        wrap.innerHTML =
            '<div class="support-chat-meta">' + escapeHtml(msg.sender_label || '') +
            ' · ' + escapeHtml(msg.created_at || '') + '</div>' +
            escapeHtml(msg.body || '');
        return wrap;
    }

    function appendMessages(list, scroll) {
        if (!list || !list.length) {
            return;
        }
        var empty = messagesEl.querySelector('.support-chat-empty');
        if (empty) {
            empty.remove();
        }
        list.forEach(function (msg) {
            if (msg.id <= lastId) {
                return;
            }
            messagesEl.appendChild(renderMessage(msg));
            lastId = Math.max(lastId, msg.id);
        });
        if (scroll !== false) {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    }

    function showEmpty() {
        if (messagesEl.children.length === 0) {
            var p = document.createElement('p');
            p.className = 'support-chat-empty';
            p.textContent = 'Chưa có tin nhắn. Gửi lời nhắn đầu tiên cho admin.';
            messagesEl.appendChild(p);
        }
    }

    function setStatus(text) {
        if (statusEl) {
            statusEl.textContent = text || '';
        }
    }

    function fetchMessages(afterId) {
        var url = messagesUrl + (messagesUrl.indexOf('?') >= 0 ? '&' : '?') + 'after_id=' + (afterId || 0);
        return fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json();
        });
    }

    function poll() {
        if (!polling || !messagesUrl) {
            return Promise.resolve();
        }
        return fetchMessages(lastId)
            .then(function (data) {
                if (data && data.ok && data.messages && data.messages.length) {
                    appendMessages(data.messages, true);
                }
                setStatus('');
            })
            .catch(function () {
                setStatus('Mất kết nối — thử lại…');
            });
    }

    function postMessage(body) {
        return fetch(postUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ body: body }),
        }).then(function (r) {
            return r.json();
        });
    }

    appendMessages(parseInitial(), false);
    showEmpty();
    if (messagesEl.children.length > 0) {
        messagesEl.querySelectorAll('.support-chat-bubble').forEach(function (el) {
            var id = parseInt(el.getAttribute('data-id'), 10);
            if (!isNaN(id)) {
                lastId = Math.max(lastId, id);
            }
        });
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    setInterval(function () {
        poll();
    }, pollMs);

    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var body = (input && input.value) ? input.value.trim() : '';
            if (!body) {
                return;
            }
            sendBtn.disabled = true;
            postMessage(body)
                .then(function (data) {
                    if (!data || !data.ok) {
                        throw new Error((data && data.message) || 'Không gửi được tin nhắn.');
                    }
                    if (data.message) {
                        appendMessages([data.message], true);
                    }
                    if (input) {
                        input.value = '';
                    }
                })
                .catch(function (err) {
                    alert(err.message || 'Lỗi gửi tin nhắn.');
                })
                .finally(function () {
                    sendBtn.disabled = false;
                    if (input) {
                        input.focus();
                    }
                });
        });
    }

    document.addEventListener('visibilitychange', function () {
        polling = !document.hidden;
        if (polling) {
            poll();
        }
    });
})();
