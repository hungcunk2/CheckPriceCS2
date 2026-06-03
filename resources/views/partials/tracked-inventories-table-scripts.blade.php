@php
    $enableRefresh = in_array($tableMode ?? 'admin', ['admin', 'member'], true);
    $refreshRouteTemplate = ($tableMode ?? 'admin') === 'member'
        ? route('member.inventories.refresh', ['inventory' => 0])
        : route('admin.inventories.refresh', ['inventory' => 0]);
@endphp
<script src="{{ asset('js/inventory-weapon-filter.js') }}"></script>
<script>
document.querySelectorAll('.admin-inventory-actions').forEach(cell => {
    cell.addEventListener('click', e => e.stopPropagation());
});

document.querySelectorAll('.admin-inventory-summary-row').forEach(row => {
    const targetSel = row.getAttribute('data-bs-target');
    const toggle = () => {
        const el = document.querySelector(targetSel);
        if (el) bootstrap.Collapse.getOrCreateInstance(el).toggle();
    };
    row.addEventListener('click', e => {
        if (e.target.closest('.admin-inventory-actions')) return;
        toggle();
    });
    row.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggle();
        }
    });
});

document.querySelectorAll('[id^="admin-inv-items-"]').forEach(panel => {
    panel.addEventListener('show.bs.collapse', () => syncAdminInventoryChevron(panel.id, true));
    panel.addEventListener('hide.bs.collapse', () => syncAdminInventoryChevron(panel.id, false));
});

function syncAdminInventoryChevron(panelId, open) {
    const btn = document.querySelector('[data-bs-target="#' + panelId + '"].admin-inventory-toggle-btn');
    const row = document.querySelector('[data-bs-target="#' + panelId + '"].admin-inventory-summary-row');
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (row) row.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function showInvToast(message, type, extraHtml) {
    const host = document.getElementById('admin-inv-toast');
    if (!host) return;
    const div = document.createElement('div');
    div.className = `alert alert-${type} alert-dismissible fade show shadow-sm mb-2`;
    const safe = String(message ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
    div.innerHTML = safe + (extraHtml || '') + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    host.appendChild(div);
    setTimeout(() => div.remove(), 12000);
}

@if($enableRefresh)
document.querySelectorAll('.btn-refresh').forEach(btn => {
    btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const id = btn.dataset.id;
        const row = document.querySelector(`tr.admin-inventory-summary-row[data-inventory-id="${id}"]`);
        const loading = document.getElementById('admin-loading');
        const icon = btn.querySelector('i');
        if (loading) loading.style.display = 'flex';
        btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');
        try {
            const res = await fetch(@json($refreshRouteTemplate).replace('/0/', '/' + id + '/'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            let json = {};
            try {
                json = await res.json();
            } catch (parseErr) {
                throw new Error(res.status === 504 ? 'Timeout server (quá 60s) — thử lại hoặc chờ cron' : 'Phản hồi không hợp lệ (HTTP ' + res.status + ')');
            }
            if (!json.ok) {
                showInvToast(json.message || ('Lỗi check giá (HTTP ' + res.status + ')'), 'danger');
                return;
            }
            if (row) {
                const buffCell = row.querySelector('.inv-buff-price-cell');
                const empireCell = row.querySelector('.inv-empire-price-cell');
                const countCell = row.querySelector('.inv-item-count-cell');
                const updatedCell = row.querySelector('.inv-updated-cell');
                if (buffCell && json.buff_price_html) buffCell.innerHTML = json.buff_price_html;
                if (empireCell && json.empire_price_html) empireCell.innerHTML = json.empire_price_html;
                if (countCell && json.item_count != null) countCell.textContent = json.item_count;
                if (updatedCell && json.last_checked_at) updatedCell.textContent = json.last_checked_at;
            }
            const detail = document.getElementById('admin-inv-items-' + id);
            const hint = detail?.classList.contains('show')
                ? '<span class="d-block small mt-1">Bảng skin bên dưới chưa tự cập nhật — thu gọn rồi mở lại hoặc F5.</span>'
                : '';
            showInvToast(json.message || 'Đã cập nhật giá.', 'success', hint);
        } catch (err) {
            showInvToast(err.message || 'Lỗi kết nối', 'danger');
        } finally {
            if (loading) loading.style.display = 'none';
            btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
        }
    });
});
@endif
</script>
