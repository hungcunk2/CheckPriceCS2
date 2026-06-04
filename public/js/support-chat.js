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
    var imageInput = document.getElementById('support-chat-image');
    var previewWrap = document.getElementById('support-chat-preview');
    var previewImg = document.getElementById('support-chat-preview-img');
    var previewClear = document.getElementById('support-chat-preview-clear');
    var hintEl = document.getElementById('support-chat-hint');
    var messagesUrl = root.getAttribute('data-messages-url') || '';
    var postUrl = root.getAttribute('data-post-url') || '';
    var pollMs = 5000;
    var lastId = 0;
    var polling = true;
    var pendingFile = null;

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

    function renderMessageBody(msg) {
        var html = '';
        if (msg.image_url) {
            html += '<a href="' + escapeHtml(msg.image_url) + '" target="_blank" rel="noopener noreferrer" class="support-chat-image-link">' +
                '<img src="' + escapeHtml(msg.image_url) + '" alt="Ảnh đính kèm" class="support-chat-image" loading="lazy">' +
                '</a>';
        }
        if (msg.body) {
            html += '<div class="support-chat-text">' + escapeHtml(msg.body) + '</div>';
        }
        return html || '<span class="text-muted">(Ảnh)</span>';
    }

    function renderMessage(msg) {
        var wrap = document.createElement('div');
        wrap.className = 'support-chat-bubble ' + (msg.is_mine ? 'mine' : 'theirs');
        wrap.setAttribute('data-id', String(msg.id));
        wrap.innerHTML =
            '<div class="support-chat-meta">' + escapeHtml(msg.sender_label || '') +
            ' · ' + escapeHtml(msg.created_at || '') + '</div>' +
            renderMessageBody(msg);
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

    function setHint(html) {
        if (hintEl) {
            hintEl.innerHTML = html;
        }
    }

    function clearPreview() {
        pendingFile = null;
        if (imageInput) {
            imageInput.value = '';
        }
        if (previewWrap) {
            previewWrap.classList.add('d-none');
        }
        if (previewImg) {
            if (previewImg.src && previewImg.src.indexOf('blob:') === 0) {
                URL.revokeObjectURL(previewImg.src);
            }
            previewImg.removeAttribute('src');
        }
        setHint('Bấm <strong>Gửi ảnh</strong> để chọn hình (JPG, PNG, WebP, GIF — tối đa 5MB).');
        setStatus('');
    }

    function setPreview(file) {
        if (!file || !previewWrap || !previewImg) {
            return;
        }
        pendingFile = file;
        if (previewImg.src && previewImg.src.indexOf('blob:') === 0) {
            URL.revokeObjectURL(previewImg.src);
        }
        previewImg.src = URL.createObjectURL(file);
        previewWrap.classList.remove('d-none');
        setHint('Đã chọn: <strong>' + escapeHtml(file.name) + '</strong> — bấm <strong>Gửi</strong> để gửi.');
        setStatus('Sẵn sàng gửi ảnh');
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

    function postMessage(body, file) {
        var fd = new FormData();
        if (body) {
            fd.append('body', body);
        }
        if (file) {
            fd.append('image', file);
        }

        return fetch(postUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: fd,
        }).then(function (r) {
            return r.json().then(function (data) {
                return { ok: r.ok, data: data };
            });
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

    if (!postUrl) {
        setStatus('Lỗi cấu hình chat');
        setHint('Thiếu URL gửi tin — tải lại trang.');
    } else {
        setStatus('');
    }

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            var file = imageInput.files && imageInput.files[0];
            if (!file) {
                clearPreview();
                return;
            }
            if (!file.type || file.type.indexOf('image/') !== 0) {
                alert('Chỉ gửi được file ảnh.');
                clearPreview();
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Ảnh tối đa 5MB.');
                clearPreview();
                return;
            }
            setPreview(file);
            if (input) {
                input.focus();
            }
        });
    }

    if (previewClear) {
        previewClear.addEventListener('click', clearPreview);
    }

    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var body = (input && input.value) ? input.value.trim() : '';
            var file = pendingFile;
            if (!body && !file) {
                return;
            }
            sendBtn.disabled = true;
            setStatus('Đang gửi…');
            postMessage(body, file)
                .then(function (result) {
                    if (!result.ok || !result.data || !result.data.ok) {
                        throw new Error((result.data && result.data.message) || 'Không gửi được tin nhắn.');
                    }
                    if (result.data.message) {
                        appendMessages([result.data.message], true);
                    }
                    if (input) {
                        input.value = '';
                    }
                    clearPreview();
                    setStatus('Đã gửi');
                    setTimeout(function () {
                        setStatus('');
                    }, 2000);
                })
                .catch(function (err) {
                    setStatus('');
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
