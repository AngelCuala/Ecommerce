{{--
    The Return / Refund pop-up for one order: the order's details, the items to return with what each refunds, and the
    request form (reason, items, description, photos). Used on My Purchases and on the order page.
    Open it with a button: onclick="document.getElementById('return-dialog-{{ $order->id }}').showModal()".
    After a validation error the page comes back with the pop-up open again (return_for = this order).
--}}
@php
    $dialogId = 'return-dialog-' . $order->id;
    $store = $order->seller?->sellerApplication?->shop_name ?? $order->seller?->name ?? 'the seller';
    $reopen = $errors->any() && (int) old('return_for') === (int) $order->id;
    $oldItems = $reopen ? array_map('intval', (array) old('items', [])) : null;
    $refundTo = $order->isPaidOnline() ? 'back to your ' . $order->paymentMethodLabel() . ' account' : 'to your refund account (cash on delivery)';
@endphp
<dialog id="{{ $dialogId }}" class="rf-dialog" aria-labelledby="{{ $dialogId }}-title" data-return-dialog="{{ $order->id }}">
    <form method="dialog" class="rf-close-wrap"><button class="rf-close" aria-label="Close">✕</button></form>

    <header class="rf-head">
        <p class="rf-eyebrow">Return / Refund</p>
        <h2 id="{{ $dialogId }}-title" class="rf-title">Order {{ $order->number() }}</h2>
        <p class="rf-sub">{{ $store }} · delivered {{ $order->delivered_at?->format('M j, Y') ?? '—' }} · request a return until
            <strong>{{ $order->returnDeadline()?->format('M j, Y g:i A') }}</strong></p>
    </header>

    <form action="{{ route('orders.return', $order->id) }}" method="POST" enctype="multipart/form-data" class="rf-body">
        @csrf
        <input type="hidden" name="return_for" value="{{ $order->id }}">

        @if ($reopen)
            <div class="rf-error">{{ $errors->first() }}</div>
        @endif

        {{-- 1. Which items --}}
        <section>
            <h3 class="rf-step"><span>1</span> Items to return</h3>
            <div class="rf-items">
                @foreach ($order->items as $ri)
                    @php $refund = \App\Models\ReturnRequest::itemRefund($ri); @endphp
                    <label class="rf-item" data-return-item="{{ $ri->id }}">
                        <input type="checkbox" name="items[]" value="{{ $ri->id }}" data-refund="{{ $refund }}" @checked($oldItems === null || in_array($ri->id, $oldItems, true)) @disabled($ri->refunded_at)>
                        <img src="{{ $ri->product?->image ? asset('storage/' . $ri->product->image) : 'https://placehold.co/56x56/f5f5f5/999?text=P' }}" alt="">
                        <span class="rf-item-text">
                            <strong>{{ $ri->product->title ?? 'Item' }}</strong>
                            <small>{{ $ri->variation_name ? $ri->variation_name . ' · ' : '' }}× {{ $ri->quantity }}{{ $ri->refunded_at ? ' · already refunded' : '' }}</small>
                        </span>
                        <span class="rf-item-amt">₱{{ number_format($refund, 2) }}</span>
                    </label>
                @endforeach
            </div>
            <p class="rf-total">Refund: <strong data-refund-total>₱0.00</strong> <span>goes {{ $refundTo }}.</span></p>
            <p class="rf-hint">Untick items you are keeping. Each item refunds its price minus its share of the vouchers.</p>
        </section>

        {{-- 2. Why --}}
        <section>
            <h3 class="rf-step"><span>2</span> What's wrong</h3>
            <label class="rf-label" for="{{ $dialogId }}-reason">Reason</label>
            <select id="{{ $dialogId }}-reason" name="reason" class="pf-input" required>
                <option value="">Choose a reason</option>
                @foreach (\App\Models\ReturnRequest::REASONS as $reason)<option @selected($reopen && old('reason') === $reason)>{{ $reason }}</option>@endforeach
            </select>
            <label class="rf-label" for="{{ $dialogId }}-details">Describe the problem <span>10 to 500 characters</span></label>
            <textarea id="{{ $dialogId }}-details" name="details" rows="3" minlength="10" maxlength="500" required class="pf-input"
                      placeholder="e.g. The bag arrived with a torn strap.">{{ $reopen ? old('details') : '' }}</textarea>
        </section>

        {{-- 3. Photos --}}
        <section>
            <h3 class="rf-step"><span>3</span> Photo evidence</h3>
            <label class="rf-drop">
                <input type="file" name="evidence_photos[]" multiple accept="image/jpeg,image/png" required data-return-photos>
                <span><strong>Add 1 to {{ \App\Models\ReturnRequest::MAX_PHOTOS }} photos</strong> of the item and the problem · JPG or PNG, up to 5 MB each</span>
            </label>
            <div class="rf-previews" data-return-previews></div>
        </section>

        <section class="rf-next">
            <p><strong>What happens next:</strong> {{ $store }} has {{ \App\Models\ReturnRequest::RESPOND_DAYS }} days to answer (no answer
                means it is approved). If approved, a rider collects the item from you and you are refunded. If rejected, you can ask ALVY to review it.</p>
        </section>

        <div class="rf-actions">
            <button type="button" class="pf-btn-ghost" onclick="this.closest('dialog').close()">Cancel</button>
            <button class="pf-btn px-6">Submit return request</button>
        </div>
    </form>
