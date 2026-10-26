// Checkout voucher pickers: a bar showing the applied voucher, opening a list of tickets.
// Picking a ticket sets the hidden <select class="v-select"> and fires "change", so
// checkout.js asks the server for the new totals exactly as before.
(function () {
    'use strict';

    function selectOf(id) { return document.getElementById(id); }

    // Bar text: the applied voucher's chip, or how many are available.
    function sync(box) {
        var select = selectOf(box.dataset.vp);
        var state = box.querySelector('[data-vp-state]');
        if (!select || !state) return;
        var opt = select.options[select.selectedIndex];
        var applied = select.value && opt;
        state.classList.toggle('is-applied', !!applied);
        // No vouchers of this kind and none applied: hide the bar.
        box.classList.toggle('hidden', select.options.length <= 1 && !applied);
        state.textContent = applied ? (opt.dataset.chip || opt.textContent) : state.dataset.empty;
    }
    function syncAll() { document.querySelectorAll('[data-vp]').forEach(sync); }

    function open(id) {
        var modal = document.getElementById(id + '-modal');
        var select = selectOf(id);
        if (!modal || !select) return;
        var current = modal.querySelector('input[type=radio][value="' + CSS.escape(select.value) + '"]')
            || modal.querySelector('input[type=radio][value=""]');
        if (current) current.checked = true;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        (current || modal.querySelector('[data-vp-close]')).focus();
    }

    function close(modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        var bar = document.querySelector('[data-vp-open="' + modal.id.replace(/-modal$/, '') + '"]');
        if (bar) bar.focus();
    }

    function confirm(modal) {
        var id = modal.id.replace(/-modal$/, '');
        var select = selectOf(id);
        var picked = modal.querySelector('input[type=radio]:checked');
        var code = picked ? picked.value : '';
        if (select && select.value !== code) {
            select.value = code;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        sync(modal.closest('[data-vp]'));
        close(modal);
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-vp-open]');
        if (openBtn) { open(openBtn.dataset.vpOpen); return; }
        var modal = e.target.closest('.vp-modal');
        if (!modal) return;
        if (e.target === modal || e.target.closest('[data-vp-close]')) close(modal);
        else if (e.target.closest('[data-vp-ok]')) confirm(modal);
    });
    // Double-click a ticket to pick it straight away.
    document.addEventListener('dblclick', function (e) {
        var item = e.target.closest('.vp-item:not(.is-blocked), .vp-none');
        if (item) confirm(item.closest('.vp-modal'));
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var modal = document.querySelector('.vp-modal:not(.hidden)');
        if (modal) close(modal);
    });

    // The server may change applied vouchers (best pick, typed code, no longer valid).
    document.addEventListener('checkout:rendered', syncAll);
    document.addEventListener('change', function (e) { if (e.target.matches && e.target.matches('select.v-select')) syncAll(); });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', syncAll); else syncAll();
})();
