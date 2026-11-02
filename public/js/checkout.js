// Checkout: when the address or a voucher changes, ask the server for the new summary
// (per-store shipping, discounts, total). The server does all the math.
(function () {
    'use strict';

    var form = document.getElementById('checkoutForm');
    if (!form) return;

    var peso = function (n) {
        return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    var token = form.querySelector('input[name="_token"]').value;
    var placeBtn = document.getElementById('placeOrder');
    var codeInput = document.getElementById('voucherCode');
    var codeMsg = document.getElementById('voucherCodeMsg');
    var pending = null;

    function payload(extra) {
        var data = new FormData();
        ['address_id', 'platform_voucher', 'shipping_voucher', 'buy_now'].forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('select[name="' + name + '"], input[type="hidden"][name="' + name + '"]');
            if (el) data.append(name, el.value);
        });
        form.querySelectorAll('select[name^="seller_vouchers"]').forEach(function (s) { data.append(s.name, s.value); });
        if (extra) Object.keys(extra).forEach(function (k) { data.append(k, extra[k]); });
        return data;
    }

    function setText(selector, text) {
        var el = form.querySelector(selector);
        if (el) el.textContent = text;
    }

    function toggleRow(selector, show) {
        var el = form.querySelector(selector);
        if (el) el.classList.toggle('hidden', !show);
    }

    // Make sure a select shows the applied code (code-only vouchers aren't in the list).
    function choose(select, code) {
        if (!select) return;
        code = code || '';
        if (code && !Array.prototype.some.call(select.options, function (o) { return o.value === code; })) {
            select.add(new Option(code, code));
        }
        select.value = code;
    }

    function render(d) {
        var unserviceable = document.getElementById('unserviceableMsg');
        unserviceable.textContent = d.unserviceable || '';
        unserviceable.classList.toggle('hidden', !d.unserviceable);
        placeBtn.disabled = !!d.unserviceable;

        Object.keys(d.stores).forEach(function (id) {
            var s = d.stores[id];
            setText('[data-store-shipping="' + id + '"]', s.shipping === null ? 'Select an address' : peso(s.shipping));
            var feeEl = form.querySelector('[data-store-shipping="' + id + '"]');
            if (feeEl) feeEl.style.color = s.shipping === null ? '#a39890' : '';
            setText('[data-store-weight="' + id + '"]', s.weight === null ? '' : '· ' + (s.route ? s.route + ' · ' : '') + s.chargeable + ' kg');
            setText('[data-store-discount="' + id + '"]', '−' + peso(s.discount));
            toggleRow('[data-store-discount-row="' + id + '"]', s.discount > 0);
        });

        setText('[data-total="subtotal"]', peso(d.subtotal));
        setText('[data-total="shipping_total"]', d.unserviceable ? 'Select an address' : peso(d.shipping_total));
        var shipTotal = form.querySelector('[data-total="shipping_total"]');
        if (shipTotal) shipTotal.style.color = d.unserviceable ? '#a39890' : '';
        ['promo_discount', 'seller_discount', 'platform_discount', 'shipping_discount'].forEach(function (k) {
            setText('[data-total="' + k + '"]', '−' + peso(d[k]));
            toggleRow('[data-row="' + k + '"]', d[k] > 0);
        });
        setText('[data-total="total"]', peso(d.total));

        // Each line's quantity, price and subtotal (after − / +), and the prices the order is placed at.
        (d.lines || []).forEach(function (l) {
            var box = form.querySelector('[data-qty-line="' + l.key + '"]');
            if (box) {
                box.dataset.qty = l.quantity;
                box.dataset.stock = l.stock;
                box.querySelector('[data-qty-value]').textContent = l.quantity;
                box.querySelector('[data-qty-step="-1"]').disabled = l.quantity <= 1;
                box.querySelector('[data-qty-step="1"]').disabled = l.quantity >= l.stock;
            }
            setText('[data-line-price="' + l.key + '"]', l.price);
            setText('[data-line-subtotal="' + l.key + '"]', peso(l.subtotal));
        });
        Object.keys(d.prices || {}).forEach(function (id) {
            var input = form.querySelector('input[name="prices[' + id + ']"]');
            if (input) input.value = d.prices[id];
        });

        choose(document.getElementById('platformVoucher'), d.applied.platform);
        choose(document.getElementById('shippingVoucher'), d.applied.shipping);
        form.querySelectorAll('select[name^="seller_vouchers"]').forEach(function (s) {
            var id = s.name.match(/\[(\d+)\]/)[1];
            choose(s, d.applied.sellers[id]);
        });

        var box = document.getElementById('voucherNotices');
        box.innerHTML = '';
        d.notices.forEach(function (n) {
            var p = document.createElement('p');
            p.className = 'rounded-md px-2.5 py-1.5 text-xs';
            p.style.cssText = 'background:#FFFBEB;color:#92400E;';
            p.textContent = n;
            box.appendChild(p);
        });

        // Let the voucher pickers (voucher-picker.js) update their labels.
        document.dispatchEvent(new CustomEvent('checkout:rendered'));
    }

    function refresh(extra) {
        if (pending) pending.abort();
        pending = new AbortController();
        placeBtn.disabled = true;
        return fetch(form.dataset.previewUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: payload(extra),
            signal: pending.signal,
        }).then(function (r) { return r.json(); }).then(function (d) {
            render(d);
            return d;
        }).catch(function (e) {
            if (e.name !== 'AbortError') placeBtn.disabled = false;
        });
    }

    // − / + on a line: the server saves the quantity (stock and per-buyer checks) and sends the new summary.
    form.addEventListener('click', function (e) {
        var step = e.target.closest('[data-qty-step]');
        if (!step) return;
        e.preventDefault();
        var box = step.closest('[data-qty-line]');
        var key = box.dataset.qtyLine;
        var qty = Math.min(Math.max((+box.dataset.qty || 1) + (+step.dataset.qtyStep), 1), +box.dataset.stock || 99999);
        if (qty === +box.dataset.qty) return;
        var msg = form.querySelector('[data-qty-msg="' + key + '"]');
        var buttons = box.querySelectorAll('button');
        buttons.forEach(function (b) { b.disabled = true; });
        placeBtn.disabled = true;
        fetch(form.dataset.quantityUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: payload({ line: key, quantity: qty }),
        }).then(function (r) {
            return r.json().then(function (d) { return { ok: r.ok, d: d }; });
        }).then(function (res) {
            if (res.ok) {
                msg.classList.add('hidden');
                render(res.d);
            } else {
                msg.textContent = res.d.message || 'The quantity could not be changed. Please try again.';
                msg.classList.remove('hidden');
                box.querySelector('[data-qty-step="-1"]').disabled = +box.dataset.qty <= 1;
                box.querySelector('[data-qty-step="1"]').disabled = +box.dataset.qty >= +box.dataset.stock;
                placeBtn.disabled = !!document.getElementById('unserviceableMsg').textContent.trim();
            }
        }).catch(function () {
            msg.textContent = 'The quantity could not be changed. Please check your connection and try again.';
            msg.classList.remove('hidden');
            buttons.forEach(function (b) { b.disabled = false; });
            placeBtn.disabled = false;
        });
    });

    form.addEventListener('change', function (e) {
        if (e.target.matches('input[name="address_id"], select.v-select')) refresh();
    });

    document.getElementById('voucherApply').addEventListener('click', function () {
        var code = (codeInput.value || '').trim().toUpperCase();
        if (!code) return;
        refresh({ voucher_code: code }).then(function (d) {
            if (!d) return;
            var error = d.errors && d.errors.voucher_code;
            var applied = [d.applied.platform, d.applied.shipping].concat(Object.values(d.applied.sellers)).indexOf(code) !== -1;
            codeMsg.classList.remove('hidden');
            if (error) {
                codeMsg.textContent = error;
                codeMsg.style.color = '#DC2626';
            } else if (applied) {
                codeMsg.textContent = code + ' applied.';
                codeMsg.style.color = '#059669';
                codeInput.value = '';
            } else {
                codeMsg.textContent = d.notices.join(' ') || (code + ' can\'t be used on this order.');
                codeMsg.style.color = '#DC2626';
            }
        });
    });
    codeInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('voucherApply').click(); }
    });

    // One click places the order; further clicks do nothing (the server also refuses a repeat).
    form.addEventListener('submit', function (e) {
        if (form.dataset.submitting) { e.preventDefault(); return; }
        form.dataset.submitting = '1';
        placeBtn.disabled = true;
        placeBtn.textContent = 'Placing order…';
    });
})();
