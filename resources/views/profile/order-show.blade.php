<x-layout :title="'Order ' . $order->number() . ' — ALVY'">
@php
    $c = $order->statusColor();
    $return = $order->returnRequests->first();
    $eventLabel = fn ($status) => match ($status) {
        'Pending'          => 'Order placed',
        'Processing'       => 'Seller is preparing the order',
        'Shipped'          => 'Picked up — on the way',
        'Delivered'        => 'Delivered',
        'Completed'        => 'Order completed',
        'Cancelled'        => 'Order cancelled',
        'Return Requested' => 'Return requested',
        'Refunded'         => 'Return approved — refunded',
        'In Dispute'       => 'Escalated — ALVY is reviewing the return',
        default            => $status,
    };
@endphp
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    <a href="{{ route('profile.orders') }}" class="text-sm font-semibold" style="color:#fa4e1c;">&larr; My Purchases</a>

    <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="font-display text-2xl font-extrabold" style="color:#222;">Order {{ $order->number() }}</h1>
        <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background:{{ $c['bg'] }};color:{{ $c['text'] }};">{{ $order->buyerStatusLabel() }}</span>
    </div>
    <p class="text-sm pf-muted">{{ $order->seller?->storeName() ?? 'ALVY' }} · placed {{ $order->created_at->format('M j, Y g:i A') }}</p>

    @if (session('success'))<div class="pf-alert pf-alert-success mt-4">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="pf-alert pf-alert-error mt-4">{{ session('error') }}</div>@endif
    @if ($errors->any())
        <div class="pf-alert pf-alert-error mt-4"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- To Pay --}}
    @if ($order->status === \App\Models\Order::AWAITING_PAYMENT)
        <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border p-4" style="{{ $order->payment_status === 'Failed' ? 'background:#FEF2F2;border-color:#FCA5A5;' : 'background:#FFFBEB;border-color:#FCD34D;' }}">
            <div class="min-w-0 flex-1 text-sm">
                @if ($order->payment_status === 'Failed')
                    <p class="font-bold" style="color:#B91C1C;">Payment failed</p>
                    <p style="color:#7F1D1D;">Nothing was charged. You can pay again{{ $order->pay_by ? ' until ' . $order->pay_by->format('M j, g:i A') : '' }}.</p>
                @else
                    <p class="font-bold" style="color:#92400E;">Waiting for your payment ({{ ucfirst(strtolower($order->payment_method)) }})</p>
                    <p style="color:#78350F;">Pay {{ $order->pay_by ? 'by ' . $order->pay_by->format('M j, g:i A') : 'soon' }}, or the order is cancelled automatically.</p>
                @endif
            </div>
            @if ($order->canBePaid())
                <form action="{{ route('orders.pay', $order->id) }}" method="POST">@csrf
                    <button class="pf-btn px-6">{{ $order->payment_status === 'Failed' ? 'Pay again' : 'Pay now' }}</button>
                </form>
            @endif
        </div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_320px]">
        <div class="space-y-5">

            {{-- Items (+ rating) --}}
            <section class="pf-card overflow-hidden" id="rate">
                <h2 class="border-b px-5 py-3 text-sm font-bold" style="border-color:#F0E4DC;color:#222;">Items</h2>
                @foreach ($order->items as $item)
                    <div class="border-b px-5 py-4 last:border-b-0" style="border-color:#F7F3F0;">
                        <div class="flex items-center gap-3">
                            <img src="{{ $item->product?->image ? asset('storage/' . $item->product->image) : 'https://placehold.co/64x64/f5f5f5/999?text=P' }}" class="h-16 w-16 shrink-0 rounded-md border object-cover" style="border-color:#F2EEEA;" alt="">
                            <div class="min-w-0 flex-1">
                                @if ($item->product && ! $item->product->trashed())
                                    <a href="{{ route('products.show', $item->product->slug) }}" class="text-sm font-medium hover:underline" style="color:#222;">{{ $item->product->title }}</a>
                                @else
                                    <p class="text-sm font-medium" style="color:#222;">{{ $item->product->title ?? 'Removed product' }}</p>
                                @endif
                                <p class="text-xs pf-muted">@if ($item->variation_name){{ $item->variation_name }} · @endif₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}@if ($item->refunded_at) · <span style="color:#059669;font-weight:600;">Refunded ₱{{ number_format($item->refund_amount, 2) }}</span>@endif</p>
                            </div>
                            <p class="text-sm font-semibold" style="color:#222;">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                        </div>

                        {{-- Rating --}}
                        @if ($item->review)
                            @php $r = $item->review; @endphp
                            <div class="mt-3 rounded-lg p-3 text-sm" style="background:#FDFAF8;">
                                <p><span style="color:#F59E0B;">{{ str_repeat('★', $r->rating) }}<span style="color:#E7E5E4;">{{ str_repeat('★', 5 - $r->rating) }}</span></span>
                                   <span class="text-xs pf-muted">· your review{{ $r->edit_count ? ' (edited)' : '' }}</span></p>
                                @if ($r->comment)<p class="mt-1" style="color:#44403C;">{{ $r->comment }}</p>@endif
                                @if ($r->photo_path)<img src="{{ asset('storage/' . $r->photo_path) }}" class="mt-2 h-20 w-20 rounded-md object-cover" alt="Review photo">@endif
                                @if ($r->canBeEdited())
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-semibold" style="color:#fa4e1c;">Edit review (one time only)</summary>
                                        @include('profile._review-form', ['action' => route('reviews.update', $r), 'method' => 'PATCH', 'review' => $r])
                                    </details>
                                @endif
                            </div>
                        @elseif ($order->reviewWindowOpen())
                            <details class="mt-3" open>
                                <summary class="cursor-pointer text-sm font-semibold" style="color:#fa4e1c;">Rate this item</summary>
                                @include('profile._review-form', ['action' => route('reviews.store', $item), 'method' => 'POST', 'review' => null])
                            </details>
                        @endif
                    </div>
                @endforeach
            </section>

            {{-- Rider delivery: the OTP to give the rider, then the proof of delivery --}}
            @php
                $riderDelivery = \App\Models\ParcelDelivery::whereHas('parcel', fn ($q) => $q->where('order_id', $order->id))
                    ->whereIn('status', ['out_for_delivery', 'delivered'])->with('rider')->latest('id')->first();
            @endphp
            @if ($riderDelivery && $riderDelivery->status === 'out_for_delivery')
                <section class="pf-card p-5" data-delivery-otp>
                    <h2 class="text-sm font-bold" style="color:#222;">Out for delivery</h2>
                    <p class="mt-1 text-sm" style="color:#57534E;">Rider {{ $riderDelivery->rider->full_name ?? '' }} is on the way. Give this code to the rider when you receive your parcel:</p>
                    <p class="mt-2 font-display text-3xl font-extrabold tracking-[.3em]" style="color:#fa4e1c;">{{ $riderDelivery->otp_code }}</p>
                    @if ((float) $riderDelivery->cod_amount > 0)<p class="mt-1 text-xs" style="color:#8a7f78;">Cash on delivery: ₱{{ number_format($riderDelivery->cod_amount, 2) }}</p>@endif
                </section>
            @elseif ($riderDelivery && $riderDelivery->status === 'delivered' && $riderDelivery->pod_path)
                <section class="pf-card p-5" data-proof-of-delivery>
                    <h2 class="text-sm font-bold" style="color:#222;">Delivered {{ $riderDelivery->delivered_at?->format('M j, Y g:i A') }}</h2>
                    <img src="{{ asset('storage/' . $riderDelivery->pod_path) }}" alt="Proof of delivery" class="mt-2 h-32 rounded-md object-cover">
                </section>
            @endif

            {{-- Timeline --}}
            <section class="pf-card p-5">
                <h2 class="mb-3 text-sm font-bold" style="color:#222;">Order timeline</h2>
                <ol class="relative ml-2 space-y-4 border-l pl-5" style="border-color:#F0E4DC;">
                    @foreach ($order->events as $event)
                        <li class="relative">
                            <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full" style="background:{{ $loop->last ? '#fa4e1c' : '#D6CFC9' }};"></span>
                            <p class="text-sm font-semibold" style="color:#222;">{{ $eventLabel($event->status) }}</p>
                            <p class="text-xs pf-muted">{{ $event->created_at->format('M j, Y · g:i A') }}@if ($event->note) — {{ $event->note }}@endif</p>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Return / refund --}}
            @if ($return)
                <section class="pf-card p-5">
                    <h2 class="text-sm font-bold" style="color:#222;">Return / refund</h2>
                    <p class="mt-1 text-sm" style="color:#57534E;"><strong>{{ $return->statusLabel() }}</strong> · {{ $return->reason }}</p>
                    @if ($return->seller_note)<p class="mt-1 text-sm"><strong>Seller:</strong> {{ $return->seller_note }}</p>@endif
                    @if ($return->status === 'rejected' && $order->status === \App\Models\Order::DELIVERED)
                        <form method="POST" action="{{ route('orders.escalate', $order->id) }}" class="mt-3 space-y-2 rounded-lg border p-3" style="border-color:#FECACA;">
                            @csrf
                            <p class="text-xs" style="color:#57534E;">Don't agree with the seller? Ask ALVY to review it.</p>
                            <input name="dispute_note" maxlength="500" class="w-full rounded-md border px-3 py-1.5 text-sm" style="border-color:#E7E5E4;" placeholder="Why you disagree (optional)">
                            <button class="rounded-md px-4 py-1.5 text-sm font-semibold text-white" style="background:#B91C1C;">Escalate</button>
                        </form>
                    @endif
                    <div class="mt-2 flex flex-wrap gap-2">@foreach ($return->photos() as $ph)<img src="{{ asset('storage/' . $ph) }}" class="h-20 rounded-md object-cover" alt="Evidence">@endforeach</div>
                </section>
            @endif

            @if ($order->returnWindowOpen() && ! $order->returnRequests->where('status', 'requested')->count())
                <section class="pf-card p-5">
                    <details>
                        <summary class="cursor-pointer text-sm font-bold" style="color:#222;">Return / refund
                            <span class="font-normal pf-muted">— until {{ $order->returnDeadline()->format('M j, Y g:i A') }}</span></summary>
                        <form action="{{ route('orders.return', $order->id) }}" method="POST" enctype="multipart/form-data" class="mt-3 space-y-3 text-sm">
                            @csrf
                            <div>
                                <label class="pf-label">Reason</label>
                                <select name="reason" class="pf-input" required>
                                    <option value="">Choose a reason</option>
                                    @foreach (\App\Models\ReturnRequest::REASONS as $reason)<option @selected(old('reason') === $reason)>{{ $reason }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <span class="pf-label">Items to return</span>
                                @foreach ($order->items as $ri)
                                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="items[]" value="{{ $ri->id }}" checked>
                                        {{ $ri->product->title ?? 'Item' }}@if ($ri->variation_name) ({{ $ri->variation_name }})@endif × {{ $ri->quantity }}</label>
                                @endforeach
                                <p class="text-xs pf-muted">Untick items you are keeping. Each item's refund is its price minus its share of the vouchers.</p>
                            </div>
                            <div>
                                <label class="pf-label">Describe the problem <span class="pf-muted">— 10 to 500 characters</span></label>
                                <textarea name="details" rows="2" minlength="10" maxlength="500" required class="pf-input">{{ old('details') }}</textarea>
                                @error('details')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="pf-label">Photo evidence (1 to 5 photos, JPG or PNG, up to 5 MB each)</label>
                                <input type="file" name="evidence_photos[]" multiple accept="image/jpeg,image/png" required class="text-sm">
                                @error('evidence')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
                                @error('evidence_photos')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
                            </div>
                            <button class="pf-btn px-5">Submit request</button>
                        </form>
                    </details>
                </section>
            @endif
        </div>

        {{-- Summary + actions --}}
        <aside class="space-y-5">
            <section class="pf-card p-5 text-sm">
                <h2 class="mb-2 font-bold" style="color:#222;">Payment</h2>
                <dl class="space-y-1" style="color:#57534E;">
                    <div class="flex justify-between"><dt>Merchandise</dt><dd>₱{{ number_format($order->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt>Shipping{{ $order->shipping_weight_kg ? ' (' . (float) $order->shipping_weight_kg . ' kg)' : '' }}</dt><dd>₱{{ number_format($order->shipping_fee, 2) }}</dd></div>
                    @if ($order->promo_discount > 0)<div class="flex justify-between" style="color:#059669;"><dt>Bundle deals &amp; promos</dt><dd>−₱{{ number_format($order->promo_discount, 2) }}</dd></div>@endif
                    @if ($order->seller_discount > 0)<div class="flex justify-between" style="color:#059669;"><dt>Store voucher</dt><dd>−₱{{ number_format($order->seller_discount, 2) }}</dd></div>@endif
                    @if ($order->platform_discount > 0)<div class="flex justify-between" style="color:#059669;"><dt>ALVY voucher {{ $order->platformVoucher?->code }}</dt><dd>−₱{{ number_format($order->platform_discount, 2) }}</dd></div>@endif
                    @if ($order->shipping_discount > 0)<div class="flex justify-between" style="color:#059669;"><dt>Shipping voucher {{ $order->shippingVoucher?->code }}</dt><dd>−₱{{ number_format($order->shipping_discount, 2) }}</dd></div>@endif
                    <div class="flex justify-between border-t pt-2 font-semibold" style="border-color:#F0E4DC;color:#222;"><dt>Total</dt><dd style="color:#fa4e1c;">₱{{ number_format($order->total_price, 2) }}</dd></div>
                    <div class="flex justify-between text-xs"><dt>Method</dt><dd>{{ $order->payment_method }} · {{ $order->payment_status }}</dd></div>
                    @if ((float) $order->refunded_amount > 0)
                        <div class="flex justify-between text-xs" style="color:#047857;"><dt>Refunded</dt><dd>₱{{ number_format($order->refunded_amount, 2) }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="pf-card p-5 text-sm">
                <h2 class="mb-2 font-bold" style="color:#222;">Deliver to</h2>
                <p style="color:#222;">{{ $order->full_name }} · {{ $order->phone }}</p>
                <p class="pf-muted">{{ $order->fullAddress() }}</p>
                @if ($order->buyer_note)<p class="mt-2 text-xs"><strong>Your note:</strong> {{ $order->buyer_note }}</p>@endif
            </section>

            @if ($order->canConfirmReceived())
                <form action="{{ route('orders.confirm-delivery', $order->id) }}" method="POST">@csrf
                    <button class="pf-btn w-full" onclick="return confirm('Confirm you received this order? It will be marked completed.')">Order Received</button>
                    <p class="mt-1 text-center text-xs pf-muted">Completes by itself on {{ $order->returnDeadline()?->format('M j, Y') }}.</p>
                </form>
            @endif

            @if ($order->isCancellableByBuyer())
                <section class="pf-card p-5">
                    <form action="{{ route('orders.cancel', $order->id) }}" method="POST" class="space-y-2" onsubmit="return confirm('Cancel this order?')">
                        @csrf
                        <label class="pf-label" for="cancel-reason">Cancel order</label>
                        <select id="cancel-reason" name="cancellation_reason" class="pf-input">
                            <option value="">Choose a reason</option>
                            @foreach (\App\Http\Controllers\OrderController::CANCEL_REASONS as $reason)<option>{{ $reason }}</option>@endforeach
                        </select>
                        <button class="pf-btn-ghost w-full rounded-lg border py-2 text-sm font-semibold" style="border-color:#FCA5A5;color:#B91C1C;">Cancel order</button>
                    </form>
                </section>
            @elseif ($order->status === \App\Models\Order::PROCESSING && $order->isReadyForPickup())
                <p class="rounded-lg px-3 py-2 text-xs" style="background:#F5F2EF;color:#57534E;">The seller has booked the pickup, so this order can no longer be cancelled. Message the seller if you need help.</p>
            @endif

            @if ($order->seller_id)
                <form action="{{ route('messages.new') }}" method="POST">@csrf
                    <input type="hidden" name="receiver_id" value="{{ $order->seller_id }}">
                    <button class="pf-btn-ghost w-full rounded-lg border py-2 text-sm font-semibold" style="border-color:#E7E5E4;">Message the seller</button>
                </form>
            @endif
        </aside>
    </div>
</div>
</div>
</x-layout>
