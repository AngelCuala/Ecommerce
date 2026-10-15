// Product page: choose every option group (e.g. Color and Size), show that variant's price and
// stock, disable options that are sold out, keep the quantity within stock and the per-buyer
// limit, and count down a sale.
(function () {
    'use strict';

    var PD = window.PD || { variants: [], groups: 0 };
    var form = document.getElementById('pd-form');
    if (!form) return;

    var peso = function (n) { return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var qty = document.getElementById('pd-qty');
    var qtyMsg = document.getElementById('pd-qty-msg');
    var hidden = document.getElementById('pd-variation');
    var varMsg = document.getElementById('pd-variation-msg');
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.variation-btn[data-group]'));
    var chosen = [];

    function matches(v, picks) {
        return picks.every(function (val, i) { return val === undefined || v.parts[i] === val; });
    }

    function current() {
        if (chosen.length < PD.groups || chosen.some(function (x) { return x === undefined; })) return null;
        return PD.variants.find(function (v) { return matches(v, chosen); }) || null;
    }

    function limit() {
        var v = current();
        var stock = v ? v.stock : PD.maxQty;
        var max = PD.allowance === null || PD.allowance === undefined ? stock : Math.min(stock, PD.allowance);
        return Math.max(1, max);
    }

    function refresh() {
        // An option is disabled when no in-stock variant has it together with the other choices.
        buttons.forEach(function (b) {
            var g = +b.dataset.group;
            var picks = chosen.slice();
            picks[g] = b.dataset.value;
            var possible = PD.variants.some(function (v) { return v.stock > 0 && matches(v, picks); });
            b.disabled = !possible;
            b.classList.toggle('is-active', chosen[g] === b.dataset.value);
        });

        var v = current();
        if (hidden) hidden.value = v ? v.id : '';
        showVariantImage();
        if (!v && PD.range) {
            // Nothing (or only part) chosen yet: show the lowest to highest option price.
            document.getElementById('pd-price').textContent = PD.range;
            var b0 = document.getElementById('pd-base'), p0 = document.getElementById('pd-pct');
            if (b0) b0.style.display = 'none';
            if (p0) p0.style.display = 'none';
        }
        if (v) {
            document.getElementById('pd-price').textContent = peso(v.price);
            // The struck-through price and % only show when this option is actually discounted.
            var base = document.getElementById('pd-base');
            var pct = document.getElementById('pd-pct');
            var off = v.base > v.price;
            if (base) { base.textContent = peso(v.base); base.style.display = off ? '' : 'none'; }
            if (pct) { pct.textContent = '-' + Math.round(100 - v.price / v.base * 100) + '%'; pct.style.display = off ? '' : 'none'; }
            var stock = document.getElementById('pd-stock');
            if (stock) stock.textContent = v.stock;
            if (varMsg) varMsg.classList.add('hidden');
        }
        qty.max = limit();
        if (+qty.value > +qty.max) qty.value = qty.max;
    }

    // A variant (or first option, e.g. a color) with its own photo switches the main image.
    var main = document.getElementById('main-img');
    var mainDefault = main ? main.src : '';
    function showVariantImage() {
        if (!main) return;
        var withImage = PD.variants.find(function (v) { return v.image && matches(v, chosen) && chosen.some(function (x) { return x !== undefined; }); });
        var src = withImage ? withImage.image : mainDefault;
        if (main.src !== src) main.src = src;
    }

    buttons.forEach(function (b) {
        b.addEventListener('click', function () {
            var g = +b.dataset.group;
            chosen[g] = chosen[g] === b.dataset.value ? undefined : b.dataset.value;
            refresh();
        });
    });

    // Pre-select from a failed submit (old input).
    if (hidden && hidden.value) {
        var pre = PD.variants.find(function (v) { return String(v.id) === hidden.value; });
        if (pre) chosen = pre.parts.slice();
    }

    // Quantity must be a whole number from 1 to the limit.
    function checkQty() {
        var raw = (qty.value || '').trim();
        var n = Number(raw);
        var msg = '';
        if (!/^\d+$/.test(raw) || n < 1) msg = 'Enter a whole number of at least 1.';
        else if (n > +qty.max) msg = PD.allowance !== null && PD.allowance !== undefined && n > PD.allowance
            ? 'You can buy at most ' + PD.allowance + ' of this item.'
            : 'Only ' + qty.max + ' available.';
        qtyMsg.textContent = msg;
        qtyMsg.classList.toggle('hidden', !msg);
        return !msg;
    }
    qty.addEventListener('input', checkQty);

    form.addEventListener('submit', function (e) {
        if (PD.groups > 0 && !current()) {
            e.preventDefault();
            if (varMsg) varMsg.classList.remove('hidden');
            return;
        }
        if (!checkQty()) e.preventDefault();
    });

    refresh();

    // Sale countdown.
    var cd = document.getElementById('pd-countdown');
    if (cd) {
        var ends = new Date(cd.dataset.ends).getTime();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var tick = function () {
            var s = Math.max(0, Math.floor((ends - Date.now()) / 1000));
            var d = Math.floor(s / 86400); s -= d * 86400;
            var h = Math.floor(s / 3600); s -= h * 3600;
            var m = Math.floor(s / 60); s -= m * 60;
            cd.textContent = (d ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s);
        };
        tick();
        var timer = setInterval(function () {
            tick();
            // At 0 the sale is over: reload once so the normal price shows everywhere.
            if (Date.now() >= ends) {
                clearInterval(timer);
                cd.textContent = 'ended';
                setTimeout(function () { window.location.reload(); }, 1200);
            }
        }, 1000);
    }
})();
