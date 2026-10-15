<x-seller-layout title="Order Detail" active="orders">
@include('partials.panel.plain')
@php
    $delivery  = $order->delivery;
    $tagClass  = strtolower($order->status);
    $gross     = $order->sellerItems->sum(fn ($i) => $i->price * $i->quantity);
    $fees      = $order->sellerItems->sum('commission_amount');
    $earned    = $order->sellerItems->sum('seller_earning');
    $steps     = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Completed'];
    $openReturn = $order->returnRequests->firstWhere('status', 'requested');
    $lastReturn = $order->returnRequests->first();
    $currIdx   = array_search($order->status, $steps);
    $sellerStep = $order->sellerStep();
    $promo     = $order->sellerItems->sum('promo_discount');
    $storeOff  = $order->sellerItems->sum('seller_discount');
    $storeVoucher = $order->voucherRedemptions()->with('voucher')->get()->first(fn ($r) => $r->voucher && $r->voucher->scope === 'seller');
    $sellerSteps = ['new' => 'New', 'accepted' => 'Accepted', 'packed' => 'Packed', 'ready' => 'Ready for Pickup'];
@endphp

<div class="mx-auto max-w-5xl">

    {{-- Header --}}
    <div class="mb-5">
        <a href="{{ route('seller.orders.index') }}" class="text-sm hover:underline pl-muted">&larr; Orders</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h1 class="font-display text-2xl font-bold" style="color:#222;">Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</h1>
            <span class="pl-tag {{ $tagClass }}">{{ $order->status }}</span>
        </div>
        <p class="mt-0.5 text-sm pl-muted">Placed {{ $order->created_at->format('M j, Y · g:i A') }}</p>
    </div>


    @if ($order->status === 'Completed')
        <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#F0FDF4;border-color:#BBF7D0;color:#166534;">
            This order is complete. Payment: {{ $order->payment_status }}. Your earnings for it are now available.
        </div>
    @elseif ($order->status === 'Delivered')
        <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#F0FDF4;border-color:#BBF7D0;color:#166534;">
            Delivered{{ $order->delivered_at ? ' on ' . $order->delivered_at->format('M j, Y') : '' }}. The order completes when {{ $order->full_name }} confirms it,
            or by itself {{ \App\Models\Order::RETURN_DAYS }} days after delivery.
        </div>
    @endif

    {{-- Return / refund request --}}
    @if ($openReturn || $lastReturn)
        @php $rr = $openReturn ?? $lastReturn; @endphp
        <section class="pl-card mb-5" style="border-color:#BFDBFE;">
            <div class="pl-head"><h2>Return / refund request</h2><span class="pl-tag pending">{{ $rr->status === 'disputed' ? 'In Dispute' : ucfirst($rr->status) }}</span></div>
            <div class="pl-body space-y-2 text-sm">
                <p><strong>Reason:</strong> {{ $rr->reason }}</p>
                @if ($rr->details)<p class="pl-muted">{{ $rr->details }}</p>@endif
                <div class="flex flex-wrap gap-2">
                    @foreach ($rr->photos() as $ph)
                        <a href="{{ asset('storage/' . $ph) }}" target="_blank" class="inline-block"><img src="{{ asset('storage/' . $ph) }}" alt="Evidence photo" class="h-28 rounded-md border object-cover" style="border-color:#ECE7E3;"></a>
                    @endforeach
                </div>
                @if ($rr->status === 'requested')<p class="text-xs" style="color:#B45309;">Answer by {{ $rr->created_at->addDays(\App\Models\ReturnRequest::RESPOND_DAYS)->format('M j, Y g:i A') }}, or it is approved automatically.</p>@endif
                <p class="text-xs pl-muted">Requested {{ $rr->created_at->format('M j, Y · g:i A') }} · refund ₱{{ number_format($rr->refund_amount, 2) }}{{ $rr->isPartial() ? ' · ' . count($rr->item_ids) . ' item(s) only' : ' · whole order' }}</p>
                @if ($rr->seller_note)<p class="text-xs"><strong>Your note:</strong> {{ $rr->seller_note }}</p>@endif
                @if ($rr->status === 'disputed')
                    <p class="rounded-md px-3 py-2 text-xs" style="background:#FEF2F2;color:#B91C1C;">The buyer escalated this to ALVY on {{ $rr->escalated_at?->format('M j, Y') }}. ALVY will decide; your decision can no longer be changed.@if ($rr->dispute_note) Buyer: “{{ $rr->dispute_note }}”@endif</p>
                @endif
                @if ($openReturn)
                    <form action="{{ route('seller.orders.return', $order->id) }}" method="POST" class="space-y-2 border-t pt-3" style="border-color:#F2EEEA;">
                        @csrf
                        <textarea name="seller_note" rows="2" maxlength="500" class="input text-sm" placeholder="Note to the buyer (required when rejecting)">{{ old('seller_note') }}</textarea>
                        @error('seller_note')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
                        <div class="flex gap-2">
                            <button name="decision" value="approve" class="pl-btn" onclick="return confirm('Approve the return and refund the buyer?')">Approve refund</button>
                            <button name="decision" value="reject" class="pl-btn-ghost rounded-md border px-3 py-1.5 text-sm" style="border-color:#FCA5A5;color:#B91C1C;">Reject</button>
                        </div>
                    </form>
                @endif
            </div>
        </section>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_19rem]">

        {{-- ═══ Left ═══ --}}
        <div class="space-y-5">

            {{-- Progress --}}
            <section class="pl-card pl-body">
                @if ($order->status === 'Cancelled')
                    <p class="text-sm" style="color:#57534E;">This order was cancelled{{ $order->cancellation_reason ? ': ' . $order->cancellation_reason : '.' }}</p>
                @else
                    <ol class="flex items-center">
                        @foreach ($steps as $i => $step)
                            @php $done = $currIdx !== false && $i <= $currIdx; @endphp
                            @if ($i > 0)
                                <li aria-hidden="true" class="mx-2 h-px flex-1" style="background:{{ $done ? '#fa4e1c' : '#E7E5E4' }};"></li>
                            @endif
                            <li class="flex items-center gap-2 text-sm" style="color:{{ $done ? '#222' : '#a39890' }};">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-semibold"
                                      style="{{ $done ? 'background:#fa4e1c;color:#fff;' : 'border:1px solid #D6D3D1;color:#a39890;' }}">{{ $i + 1 }}</span>
                                <span class="hidden sm:inline">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Items --}}
            <section class="pl-card">
                <div class="pl-head"><h2>Items</h2></div>
                @foreach ($order->sellerItems as $item)
                    <div class="flex items-center gap-3 border-b px-5 py-3 last:border-b-0" style="border-color:#F2EEEA;">
                        @if ($item->product?->image)
                            <img src="{{ asset('storage/' . $item->product->image) }}" alt="" class="h-14 w-14 shrink-0 rounded-md border object-cover" style="border-color:#ECE7E3;">
                        @else
                            <span class="h-14 w-14 shrink-0 rounded-md" style="background:#F5F2EF;"></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium" style="color:#222;">{{ $item->product->title ?? 'Removed product' }}</p>
                            <p class="text-xs pl-muted">@if ($item->variation_name){{ $item->variation_name }} · @endif₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}@if ($item->refunded_at) · <span style="color:#B91C1C;font-weight:600;">Refunded ₱{{ number_format($item->refund_amount, 2) }}</span>@endif</p>
                        </div>
                        <p class="text-sm font-medium" style="color:#222;">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                    </div>
                @endforeach
                <dl class="pl-dl border-t px-5 py-3" style="border-color:#F2EEEA;background:#FCFBFA;border-radius:0 0 .75rem .75rem;">
                    <div><dt>Subtotal</dt><dd>₱{{ number_format($gross, 2) }}</dd></div>
                    @if ($promo > 0)<div><dt>Bundle deals &amp; promos (you pay)</dt><dd>−₱{{ number_format($promo, 2) }}</dd></div>@endif
                    @if ($storeOff > 0)<div><dt>Store voucher{{ $storeVoucher ? ' ' . $storeVoucher->voucher->code : '' }} (you pay)</dt><dd>−₱{{ number_format($storeOff, 2) }}</dd></div>@endif
                    @if ($order->platform_discount > 0)<div><dt>ALVY voucher (ALVY pays)</dt><dd class="pl-muted">₱{{ number_format($order->platform_discount, 2) }}</dd></div>@endif
                    <div><dt>Shipping fee (buyer pays)</dt><dd>₱{{ number_format($order->shipping_fee, 2) }}@if ($order->shipping_discount > 0) <span class="pl-muted">(−₱{{ number_format($order->shipping_discount, 2) }} shipping voucher)</span>@endif</dd></div>
                    <div><dt>Platform fee</dt><dd>−₱{{ number_format($fees, 2) }}</dd></div>
                    <div class="font-semibold"><dt style="color:#222;">You earn</dt><dd style="color:#047857;">₱{{ number_format($earned, 2) }}</dd></div>
                </dl>
            </section>

            {{-- Ship to + payment --}}
            <section class="pl-card">
                <div class="pl-head">
                    <h2>Ship to</h2>
                    <a href="{{ route('messages.inbox', ['open' => 'order:' . $order->id]) }}" class="pl-link">Message buyer</a>
                </div>
                <dl class="pl-dl pl-body !py-3">
                    <div><dt>Name</dt><dd>{{ $order->full_name }}</dd></div>
                    <div><dt>Phone</dt><dd>{{ $order->phone }}</dd></div>
                    <div><dt>Email</dt><dd class="break-all">{{ $order->email }}</dd></div>
                    <div><dt>Address</dt><dd class="max-w-[24rem]">{{ $order->fullAddress() }}</dd></div>
                    @if ($order->buyer_note)
                        <div><dt>Note from buyer</dt><dd class="max-w-[24rem] font-medium" style="color:#9A3412;">“{{ $order->buyer_note }}”</dd></div>
                    @endif
                    <div><dt>Payment</dt><dd>{{ $order->payment_method }} · {{ $order->payment_status }}</dd></div>
                    @if ($order->payment_method === 'COD' && $order->payment_status !== 'Paid')
                        <div><dt>Collect on delivery</dt><dd class="font-semibold">₱{{ number_format($order->total_price, 2) }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>

        {{-- ═══ Right ═══ --}}
        <div class="space-y-5">

            {{-- Seller steps: New → Accepted → Packed → Ready for Pickup --}}
            @if (in_array($order->status, ['Pending', 'Processing'], true))
                <section class="pl-card pl-body space-y-3">
                    <ol class="flex flex-wrap gap-1 text-[11px] font-semibold">
                        @foreach ($sellerSteps as $key => $label)
                            @php $reached = array_search($key, array_keys($sellerSteps)) <= array_search($sellerStep, array_keys($sellerSteps)); @endphp
                            <li class="rounded px-2 py-0.5" style="{{ $reached ? 'background:#FFF1EB;color:#C2410C;' : 'background:#F5F5F4;color:#a39890;' }}">{{ $label }}</li>
                        @endforeach
                    </ol>
                    @if ($sellerStep === 'new')
                        <form method="POST" action="{{ route('seller.orders.accept', $order->id) }}">@csrf<button class="pl-btn w-full">Accept order</button></form>
                    @elseif ($sellerStep === 'accepted')
                        <form method="POST" action="{{ route('seller.orders.pack', $order->id) }}">@csrf<button class="pl-btn w-full">Mark as packed</button></form>
                    @elseif ($sellerStep === 'packed')
                        <p class="text-xs pl-muted">Packed. Book the pickup below to mark it Ready for Pickup.</p>
                    @endif
                    @if (in_array($sellerStep, ['packed', 'ready'], true))
                        <a href="{{ route('seller.orders.waybill', $order->id) }}" target="_blank" class="pl-btn-ghost block rounded-md border px-3 py-1.5 text-center text-sm font-semibold" style="border-color:#E7E5E4;">Print waybill</a>
                    @endif

                    @if ($order->isCancellableBySeller())
                        <details class="border-t pt-3" style="border-color:#F2EEEA;" @if ($errors->has('reason')) open @endif>
                            <summary class="cursor-pointer text-sm font-semibold" style="color:#B91C1C;">Cancel order</summary>
                            <form method="POST" action="{{ route('seller.orders.cancel', $order->id) }}" class="mt-2 space-y-2">
                                @csrf
                                <label class="pl-label" for="cancel-reason">Reason *</label>
                                <select id="cancel-reason" name="reason" class="input text-sm">
                                    <option value="">Choose a reason</option>
                                    @foreach (\App\Models\Order::SELLER_CANCEL_REASONS as $r)<option value="{{ $r }}" @selected(old('reason') === $r)>{{ $r }}</option>@endforeach
                                </select>
                                @error('reason')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
                                <textarea name="details" rows="2" maxlength="300" class="input text-sm" placeholder="Message to the buyer (optional)">{{ old('details') }}</textarea>
                                <button class="w-full rounded-md border px-3 py-1.5 text-sm font-semibold" style="border-color:#FCA5A5;color:#B91C1C;" onclick="return confirm('Cancel this order? The buyer will be told why.')">Cancel order</button>
                                <p class="text-[11px] pl-muted">Stock goes back, vouchers are returned to the buyer, and an online payment is refunded in full.</p>
                            </form>
                        </details>
                    @endif
                </section>
            @endif

            {{-- A parcel coming back to the seller (Return to Sender) --}}
            @if ($parcel && $parcel->status === 'return_to_sender')
                <section class="pl-card pl-body space-y-2">
                    <p class="text-sm font-semibold" style="color:#B91C1C;">Return to Sender</p>
                    <p class="text-xs pl-muted">{{ $parcel->failure_reason ?: 'The parcel could not be delivered.' }} Confirm when it is back with you; its stock is then restored.</p>
                    <form method="POST" action="{{ route('seller.orders.returned-received', $order->id) }}">@csrf
                        <button class="pl-btn w-full">Confirm returned parcel received</button></form>
                </section>
            @endif

            {{-- Sorting-center pickup --}}
            @if ($parcel)
                <section class="pl-card">
                    <div class="pl-head"><h2>Sorting center pickup</h2></div>
                    <dl class="pl-dl pl-body !py-3">
                        <div><dt>Tracking no.</dt><dd class="font-medium">{{ $parcel->tracking_number }}</dd></div>
                        <div><dt>Sorting center</dt><dd>{{ $parcel->currentSortingCenter->name ?? 'Awaiting assignment' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $parcel->statusLabel() }}</dd></div>
                    </dl>
                    @if ($parcel->failure_reason && in_array($parcel->status, ['pickup_rejected', 'failed', 'returned']))
                        <p class="mx-5 mb-3 rounded-md px-3 py-2 text-xs" style="background:#FEF2F2;color:#B91C1C;">Reason: {{ $parcel->failure_reason }}</p>
                    @endif
                    @if ($parcel->status === 'pickup_rejected')
                        <p class="px-5 pb-4 text-xs pl-muted">Schedule a new pickup below to send another request.</p>
                    @endif
                </section>
            @endif

            {{-- Courier handover --}}
            @if (! in_array($order->status, \App\Models\Order::LOGISTICS_DONE))

                {{-- One route per order: a sorting-center parcel replaces the courier cards below. --}}
                @if (in_array($sellerStep, ['packed', 'ready'], true) && ((! $delivery && ! $parcel) || ($delivery && $delivery->status === 'available' && ! $delivery->pickup_scheduled_at) || ($parcel && $parcel->status === 'pickup_rejected')))
                    <section class="pl-card">
                        <div class="pl-head"><h2>Ready for pickup — choose the logistics company</h2></div>
                        <form action="{{ route('seller.orders.handover', $order->id) }}" method="POST" class="pl-body space-y-3">
                            @csrf
                            <div>
                                <label for="sorting_center_id" class="pl-label">Logistics company (sorting center)</label>
                                <select id="sorting_center_id" name="sorting_center_id" class="input text-sm" required>
                                    <option value="">Choose…</option>
                                    @foreach ($sortingCenters as $sc)
                                        <option value="{{ $sc->id }}" @selected((string) old('sorting_center_id') === (string) $sc->id)>
                                            {{ $sc->display_name }}{{ $sc->assigned_municipality ? ' · ' . $sc->assigned_municipality : '' }}{{ $sc->covers_seller ? ' · covers your area' : '' }}{{ $sc->rider_count ? ' · ' . $sc->rider_count . ' ' . \Illuminate\Support\Str::plural('rider', $sc->rider_count) : ' · no riders yet' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sorting_center_id')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
                                <p class="mt-1 text-xs pl-muted">One of their riders picks the parcel up from you and brings it to their hub.</p>
                            </div>
                            <div>
                                <label for="pickup_scheduled_at" class="pl-label">Pickup date and time</label>
                                <input type="datetime-local" id="pickup_scheduled_at" name="pickup_scheduled_at" class="input text-sm" required min="{{ now()->format('Y-m-d\TH:i') }}">
                            </div>
                            <div>
                                <label for="notes" class="pl-label">Notes <span style="color:#a39890;font-weight:400;">— optional</span></label>
                                <textarea id="notes" name="notes" rows="2" class="input text-sm" placeholder="Instructions for the rider"></textarea>
                            </div>
                            <button type="submit" class="pl-btn w-full">Ready for pickup</button>
                        </form>
                    </section>

                @elseif ($delivery?->status === 'available')
                    <section class="pl-card">
                        <div class="pl-head"><h2>Waiting for a courier</h2></div>
                        <dl class="pl-dl pl-body !py-3">
                            <div><dt>Pickup</dt><dd>{{ $delivery->pickup_scheduled_at?->format('M j, Y · g:i A') }}</dd></div>
                            @if ($delivery->notes)<div><dt>Notes</dt><dd>{{ $delivery->notes }}</dd></div>@endif
                        </dl>
                        <p class="px-5 pb-4 text-xs pl-muted">The job is open to couriers. You'll be notified when one accepts it.</p>
                    </section>

                @elseif ($delivery?->status === 'accepted')
                    <section class="pl-card">
                        <div class="pl-head"><h2>Courier pickup</h2></div>
                        <dl class="pl-dl pl-body !py-3">
                            <div><dt>Courier</dt><dd class="font-medium">{{ $delivery->courier_name }}</dd></div>
                            @if ($delivery->tracking_number)<div><dt>Tracking no.</dt><dd>{{ $delivery->tracking_number }}</dd></div>@endif
                            <div><dt>Pickup</dt><dd>{{ $delivery->pickup_scheduled_at?->format('M j, Y · g:i A') }}</dd></div>
                            @if ($delivery->notes)<div><dt>Notes</dt><dd>{{ $delivery->notes }}</dd></div>@endif
                        </dl>
                        <form action="{{ route('seller.orders.handed-over', $order->id) }}" method="POST" class="px-5 pb-4">
                            @csrf
                            <p class="mb-2 text-xs pl-muted">Once you've given the parcel to the courier, mark it as handed over.</p>
                            <button type="submit" class="pl-btn w-full"
                                    onclick="return confirm('Confirm that you handed this order to the courier?')">Mark as handed over</button>
                        </form>
                    </section>

                @elseif (in_array($delivery?->status, ['picked_up', 'in_transit']))
                    <section class="pl-card">
                        <div class="pl-head"><h2>In transit</h2></div>
                        <dl class="pl-dl pl-body !py-3">
                            <div><dt>Courier</dt><dd class="font-medium">{{ $delivery->courier_name }}</dd></div>
                            @if ($delivery->tracking_number)<div><dt>Tracking no.</dt><dd>{{ $delivery->tracking_number }}</dd></div>@endif
                            <div><dt>Handed over</dt><dd>{{ $delivery->handed_over_at?->format('M j, Y · g:i A') }}</dd></div>
                        </dl>
                        <p class="px-5 pb-4 text-xs pl-muted">The parcel is on its way to the buyer.</p>
                    </section>
                @endif
            @endif

            {{-- Delivered --}}
            @if ($order->status === 'Delivered' && $delivery)
                <section class="pl-card">
                    <div class="pl-head"><h2>Delivery</h2></div>
                    <dl class="pl-dl pl-body !py-3">
                        <div><dt>Courier</dt><dd class="font-medium">{{ $delivery->courier_name }}</dd></div>
                        @if ($delivery->tracking_number)<div><dt>Tracking no.</dt><dd>{{ $delivery->tracking_number }}</dd></div>@endif
                        <div><dt>Delivered</dt><dd>{{ $delivery->delivered_at?->format('M j, Y · g:i A') ?? '—' }}</dd></div>
                    </dl>
                </section>
            @endif
        </div>
    </div>
</div>
</x-seller-layout>
