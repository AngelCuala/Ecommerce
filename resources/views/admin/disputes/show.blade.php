<x-admin-layout title="Dispute · {{ $order->number() }}" active="disputes">
@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tz = config('app.business_timezone');
    $when = fn ($t) => $t ? $t->copy()->tz($tz)->format('M j, Y g:i A') : '—';
    $open = $return->status === 'disputed';
    $photos = $return->photos();
@endphp
<style>
    .dc-grid { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); align-items: start; }
    @media (max-width: 1000px) { .dc-grid { grid-template-columns: 1fr; } }
    .dc-h { font-family: inherit; font-size: .95rem; font-weight: 800; color: #222; margin: 0 0 .75rem; }
    .dc-kv { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px solid #F1F5F9; font-size: .85rem; }
    .dc-kv span:first-child { color: #6b90aa; }
    .dc-quote { margin-top: .5rem; padding: .75rem .9rem; border-radius: .6rem; font-size: .88rem; line-height: 1.45; }
    .dc-side { display: flex; flex-direction: column; gap: 1.25rem; }
    .dc-photos { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .6rem; }
    .dc-photos a { display: block; width: 92px; height: 92px; border-radius: .5rem; overflow: hidden; border: 1px solid #E5EDF3; }
    .dc-photos img { width: 100%; height: 100%; object-fit: cover; }
    .dc-steps { position: relative; margin: 0; padding: 0; list-style: none; }
    .dc-steps li { position: relative; padding: 0 0 .9rem 1.4rem; font-size: .84rem; }
    .dc-steps li::before { content: ''; position: absolute; left: .25rem; top: .35rem; width: .55rem; height: .55rem; border-radius: 99px; background: #CBD5E1; }
    .dc-steps li::after { content: ''; position: absolute; left: .5rem; top: 1rem; bottom: 0; width: 1px; background: #E2E8F0; }
    .dc-steps li:last-child::after { display: none; }
    .dc-steps li.is-key::before { background: #fa4e1c; }
    .dc-steps small { display: block; color: #6b90aa; font-size: .75rem; }
    .dc-choice { display: flex; gap: .6rem; align-items: flex-start; padding: .75rem; border: 1px solid #E5EDF3; border-radius: .6rem; cursor: pointer; font-size: .86rem; }
    .dc-choice:has(input:checked) { border-color: #fa4e1c; background: #FFF6F2; }
</style>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div>
        <a href="{{ route('admin.disputes.index') }}" class="text-sm font-semibold" style="color:#6b90aa;">← Return Disputes</a>
        <h1 class="font-display text-xl font-bold" style="color:#222;">Dispute on order {{ $order->number() }}</h1>
        <p class="text-sm" style="color:#6b90aa;">{{ $return->user?->name }} vs {{ $sellerName }} · escalated {{ $when($return->escalated_at) }}</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="rounded-full px-3 py-1 text-xs font-bold" data-dispute-status style="{{ $open ? 'background:#FEF2F2;color:#B91C1C;' : ($return->admin_decision === 'refund' ? 'background:#ECFDF3;color:#16A34A;' : 'background:#F3F4F6;color:#4B5563;') }}">
            {{ $open ? 'Waiting for your decision' : ($return->admin_decision === 'refund' ? 'Decided: refunded' : 'Decided: closed, no refund') }}</span>
        <a href="{{ route('admin.orders.show', $order->id) }}" class="rounded-md border px-3 py-1.5 text-xs font-semibold">Open the order</a>
    </div>
</div>

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">{{ session('error') }}</div>
@endif

<div class="dc-grid">
    <div class="flex flex-col gap-5">
        {{-- 1. What the buyer wants back --}}
        <div class="card p-5" data-dispute-items>
            <h2 class="dc-h">Items being returned</h2>
            @foreach ($items as $item)
                <div class="dc-kv"><span>{{ $item->product?->title ?? 'Product' }}{{ $item->variation_name ? ' · ' . $item->variation_name : '' }} × {{ $item->quantity }}</span>
                    <span>{{ $peso(\App\Models\ReturnRequest::itemRefund($item)) }}</span></div>
            @endforeach
            <div class="dc-kv" style="border:0;font-weight:800;"><span style="color:#222;">Refund the buyer asks for</span><span>{{ $peso($return->refund_amount) }}</span></div>
            <p class="text-xs" style="color:#6b90aa;">Order total {{ $peso($order->total_price) }} · paid by {{ $order->paymentMethodLabel() }} · {{ $return->isPartial() ? 'only these items' : 'the whole order' }}.</p>
        </div>

        {{-- 2. The buyer's request --}}
        <div class="card p-5" data-dispute-request>
            <h2 class="dc-h">1. The buyer's return request <small class="text-xs font-normal" style="color:#6b90aa;">{{ $when($return->created_at) }}</small></h2>
            <div class="dc-kv"><span>Reason</span><span><strong>{{ $return->reason }}</strong></span></div>
            @if ($return->details)<div class="dc-quote" style="background:#F8FAFC;">{{ $return->details }}</div>@endif
            @if ($photos)
                <div class="dc-photos">
                    @foreach ($photos as $path)
                        @php $url = \App\Support\ProtectedFile::url($path); @endphp
                        @if ($url)<a href="{{ $url }}" target="_blank" rel="noopener" data-evidence><img src="{{ $url }}" alt="Buyer's photo"></a>@endif
                    @endforeach
                </div>
            @else
                <p class="mt-2 text-xs" style="color:#6b90aa;">No photos.</p>
            @endif
        </div>

        {{-- 3. The seller's rejection --}}
        <div class="card p-5" data-dispute-seller>
            <h2 class="dc-h">2. The seller rejected it <small class="text-xs font-normal" style="color:#6b90aa;">{{ $when($return->resolved_at && $return->escalated_at && $return->resolved_at->lte($return->escalated_at) ? $return->resolved_at : null) }}</small></h2>
            <div class="dc-quote" style="background:#FFF7ED;">{{ $return->seller_note ?: 'No reason given.' }}</div>
        </div>

        {{-- 4. The buyer's dispute --}}
        <div class="card p-5" data-dispute-buyer>
            <h2 class="dc-h">3. The buyer escalated it to ALVY <small class="text-xs font-normal" style="color:#6b90aa;">{{ $when($return->escalated_at) }}</small></h2>
            <div class="dc-quote" style="background:#FEF2F2;">{{ $return->dispute_note ?: 'The buyer did not add a note.' }}</div>
        </div>

        {{-- 5. The decision --}}
        <div class="card p-5" data-dispute-decision>
            <h2 class="dc-h">4. Your decision</h2>
            @if ($open)
                <form method="POST" action="{{ route('admin.disputes.decide', $return) }}" class="flex flex-col gap-3" data-decide-form>
                    @csrf
                    <label class="dc-choice"><input type="radio" name="decision" value="refund" @checked(old('decision') === 'refund') required>
                        <span><strong>Refund the buyer {{ $peso($return->refund_amount) }}</strong><br><span style="color:#6b90aa;">Overrides the seller's rejection.
                            {{ $order->isPaidOnline() ? 'The money goes back through the payment gateway.' : 'Recorded as refunded to the buyer (' . $order->paymentMethodLabel() . ').' }}
                            A rider collects the item from the buyer if it hasn't been collected.</span></span></label>
                    <label class="dc-choice"><input type="radio" name="decision" value="close" @checked(old('decision') === 'close')>
                        <span><strong>Close without a refund</strong><br><span style="color:#6b90aa;">Agrees with the seller. The buyer keeps the item and the order is completed.</span></span></label>
                    <div>
                        <label for="reason" class="text-xs font-semibold" style="color:#6b90aa;">Reason (the buyer and the seller see this)</label>
                        <textarea id="reason" name="reason" rows="3" minlength="10" maxlength="500" required class="input mt-1 w-full text-sm"
                                  placeholder="e.g. The photos show the wrong colour was sent, so the buyer is refunded.">{{ old('reason') }}</textarea>
                        @error('reason')<p class="mt-1 text-xs" style="color:#DC2626;">{{ $message }}</p>@enderror
                        @error('decision')<p class="mt-1 text-xs" style="color:#DC2626;">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn-gold self-start" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">Submit decision</button>
                </form>
            @else
                <div class="dc-kv"><span>Decision</span><span><strong>{{ $return->admin_decision === 'refund' ? 'Refunded ' . $peso($return->refund_amount) : 'Closed without a refund' }}</strong></span></div>
                <div class="dc-kv"><span>By</span><span>{{ $decider?->name ?? 'ALVY' }} · {{ $when($return->admin_decided_at) }}</span></div>
                @if ($return->admin_note)<div class="dc-quote" style="background:#F8FAFC;">{{ $return->admin_note }}</div>@endif
            @endif
        </div>
    </div>

    <div class="dc-side">
        <div class="card p-5">
            <h2 class="dc-h">People</h2>
            <div class="dc-kv"><span>Buyer</span><span>{{ $return->user?->name }}</span></div>
            <div class="dc-kv"><span>Buyer email</span><span>{{ $return->user?->email }}</span></div>
            <div class="dc-kv"><span>Seller</span><span>{{ $sellerName }}</span></div>
            <div class="dc-kv" style="border:0;"><span>Seller email</span><span>{{ $return->seller?->email }}</span></div>
            <p class="mt-2 text-xs" style="color:#6b90aa;">Need more details? Message them from <a href="{{ \App\Support\Portal::enabled() ? route('admin.messages.inbox') : route('messages.inbox') }}" style="color:#fa4e1c;font-weight:600;">Messages</a>.</p>
        </div>

        <div class="card p-5" data-dispute-pickup>
            <h2 class="dc-h">Item pickup</h2>
            @if ($shipment)
                <div class="dc-kv"><span>Status</span><span>{{ $shipment->statusLabel() }}</span></div>
                @if ($shipment->picked_up_at)<div class="dc-kv"><span>Collected from the buyer</span><span>{{ $when($shipment->picked_up_at) }}</span></div>@endif
                @if ($shipment->cancel_reason)<div class="dc-kv"><span>Note</span><span>{{ $shipment->cancel_reason }}</span></div>@endif
            @else
                <p class="text-sm" style="color:#6b90aa;">No pickup was opened for this return.</p>
            @endif
        </div>

        <div class="card p-5">
            <h2 class="dc-h">Order timeline</h2>
            <ul class="dc-steps">
                @foreach ($order->events->sortBy('created_at') as $e)
                    <li class="{{ in_array($e->status, [\App\Models\Order::RETURN_REQUESTED, \App\Models\Order::IN_DISPUTE, \App\Models\Order::REFUNDED], true) ? 'is-key' : '' }}">
                        <strong>{{ $e->status }}</strong>@if ($e->note) · {{ $e->note }}@endif
                        <small>{{ $when($e->created_at) }}</small>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
</x-admin-layout>
