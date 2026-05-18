(function () {
    const overlay = document.getElementById('image-zoom-overlay');
    if (!overlay) {
        return;
    }

    const img = document.getElementById('image-zoom-img');
    const caption = document.getElementById('image-zoom-caption');
    const backdrop = overlay.querySelector('.image-zoom-backdrop');
    const closeBtn = overlay.querySelector('.image-zoom-close');

    let lastFocus = null;

    function openFromTrigger(trigger) {
        const targetImg = trigger.querySelector('img');
        const src = trigger.dataset.zoomSrc || targetImg?.currentSrc || targetImg?.src;
        const label = trigger.dataset.zoomCaption || targetImg?.alt || '';

        if (!src) {
            return;
        }

        lastFocus = document.activeElement;
        img.src = src;
        img.alt = label || '';
        caption.textContent = label || '';
        caption.hidden = !label;
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        document.body.classList.add('image-zoom-open');
        closeBtn.focus();
    }

    function closeLightbox() {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('image-zoom-open');
        img.removeAttribute('src');
        img.alt = '';
        caption.textContent = '';
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.img-zoom-trigger');
        if (!trigger) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openFromTrigger(trigger);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !overlay.hidden) {
            event.preventDefault();
            closeLightbox();
            return;
        }

        const trigger = event.target.closest('.img-zoom-trigger');
        if (!trigger || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openFromTrigger(trigger);
    });

    backdrop?.addEventListener('click', closeLightbox);
    closeBtn?.addEventListener('click', closeLightbox);
})();