</dialog>

@once
    <style>
        .rf-dialog { position: fixed; inset: 0; margin: auto; height: fit-content; max-height: 92vh; width: min(640px, 94vw); overflow: auto;
                     border: 0; border-radius: 18px; padding: 0; box-shadow: 0 24px 60px rgba(0,0,0,.25); color: #222; background: #fff; }
        .rf-dialog::backdrop { background: rgba(28, 25, 23, .5); }
        .rf-close-wrap { position: absolute; top: 12px; right: 12px; }
        .rf-close { width: 32px; height: 32px; border-radius: 999px; border: 0; background: #F5F0EC; color: #57534E; font-size: 14px; cursor: pointer; }
        .rf-head { padding: 22px 24px 14px; border-bottom: 1px solid #F0E4DC; background: #FFF8F4; }
        .rf-eyebrow { font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #fa4e1c; }
        .rf-title { margin-top: 2px; font-size: 20px; font-weight: 800; }
        .rf-sub { margin-top: 2px; font-size: 13px; color: #78716C; }
        .rf-body { display: flex; flex-direction: column; gap: 18px; padding: 18px 24px 22px; }
        .rf-step { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 14px; font-weight: 800; }
        .rf-step span { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 999px; background: #fa4e1c; color: #fff; font-size: 12px; }
        .rf-items { display: flex; flex-direction: column; gap: 6px; }
        .rf-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #F0E4DC; border-radius: 12px; cursor: pointer; }
        .rf-item:has(input:checked) { border-color: #fa4e1c; background: #FFF6F2; }
        .rf-item:has(input:disabled) { opacity: .55; cursor: default; }
        .rf-item img { width: 46px; height: 46px; border-radius: 8px; object-fit: cover; }
        .rf-item-text { flex: 1; min-width: 0; display: flex; flex-direction: column; font-size: 13px; }
        .rf-item-text small { color: #78716C; }
        .rf-item-amt { font-size: 13px; font-weight: 700; }
        .rf-total { margin-top: 8px; font-size: 14px; }
        .rf-total strong { color: #fa4e1c; }
        .rf-total span, .rf-hint { font-size: 12px; color: #78716C; }
        .rf-label { display: block; margin: 8px 0 4px; font-size: 12px; font-weight: 700; color: #57534E; }
        .rf-label span { font-weight: 400; color: #A8A29E; }
        .rf-drop { display: block; padding: 14px; border: 1.5px dashed #E7D6CC; border-radius: 12px; font-size: 12.5px; color: #78716C; cursor: pointer; }
        .rf-drop input { display: block; margin-bottom: 6px; font-size: 13px; }
        .rf-previews { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .rf-previews img { width: 64px; height: 64px; border-radius: 8px; object-fit: cover; border: 1px solid #F0E4DC; }
        .rf-next { padding: 12px 14px; border-radius: 12px; background: #F5F9FF; font-size: 12.5px; color: #1E3A5F; }
        .rf-error { padding: 10px 12px; border-radius: 10px; background: #FEF2F2; color: #B91C1C; font-size: 13px; }
        .rf-actions { display: flex; justify-content: flex-end; gap: 10px; }
    </style>
    <script>
        (function (start) { document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start(); })(function () {
            document.querySelectorAll('[data-return-dialog]').forEach(function (dialog) {
                // Refund total follows the ticked items.
                var total = dialog.querySelector('[data-refund-total]');
                var boxes = dialog.querySelectorAll('input[name="items[]"]');
                function sum() {
                    var s = 0;
                    boxes.forEach(function (b) { if (b.checked && ! b.disabled) s += parseFloat(b.dataset.refund || 0); });
                    total.textContent = '₱' + s.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                boxes.forEach(function (b) { b.addEventListener('change', sum); });
                sum();
                // Thumbnails of the chosen photos.
                var input = dialog.querySelector('[data-return-photos]'), box = dialog.querySelector('[data-return-previews]');
                input.addEventListener('change', function () {
                    box.innerHTML = '';
                    Array.from(input.files).slice(0, {{ \App\Models\ReturnRequest::MAX_PHOTOS }}).forEach(function (f) {
                        var img = document.createElement('img'); img.src = URL.createObjectURL(f); img.alt = ''; box.appendChild(img);
                    });
                });
            });
        });
    </script>
@endonce

@if ($reopen)
    <script>(function (open) { document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', open) : open(); })(function () { document.getElementById('{{ $dialogId }}').showModal(); });</script>
@endif
