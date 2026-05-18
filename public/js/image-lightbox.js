(function () {
    const MODAL_ID = 'image-lightbox';

    function enlargeSteamImageUrl(url) {
        if (!url || typeof url !== 'string') {
            return url;
        }

        if (!/steamstatic\.com\/economy\/image\//i.test(url)) {
            return url;
        }

        if (/\/\d+fx\d+f(?:\/)?$/i.test(url)) {
            return url.replace(/\/\d+fx\d+f\/?$/i, '/512fx512f');
        }

        return url.replace(/\/?$/, '/512fx512f');
    }

    function ensureModal() {
        let modal = document.getElementById(MODAL_ID);
        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.className = 'image-lightbox';
        modal.hidden = true;
        modal.innerHTML = [
            '<button type="button" class="image-lightbox-backdrop" aria-label="Đóng"></button>',
            '<div class="image-lightbox-panel" role="dialog" aria-modal="true" aria-label="Xem ảnh phóng to">',
            '  <button type="button" class="image-lightbox-close" aria-label="Đóng">&times;</button>',
            '  <img class="image-lightbox-img" alt="">',
            '</div>',
        ].join('');

        document.body.appendChild(modal);

        modal.querySelector('.image-lightbox-backdrop').addEventListener('click', close);
        modal.querySelector('.image-lightbox-close').addEventListener('click', close);

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                close();
            }
        });

        return modal;
    }

    function close() {
        const modal = document.getElementById(MODAL_ID);
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.hidden = true;
        document.body.classList.remove('image-lightbox-open');

        const img = modal.querySelector('.image-lightbox-img');
        if (img) {
            img.removeAttribute('src');
        }
    }

    function open(source) {
        const modal = ensureModal();
        const img = modal.querySelector('.image-lightbox-img');
        const fullSrc = enlargeSteamImageUrl(source.currentSrc || source.src);

        img.src = fullSrc;
        img.alt = source.alt || 'Ảnh phóng to';

        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('image-lightbox-open');
        modal.querySelector('.image-lightbox-close').focus();
    }

    document.addEventListener('click', (event) => {
        const thumb = event.target.closest('img.image-zoomable');
        if (!thumb || !thumb.src) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        open(thumb);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const modal = document.getElementById(MODAL_ID);
        if (modal && modal.classList.contains('is-open')) {
            close();
        }
    });
})();
