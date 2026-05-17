(function () {
    function applyFilter(root, category, label) {
        const rows = root.querySelectorAll('tbody tr[data-weapon-category]');
        const emptyRow = root.querySelector('tr.weapon-filter-empty');
        const showAll = !category || category === '*';
        let visible = 0;

        rows.forEach((row) => {
            const show = showAll || row.dataset.weaponCategory === category;
            row.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyRow) {
            emptyRow.classList.toggle('d-none', showAll || visible > 0);
        }

        const status = root.querySelector('.weapon-filter-status');
        const statusLabel = root.querySelector('.weapon-filter-label');
        const statusCount = root.querySelector('.weapon-filter-count');
        const wrap = root.querySelector('.item-table-wrap');
        const total = wrap ? parseInt(wrap.dataset.totalItems || '0', 10) : rows.length;

        if (status) {
            status.classList.toggle('d-none', showAll);
        }
        if (statusLabel) {
            statusLabel.textContent = label || '';
        }
        if (statusCount) {
            statusCount.textContent = showAll ? '' : ` — hiển thị ${visible}/${total}`;
        }

        root.querySelectorAll('.weapon-stat-badge').forEach((btn) => {
            const active = showAll
                ? btn.dataset.weaponCategory === '*'
                : btn.dataset.weaponCategory === category;
            btn.classList.toggle('weapon-stat-badge--active', active);
            btn.classList.toggle('bg-primary', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('border-0', active);
            btn.classList.toggle('bg-light', !active);
            btn.classList.toggle('text-dark', !active);
            btn.classList.toggle('border', !active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function initRoot(root) {
        root.querySelectorAll('.weapon-stat-badge[data-weapon-category]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const category = btn.dataset.weaponCategory;
                const label = btn.dataset.weaponLabel || btn.textContent.trim();
                applyFilter(root, category, label);
            });
        });

        const clearBtn = root.querySelector('.weapon-filter-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', (e) => {
                e.preventDefault();
                applyFilter(root, '*', 'Tất cả');
            });
        }
    }

    document.querySelectorAll('.inventory-item-filter-root').forEach(initRoot);
})();
