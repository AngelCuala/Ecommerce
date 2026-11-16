{{-- "Remove …?" before a form takes something out of the cart or the wishlist.
     Put data-confirm-remove="Title|Message" on the form; the form is sent only after "Remove". --}}
<div id="cr-modal" class="fixed inset-0 z-[90] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="cr-title"
     style="background:rgba(28,25,23,.45);">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-xl">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full" style="background:#FEF2F2;color:#DC2626;">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
        </span>
        <h2 id="cr-title" class="mt-3 font-display text-lg font-extrabold" style="color:#222;"></h2>
        <p id="cr-text" class="mt-1 text-sm" style="color:#78716C;"></p>
        <div class="mt-5 flex justify-center gap-3">
            <button type="button" id="cr-keep" class="rounded-lg border px-5 py-2 text-sm font-semibold" style="border-color:#E7E5E4;color:#44403C;">Keep it</button>
            <button type="button" id="cr-go" class="rounded-lg px-5 py-2 text-sm font-semibold text-white" style="background:#DC2626;">Remove</button>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('cr-modal'), pending = null;
    function close(go) {
        modal.classList.add('hidden'); modal.classList.remove('flex');
        var f = pending; pending = null;
        if (!f) return;
        if (go) { f.dataset.crOk = '1'; f.requestSubmit ? f.requestSubmit() : f.submit(); return; }
        // Kept: undo the heart's quick toggle (Buyer.js) and let it be pressed again.
        var heart = f.querySelector('.wishlist-btn');
        if (heart) { heart.classList.add('is-wished'); heart.style.color = '#fa4e1c'; delete heart.dataset.busy; }
    }
    document.addEventListener('submit', function (e) {
        var f = e.target, ask = f.getAttribute && f.getAttribute('data-confirm-remove');
        if (!ask || f.dataset.crOk) return;
        e.preventDefault();
        var parts = ask.split('|');
        document.getElementById('cr-title').textContent = parts[0];
        document.getElementById('cr-text').textContent = parts[1] || '';
        pending = f;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('cr-go').focus();
    }, true);
    document.getElementById('cr-keep').addEventListener('click', function () { close(false); });
    document.getElementById('cr-go').addEventListener('click', function () { close(true); });
    modal.addEventListener('click', function (e) { if (e.target === modal) close(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && pending) close(false); });
})();
</script>
