(function () {
    function placeholderSrc() {
        return window.__cpcs2PlaceholderImg || '';
    }

    function isPlaceholderSrc(src) {
        var ph = placeholderSrc();
        if (!src) {
            return true;
        }
        if (ph && src === ph) {
            return true;
        }

        return /\/images\/logo\.png(?:\?|$)/i.test(src);
    }

    window.__cpcs2CatalogImgFallback = function (imgEl) {
        try {
            if (!imgEl) {
                return;
            }

            var stage = parseInt(imgEl.dataset.fallbackStage || '0', 10);
            if (stage >= 2) {
                imgEl.onerror = null;
                imgEl.src = placeholderSrc() || imgEl.src;

                return;
            }

            var hash = imgEl.getAttribute('data-hash') || '';
            if (!hash) {
                imgEl.onerror = null;
                imgEl.src = placeholderSrc() || imgEl.src;

                return;
            }

            imgEl.dataset.fallbackStage = String(stage + 1);

            var endpoint = window.__cpcs2CatalogEndpoint || '';
            if (!endpoint) {
                imgEl.onerror = null;
                imgEl.src = placeholderSrc() || imgEl.src;

                return;
            }

            var iconHint = imgEl.getAttribute('data-steam-icon') || '';
            var imgQuery = '?market_hash_name=' + encodeURIComponent(hash);
            if (iconHint && stage === 0) {
                imgQuery += '&icon=' + encodeURIComponent(iconHint);
            }
            if (stage >= 1) {
                imgQuery += '&prefer=catalog';
            }

            fetch(endpoint + imgQuery, {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (r) { return r.json(); })
              .then(function (j) {
                  if (j && j.ok && j.image_url) {
                      imgEl.onerror = function () {
                          window.__cpcs2CatalogImgFallback(imgEl);
                      };
                      imgEl.src = j.image_url;
                  } else {
                      window.__cpcs2CatalogImgFallback(imgEl);
                  }
              }).catch(function () {
                  window.__cpcs2CatalogImgFallback(imgEl);
              });
        } catch (e) {}
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('img.item-thumb[data-hash]').forEach(function (imgEl) {
            if (isPlaceholderSrc(imgEl.getAttribute('src') || imgEl.src)) {
                window.__cpcs2CatalogImgFallback(imgEl);
            }
        });
    });
})();
