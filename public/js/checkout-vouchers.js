// Checkout vouchers: live preview of the discounts and total while the buyer picks vouchers.
// The server recalculates everything when the order is placed (VoucherService).
(function () {
    'use strict';

    var peso = function (n) {
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    document.addEventListener('DOMContentLoaded', function () {
        var totals = document.getElementById('checkoutTotals');
        if (!totals) return;

        var subtotal = parseFloat(totals.dataset.subtotal) || 0;
        var shipping = parseFloat(totals.dataset.shipping) || 0;
        var sellerSelects = Array.prototype.slice.call(document.querySelectorAll('select[data-kind="seller"]'));
        var platform = document.getElementById('platformVoucher');
        var note = document.getElementById('platformNote');

        function platformDiscount(base) {
            var opt = platform && platform.options[platform.selectedIndex];
            if (note) note.classList.add('hidden');
            if (!opt || !opt.value) return 0;
            var min = parseFloat(opt.dataset.min) || 0;
            if (base < min) {
                note.textContent = 'Needs a minimum spend of ' + peso(min) + ' — add ' + peso(min - base) + ' more.';
                note.classList.remove('hidden');
                return 0;
            }
            note.classList.add('hidden');
            var value = parseFloat(opt.dataset.value) || 0;
            var d = opt.dataset.type === 'percent' ? base * value / 100 : value;
            var max = parseFloat(opt.dataset.max);
            if (opt.dataset.type === 'percent' && max) d = Math.min(d, max);
            return Math.round(Math.max(0, Math.min(d, base)) * 100) / 100;
        }

        function show(rowId, valueId, amount) {
            var row = document.getElementById(rowId);
            document.getElementById(valueId).textContent = '−' + peso(amount);
            row.classList.toggle('hidden', !amount);
            row.classList.toggle('flex', !!amount);
        }

        function update() {
            var sellerOff = sellerSelects.reduce(function (sum, sel) {
                var opt = sel.options[sel.selectedIndex];
                return sum + (parseFloat(opt && opt.dataset.discount) || 0);
            }, 0);
            var platformOff = platformDiscount(subtotal - sellerOff);
            show('rowSellerDiscount', 'sellerDiscount', sellerOff);
            show('rowPlatformDiscount', 'platformDiscount', platformOff);

            var total = Math.max(0, subtotal - sellerOff - platformOff) + shipping;
            document.querySelectorAll('[data-checkout-total]').forEach(function (el) { el.textContent = peso(total); });
        }

        document.querySelectorAll('.v-select').forEach(function (sel) { sel.addEventListener('change', update); });

        // "Have a code?": pick it in whichever list has it; otherwise send it as the ALVY
        // voucher so the server can say why it doesn't work.
        var codeInput = document.getElementById('voucherCode');
        var msg = document.getElementById('voucherCodeMsg');
        var extra = null;
        document.getElementById('voucherApply').addEventListener('click', function () {
            var code = (codeInput.value || '').trim().toUpperCase();
            if (!code) return;
            var selects = sellerSelects.concat(platform ? [platform] : []);
            for (var i = 0; i < selects.length; i++) {
                var opt = Array.prototype.find.call(selects[i].options, function (o) { return o.value === code; });
                if (opt) {
                    if (opt.disabled) {
                        msg.textContent = code + ' can\'t be used: ' + (opt.textContent.split(' — ').pop() || 'not available') ;
                        msg.style.color = '#DC2626';
                    } else {
                        selects[i].value = code;
                        msg.textContent = code + ' applied.';
                        msg.style.color = '#059669';
                        update();
                    }
                    msg.classList.remove('hidden');
                    return;
                }
            }
            // Not in the lists (e.g. not started yet): add it to the ALVY list and let the server check it.
            if (platform) {
                if (!extra) {
                    extra = new Option(code + ' — will be checked when you place the order', code);
                    platform.add(extra);
                } else {
                    extra.value = code;
                    extra.textContent = code + ' — will be checked when you place the order';
                }
                platform.value = code;
                msg.textContent = 'We\'ll check ' + code + ' when you place your order.';
                msg.style.color = '#B45309';
                msg.classList.remove('hidden');
                update();
            }
        });

        update();
    });
})();
