(function () {
    'use strict';

    var form = document.getElementById('lp-check-form');
    if (!form || form.getAttribute('data-progressive') === '0') {
        return;
    }

    var host = document.getElementById('lp-check-result-host');
    var submitBtn = document.getElementById('lp-check-submit');
    var startUrl = form.getAttribute('data-start-url') || '';
    var pricesUrl = form.getAttribute('data-prices-url') || '';
    var itemImageUrl = form.getAttribute('data-item-image-url') || '';
    var placeholderImageUrl = form.getAttribute('data-placeholder-image-url') || '';
    var empireEnabled = form.getAttribute('data-empire-enabled') === '1';
    var empireUsdReference = form.getAttribute('data-empire-usd-reference') === '1';
    var batchSize = parseInt(form.getAttribute('data-batch-size') || '12', 10) || 12;

    function csrfToken() {
        var input = form.querySelector('input[name="_token"]');
        return input ? input.value : '';
    }

    function fmtNum(n) {
        return Number(n).toLocaleString('vi-VN');
    }

    function fmtCny(n) {
        if (n == null || isNaN(n)) {
            return '—';
        }
        return '¥' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function priceCell(cny, rates) {
        if (cny == null || isNaN(cny)) {
            return '—';
        }
        var vnd = Math.round(cny * rates.cny_to_vnd);
        var usd = rates.vnd_to_usd > 0 ? Math.round((vnd / rates.vnd_to_usd) * 100) / 100 : null;
        var usdStr = usd != null ? '$' + usd.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
        return '<span class="price-vnd">' + fmtNum(vnd) + ' ₫</span>' +
            '<span class="price-usd">' + usdStr + '</span>';
    }

    function bestVenueBadge(venue) {
        if (venue === 'buff') {
            return '<span class="badge text-bg-primary best-sell-badge" title="Giá Buff163 quy đổi cao hơn">Buff</span>';
        }
        if (venue === 'empire') {
            return '<span class="badge text-bg-warning text-dark best-sell-badge" title="Giá Empire quy đổi cao hơn">Empire</span>';
        }
        if (venue === 'tie') {
            return '<span class="badge text-bg-secondary best-sell-badge" title="Hai nguồn gần bằng nhau">≈</span>';
        }
        return '<span class="text-muted">—</span>';
    }

    function setBtnLoading(loading) {
        var label = submitBtn && submitBtn.querySelector('.lp-check-btn-label');
        if (!submitBtn) {
            return;
        }
        submitBtn.disabled = loading;
        if (label) {
            label.textContent = loading ? 'Đang quét kho…' : 'Tra giá';
        }
    }

    function showLoading(message) {
        if (!host) {
            return;
        }
        host.innerHTML =
            '<div id="ket-qua-tra-gia" class="lp-check-result lp-glass-strong rounded-3 p-4 mt-4 text-start">' +
            '<div class="d-flex align-items-center gap-3">' +
            '<span class="lp-pulse-dot"></span>' +
            '<div class="fw-medium">' + escapeHtml(message || 'Đang tải kho đồ…') + '</div></div></div>';
        scrollToResult();
    }

    function showError(message) {
        if (!host) {
            return;
        }
        host.innerHTML =
            '<div id="ket-qua-tra-gia" class="lp-check-result lp-check-result--error lp-glass rounded-3 p-4 mt-4">' +
            '<div class="d-flex align-items-start gap-3">' +
            '<i class="fas fa-circle-exclamation" style="color:var(--lp-accent);margin-top:0.15rem"></i>' +
            '<div><div class="fw-semibold mb-1">Không tra được giá</div>' +
            '<div class="lp-muted small">' + escapeHtml(message) + '</div></div></div></div>';
        scrollToResult();
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function scrollToResult() {
        document.getElementById('ket-qua-tra-gia')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function chunk(arr, size) {
        var out = [];
        for (var i = 0; i < arr.length; i += size) {
            out.push(arr.slice(i, i + size));
        }
        return out;
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error((data && data.error) || ('Lỗi máy chủ (' + res.status + ')'));
                }
                return data;
            });
        });
    }

    function summarize(rows) {
        var totalCny = 0;
        var totalEmpireCny = 0;
        var priced = 0;
        var empirePriced = 0;
        var buffWins = 0;
        var empireWins = 0;

        rows.forEach(function (row) {
            if (row.buff_price_cny != null) {
                priced++;
            }
            if (row.line_total_cny != null) {
                totalCny += Number(row.line_total_cny);
            }
            if (row.line_total_empire_cny != null) {
                totalEmpireCny += Number(row.line_total_empire_cny);
                empirePriced++;
            }
            if (row.best_sell_venue === 'buff') {
                buffWins++;
            } else if (row.best_sell_venue === 'empire') {
                empireWins++;
            }
        });

        return {
            total_cny: Math.round(totalCny * 100) / 100,
            total_empire_cny: Math.round(totalEmpireCny * 100) / 100,
            priced_count: priced,
            empire_priced_count: empirePriced,
            sell_compare_buff_wins: buffWins,
            sell_compare_empire_wins: empireWins,
        };
    }

    function sortRows(rows) {
        rows.sort(function (a, b) {
            return (Number(b.line_total_cny) || 0) - (Number(a.line_total_cny) || 0);
        });
    }

    function renderShell(state) {
        var inv = state.inventory;
        var progress = state.pricedCount + ' / ' + state.itemCount + ' skin đã có giá Buff';
        if (empireEnabled) {
            progress += ' · Empire ' + state.empirePricedCount;
        }
        if (!state.pricingDone) {
            progress += ' <span class="lp-pulse-dot d-inline-block ms-1" title="Đang lấy giá"></span>';
        }

        var avatar = inv.steam_avatar_url
            ? '<img src="' + escapeHtml(inv.steam_avatar_url) + '" alt="" class="lp-check-avatar" width="56" height="56">'
            : '<div class="lp-check-avatar lp-check-avatar--placeholder"><i class="fab fa-steam"></i></div>';

        var totalBlock = '';
        if (state.summary.total_cny > 0) {
            totalBlock +=
                '<div class="small lp-muted mb-1">Tổng Buff</div>' +
                '<div class="lp-check-total lp-text-gradient-primary">' + priceCell(state.summary.total_cny, state.rates) + '</div>' +
                '<div class="small lp-muted">¥' + fmtNum(state.summary.total_cny) + '</div>';
        } else if (state.pricingDone) {
            totalBlock += '<div style="color:var(--lp-accent)">Chưa có giá Buff</div>';
        } else {
            totalBlock += '<div class="lp-muted small">Đang lấy giá Buff…</div>';
        }

        if (empireEnabled && state.summary.total_empire_cny > 0) {
            totalBlock +=
                '<div class="small lp-muted mt-3 mb-1">Tổng Empire (ước tính)</div>' +
                '<div class="fw-semibold">' + priceCell(state.summary.total_empire_cny, state.rates) + '</div>' +
                '<div class="small lp-muted">¥' + fmtNum(state.summary.total_empire_cny) + '</div>';
            if (state.summary.sell_compare_buff_wins + state.summary.sell_compare_empire_wins > 0) {
                totalBlock +=
                    '<div class="small lp-muted mt-1">Buff tốt hơn: ' + state.summary.sell_compare_buff_wins +
                    ' · Empire: ' + state.summary.sell_compare_empire_wins + '</div>';
            }
        }

        var empireCols = empireEnabled
            ? '<th class="text-end">Empire</th><th class="text-center">Nên bán</th>'
            : '';

        host.innerHTML =
            '<div id="ket-qua-tra-gia" class="lp-check-result lp-glass-strong rounded-3 p-4 sm:p-6 mt-4 text-start">' +
            '<div class="lp-check-result-header d-flex flex-wrap justify-content-between align-items-start gap-4 mb-4 pb-4" style="border-bottom:1px solid var(--lp-border)">' +
            '<div class="d-flex align-items-center gap-3 min-w-0">' + avatar +
            '<div class="min-w-0"><div class="fw-semibold text-truncate">' + escapeHtml(inv.steam_persona_name || inv.label || 'Steam') + '</div>' +
            (inv.url ? '<a href="' + escapeHtml(inv.url) + '" target="_blank" rel="noopener noreferrer" class="small lp-muted text-decoration-none">Mở trên Steam <i class="fas fa-arrow-up-right-from-square"></i></a>' : '') +
            '</div></div>' +
            '<div class="text-md-end" id="lp-check-totals">' + totalBlock +
            '<div class="small lp-muted mt-1" id="lp-check-progress">' + progress + '</div></div></div>' +
            '<div class="lp-check-table-wrap"><table class="lp-check-table"><thead><tr>' +
            '<th></th><th>Item</th><th class="text-center">SL</th><th class="text-end">Buff</th>' + empireCols +
            '<th class="text-end"><span class="price-col-label-vnd">VND</span><span class="price-col-label-usd">USD</span></th>' +
            '<th class="text-end">Tổng</th></tr></thead>' +
            '<tbody id="lp-check-tbody"></tbody></table></div></div>';

        renderTableBody(state);
        scrollToResult();
    }

    function rowHtml(row, rates) {
        // Ưu tiên ảnh Catalog CS2Cap nếu server đã có cache; nếu chưa có thì placeholder rồi hydrate.
        var iconSrc = row.icon_url ? escapeHtml(row.icon_url) : (placeholderImageUrl ? escapeHtml(placeholderImageUrl) : '');
        var icon = '<img src="' + iconSrc + '" alt="" class="lp-check-item-thumb" loading="lazy" referrerpolicy="no-referrer" ' +
            'data-hash="' + escapeHtml(row.market_hash_name) + '" ' +
            'onerror="window.__cpcs2_onItemImgError && window.__cpcs2_onItemImgError(this)">';
        var buffCell = row.buff_price_cny != null ? fmtCny(row.buff_price_cny) : '<span class="lp-price-pending lp-muted">…</span>';
        var buffErr = row.buff_error ? '<div class="small" style="color:var(--lp-accent)">' + escapeHtml(row.buff_error) + '</div>' : '';

        var empireCells = '';
        if (empireEnabled) {
            var empireCell = '—';
            if (row.empire_price_coins != null) {
                empireCell = fmtNum(row.empire_price_coins) + 'c';
                if (row.empire_price_cny != null) {
                    empireCell += '<div class="lp-muted">≈' + fmtCny(row.empire_price_cny) + '</div>';
                }
            } else if (row.empire_price_usd != null) {
                empireCell = '$' + Number(row.empire_price_usd).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (empireUsdReference) {
                    empireCell += '<div class="lp-muted" style="font-size:0.7rem">CS2Cap · tham khảo</div>';
                }
                if (row.empire_price_cny != null) {
                    empireCell += '<div class="lp-muted">≈' + fmtCny(row.empire_price_cny) + '</div>';
                }
            } else if (!row._priced) {
                empireCell = '<span class="lp-muted">…</span>';
            }
            empireCells =
                '<td class="text-end small">' + empireCell + '</td>' +
                '<td class="text-center small">' + bestVenueBadge(row.best_sell_venue) + '</td>';
        }

        return '<tr data-hash="' + escapeHtml(row.market_hash_name) + '">' +
            '<td>' + icon + '</td>' +
            '<td><div class="fw-medium small">' + escapeHtml(row.name || '') + '</div>' + buffErr + '</td>' +
            '<td class="text-center small">' + (row.amount || 1) + '</td>' +
            '<td class="text-end small">' + buffCell + '</td>' +
            empireCells +
            '<td class="text-end small">' + (row.buff_price_cny != null ? priceCell(row.buff_price_cny, rates) : '<span class="lp-muted">…</span>') + '</td>' +
            '<td class="text-end small fw-semibold">' + (row.line_total_cny != null ? priceCell(row.line_total_cny, rates) : '<span class="lp-muted">…</span>') + '</td>' +
            '</tr>';
    }

    // Fallback ảnh: nếu steamstatic icon lỗi, gọi API lấy image_url từ CS2Cap catalog.
    window.__cpcs2_onItemImgError = function (imgEl) {
        try {
            if (!imgEl) return;
            if (imgEl.dataset.fallbackTried === '1') {
                if (placeholderImageUrl) {
                    imgEl.src = placeholderImageUrl;
                    imgEl.style.display = '';
                } else {
                    imgEl.style.display = 'none';
                }
                return;
            }
            var hash = imgEl.getAttribute('data-hash') || '';
            if (!hash) {
                if (placeholderImageUrl) {
                    imgEl.src = placeholderImageUrl;
                    imgEl.style.display = '';
                } else {
                    imgEl.style.display = 'none';
                }
                return;
            }
            imgEl.dataset.fallbackTried = '1';
            if (!itemImageUrl) {
                if (placeholderImageUrl) {
                    imgEl.src = placeholderImageUrl;
                    imgEl.style.display = '';
                } else {
                    imgEl.style.display = 'none';
                }
                return;
            }
            fetch(itemImageUrl + '?market_hash_name=' + encodeURIComponent(hash), {
                method: 'GET',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (r) { return r.json(); })
              .then(function (j) {
                  if (j && j.ok && j.image_url) {
                      imgEl.onerror = function () {
                          imgEl.onerror = null;
                          if (placeholderImageUrl) {
                              imgEl.src = placeholderImageUrl;
                              imgEl.style.display = '';
                          } else {
                              imgEl.style.display = 'none';
                          }
                      };
                      imgEl.src = j.image_url;
                      imgEl.style.display = '';
                  } else {
                      if (placeholderImageUrl) {
                          imgEl.src = placeholderImageUrl;
                          imgEl.style.display = '';
                      } else {
                          imgEl.style.display = 'none';
                      }
                  }
              }).catch(function () {
                  if (placeholderImageUrl) {
                      imgEl.src = placeholderImageUrl;
                      imgEl.style.display = '';
                  } else {
                      imgEl.style.display = 'none';
                  }
              });
        } catch (e) {}
    };

    function renderTableBody(state) {
        var tbody = document.getElementById('lp-check-tbody');
        if (!tbody) {
            return;
        }
        sortRows(state.rows);
        tbody.innerHTML = state.rows.map(function (row) {
            return rowHtml(row, state.rates);
        }).join('');

        // Thống nhất: luôn hydrate ảnh từ Catalog CS2Cap.
        hydrateCatalogImages(tbody);
    }

    function hydrateCatalogImages(rootEl) {
        if (!itemImageUrl || !rootEl) return;
        var imgs = rootEl.querySelectorAll('img.lp-check-item-thumb[data-hash]');
        if (!imgs || !imgs.length) return;

        var queue = Array.prototype.slice.call(imgs).filter(function (imgEl) {
            // chỉ hydrate khi đang là placeholder/empty
            if (!imgEl) return false;
            var src = imgEl.getAttribute('src') || '';
            if (!src) return true;
            return placeholderImageUrl && src === placeholderImageUrl;
        });
        var concurrency = 4;
        var active = 0;

        function next() {
            while (active < concurrency && queue.length) {
                var imgEl = queue.shift();
                if (!imgEl || imgEl.dataset.fallbackTried === '1') continue;
                active++;
                Promise.resolve()
                    .then(function () { window.__cpcs2_onItemImgError(imgEl); })
                    .finally(function () {
                        active--;
                        next();
                    });
            }
        }

        next();
    }

    function updateHeader(state) {
        var totals = document.getElementById('lp-check-totals');
        var progressEl = document.getElementById('lp-check-progress');
        if (!totals || !progressEl) {
            renderShell(state);
            return;
        }

        state.summary = summarize(state.rows);
        state.pricedCount = state.summary.priced_count;
        state.empirePricedCount = state.summary.empire_priced_count;

        var inv = state.inventory;
        var totalBlock = '';
        if (state.summary.total_cny > 0) {
            totalBlock +=
                '<div class="small lp-muted mb-1">Tổng Buff</div>' +
                '<div class="lp-check-total lp-text-gradient-primary">' + priceCell(state.summary.total_cny, state.rates) + '</div>' +
                '<div class="small lp-muted">¥' + fmtNum(state.summary.total_cny) + '</div>';
        } else if (state.pricingDone) {
            totalBlock += '<div style="color:var(--lp-accent)">Chưa có giá Buff</div>';
        } else {
            totalBlock += '<div class="lp-muted small">Đang lấy giá Buff…</div>';
        }

        if (empireEnabled && state.summary.total_empire_cny > 0) {
            totalBlock +=
                '<div class="small lp-muted mt-3 mb-1">Tổng Empire (ước tính)</div>' +
                '<div class="fw-semibold">' + priceCell(state.summary.total_empire_cny, state.rates) + '</div>' +
                '<div class="small lp-muted">¥' + fmtNum(state.summary.total_empire_cny) + '</div>';
        }

        var progress = state.pricedCount + ' / ' + state.itemCount + ' skin · Buff ' + state.pricedCount + ' có giá';
        if (empireEnabled) {
            progress += ' · Empire ' + state.empirePricedCount;
        }
        if (!state.pricingDone) {
            progress += ' <span class="lp-pulse-dot d-inline-block ms-1"></span>';
        }

        totals.innerHTML = totalBlock;
        progressEl.innerHTML = progress;
    }

    function mergePricedRows(state, pricedItems) {
        var byHash = {};
        state.rows.forEach(function (row, idx) {
            byHash[row.market_hash_name] = idx;
        });

        pricedItems.forEach(function (item) {
            var hash = item.market_hash_name;
            var idx = byHash[hash];
            if (idx === undefined) {
                return;
            }
            var merged = Object.assign({}, state.rows[idx], item, { _priced: true });
            state.rows[idx] = merged;

            var tr = document.querySelector('tr[data-hash="' + CSS.escape(hash) + '"]');
            if (tr) {
                var temp = document.createElement('tbody');
                temp.innerHTML = rowHtml(merged, state.rates);
                tr.replaceWith(temp.firstElementChild);
            }
        });

        updateHeader(state);
    }

    function runPriceBatches(state) {
        var hashes = state.rows.map(function (r) {
            return r.market_hash_name;
        }).filter(Boolean);
        var batches = chunk(hashes, batchSize);
        var chain = Promise.resolve();

        batches.forEach(function (batch) {
            chain = chain.then(function () {
                return postJson(pricesUrl, { token: state.token, hashes: batch }).then(function (data) {
                    if (!data.ok) {
                        throw new Error(data.error || 'Không lấy được giá.');
                    }
                    mergePricedRows(state, data.items || []);
                });
            });
        });

        return chain.then(function () {
            state.pricingDone = true;
            sortRows(state.rows);
            renderTableBody(state);
            updateHeader(state);
        });
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();

        if (!startUrl || !pricesUrl || !host) {
            showError('Tra giá chưa sẵn sàng (thiếu API). Chạy php artisan route:clear trên server rồi tải lại trang.');
            return;
        }

        var urlInput = form.querySelector('input[name="steam_url"]');
        var steamUrl = urlInput ? urlInput.value.trim() : '';
        if (!steamUrl) {
            return;
        }

        setBtnLoading(true);
        showLoading('Đang tải kho đồ Steam…');

        postJson(startUrl, { steam_url: steamUrl })
            .then(function (data) {
                if (!data.ok) {
                    throw new Error(data.error || 'Không tra được kho.');
                }

                var rows = (data.items || []).map(function (item) {
                    return Object.assign({}, item, { _priced: false });
                });

                var state = {
                    token: data.token,
                    rates: data.rates || { cny_to_vnd: 3750, vnd_to_usd: 26700 },
                    inventory: data.inventory || {},
                    itemCount: data.item_count || rows.length,
                    rows: rows,
                    pricedCount: 0,
                    empirePricedCount: 0,
                    pricingDone: false,
                    summary: summarize(rows),
                };

                if (data.batch_size) {
                    batchSize = data.batch_size;
                }

                renderShell(state);
                return runPriceBatches(state);
            })
            .catch(function (err) {
                showError(err.message || 'Lỗi kết nối. Thử lại sau.');
            })
            .finally(function () {
                setBtnLoading(false);
            });
    });
})();
