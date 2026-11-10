<x-layout :title="'Order ' . $order->number() . ' — ALVY'">
@include('profile._styles')
@php
    $riderDelivery = \App\Models\ParcelDelivery::whereHas('parcel', fn ($q) => $q->where('order_id', $order->id))
        ->whereIn('status', ['out_for_delivery', 'delivered'])->with('rider')->latest('id')->first();
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

    @if ($errors->any())
        <div class="pf-alert pf-alert-error mt-4"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {{-- To Pay --}}
    @if ($order->status === \App\Models\Order::AWAITING_PAYMENT && $order->isGcashReceipt() && $order->payment_status === 'Rejected')
        {{-- Rejected: why, how to fix it, and the form to upload the right receipt. --}}
        @php
            $gc = $order->payment_check ?? [];
            $gAccount = \App\Support\AlvyGcash::account();
            $gGroup = \App\Models\Order::where('checkout_group', $order->checkout_group)->where('status', \App\Models\Order::AWAITING_PAYMENT)->get();
            $gAmount = (float) ($order->payment_expected ?? $gGroup->sum('total_price'));
            $gFixBy = ! empty($gc['fix_by']) ? \Illuminate\Support\Carbon::parse($gc['fix_by'])->tz(config('app.business_timezone', 'Asia/Manila')) : null;
        @endphp
        <div class="mt-4 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:#FCA5A5;" data-gcash-rejected>
            <p class="font-bold" style="color:#B91C1C;">Your GCash payment was rejected</p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide" style="color:#991B1B;">Why</p>
                    <ul class="mt-1 list-inside list-disc" style="color:#7F1D1D;">@foreach ($gc['rejection']['problems'] ?? [] as $p)<li>{{ $p['reason'] }}</li>@endforeach</ul>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide" style="color:#991B1B;">How to fix it</p>
                    <ul class="mt-1 list-inside list-disc" style="color:#7F1D1D;">@foreach (array_unique(array_column($gc['rejection']['problems'] ?? [], 'fix')) as $fix)<li>{{ $fix }}</li>@endforeach</ul>
                </div>
            </div>
            <p class="mt-2 text-xs" style="color:#B91C1C;">
                Upload the right receipt{{ $gFixBy ? ' by ' . $gFixBy->format('M j, g:i A') : '' }}, or the order is cancelled.
                Try {{ $gc['tries'] ?? 1 }} of {{ \App\Services\GcashChecks::MAX_TRIES }}.@if ($gGroup->count() > 1) This payment covers orders {{ $gGroup->map->number()->implode(', ') }}.@endif
            </p>

            <form method="POST" action="{{ route('orders.gcash-resubmit', $order->id) }}" enctype="multipart/form-data" class="mt-3 grid gap-4 rounded-lg border bg-white p-4 sm:grid-cols-[150px_1fr]" style="border-color:#FECACA;">
                @csrf
                @if ($gAccount)
                    <a href="{{ $gAccount['qr_url'] }}" target="_blank" rel="noopener" title="Open the QR bigger">
                        <img src="{{ $gAccount['qr_url'] }}" alt="ALVY GCash QR" class="w-full rounded-lg border bg-white object-contain" style="border-color:#BFDBFE;">
                    </a>
                @endif
                <div class="min-w-0 space-y-3">
                    <div style="color:#1E3A8A;">
                        <p class="text-xs">Send exactly this amount to {{ $gAccount['name'] ?? 'ALVY' }}{{ ! empty($gAccount['number']) ? ' · ' . $gAccount['number'] : '' }}</p>
                        <p class="font-display text-2xl font-extrabold" style="color:#0B2A5B;">₱{{ number_format($gAmount, 2) }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold" style="color:#1E3A8A;" for="fix-proof">Screenshot of the GCash receipt *</label>
                        <input type="file" id="fix-proof" name="payment_proof" accept="image/jpeg,image/png,image/webp" required class="block w-full rounded-lg border bg-white px-3 py-2 text-xs" style="border-color:#BFDBFE;">
                        @error('payment_proof')<p class="mt-1 text-xs font-medium" style="color:#B91C1C;">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold" style="color:#1E3A8A;" for="fix-ref">GCash Ref No. *</label>
                        <input type="text" id="fix-ref" name="payment_reference" value="{{ old('payment_reference') }}" required inputmode="numeric" maxlength="13" pattern="\d{13}"
                               title="The 13 numbers of the GCash Ref No." oninput="this.value = this.value.replace(/\D/g, '').slice(0, 13)"
                               placeholder="13 numbers, e.g. 1012345678901" autocomplete="off" class="block w-full rounded-lg border bg-white px-3 py-2 font-mono text-sm" style="border-color:#BFDBFE;">
                        @error('payment_reference')<p class="mt-1 text-xs font-medium" style="color:#B91C1C;">{{ $message }}</p>@enderror
                    </div>
                    <button class="pf-btn px-6">Send the new receipt</button>
                </div>
            </form>
        </div>
    @elseif ($order->status === \App\Models\Order::AWAITING_PAYMENT && $order->isGcashReceipt())
        <div class="mt-4 rounded-xl border p-4 text-sm" style="background:#F5F9FF;border-color:#BFDBFE;" data-gcash-checking>
            <p class="font-bold" style="color:#1E3A8A;">ALVY is checking your GCash payment</p>
            <p style="color:#3B5B8C;">We match your receipt{{ $order->payment_reference ? ' (Ref No. ' . $order->payment_reference . ')' : '' }} with the money in ALVY's GCash. Once it's confirmed, the seller prepares your order. If something doesn't match, you're told why and how to fix it.</p>
        </div>
    @elseif ($order->status === \App\Models\Order::AWAITING_PAYMENT)
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

            {{-- Rider delivery: on the way, then the proof of delivery --}}
            @if ($riderDelivery && $riderDelivery->status === 'out_for_delivery')
                @php $rider = $riderDelivery->rider; $cod = (float) $riderDelivery->cod_amount; @endphp
                <section class="pf-card overflow-hidden" data-out-for-delivery>
                    <div class="flex flex-wrap items-center gap-3 border-b px-5 py-4" style="border-color:#F0E4DC;background:linear-gradient(90deg,#FFF1EA 0,#FFFFFF 85%);">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white" style="background:#fa4e1c;" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7M7.5 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM17.5 19a1.5 1.5 0 100-3 1.5 1.5 0 000 3z"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold" style="color:#222;">Out for delivery</h2>
                            <p class="text-sm" style="color:#57534E;">
                                <strong style="color:#222;">{{ $rider->full_name ?? 'Your rider' }}</strong> is bringing your parcel
                                @if ($rider && ($rider->vehicle_type || $rider->plate_number))
                                    <span class="pf-muted">· {{ trim(($rider->vehicle_type ?? '') . ' ' . ($rider->plate_number ?? '')) }}</span>
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" style="background:#FFF7ED;color:#C2410C;">
                            <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60" style="background:#fa4e1c;"></span><span class="relative inline-flex h-2 w-2 rounded-full" style="background:#fa4e1c;"></span></span>
                            On the way
                        </span>
                    </div>

                    <div class="px-5 py-5">
                        <ul class="space-y-2 text-sm" style="color:#57534E;">
                            @foreach (['Please be ready to receive your parcel.', 'The rider takes a photo when handing it over as proof of delivery.', 'Check the parcel before the rider leaves.'] as $tip)
                                <li class="flex gap-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0" style="color:#059669;" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span>{{ $tip }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    @if ($cod > 0)
                        <div class="flex flex-wrap items-center justify-between gap-2 border-t px-5 py-3" style="background:#FFFBEB;border-color:#FDE68A;" data-cod-due>
                            <p class="flex items-center gap-2 text-sm" style="color:#92400E;">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>
                                Cash on delivery · please prepare the exact amount
                            </p>
                            <p class="font-display text-lg font-extrabold" style="color:#92400E;">₱{{ number_format($cod, 2) }}</p>
                        </div>
                    @endif
                </section>
            @elseif ($riderDelivery && $riderDelivery->status === 'delivered' && $riderDelivery->pod_path)
                @php $podUrl = \App\Support\Portal::route('shipments.pod', $order); @endphp
                <section class="pf-card overflow-hidden" data-proof-of-delivery>
                    <div class="flex flex-wrap items-center gap-3 border-b px-5 py-4" style="border-color:#D1FAE5;background:linear-gradient(90deg,#ECFDF5 0,#FFFFFF 85%);">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white" style="background:#059669;box-shadow:0 0 0 5px #D1FAE5;" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold" style="color:#222;">Delivered</h2>
                            <p class="text-sm pf-muted">Your parcel was handed over {{ $riderDelivery->delivered_at?->diffForHumans() }}.</p>
                        </div>
                        @if ($order->status === \App\Models\Order::COMPLETED)
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background:#ECFDF5;color:#047857;">Order completed</span>
                        @endif
                    </div>

                    <div class="grid gap-5 px-5 py-5 sm:grid-cols-[1fr_auto] sm:items-start">
                        <div>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl p-3" style="background:#FAF7F5;">
                                    <dt class="text-[11px] font-semibold uppercase tracking-wide pf-muted">Delivered on</dt>
                                    <dd class="mt-1 text-sm font-semibold" style="color:#222;">{{ $riderDelivery->delivered_at?->format('M j, Y') }}<span class="block text-xs font-normal pf-muted">{{ $riderDelivery->delivered_at?->format('g:i A') }}</span></dd>
                                </div>
                                <div class="rounded-xl p-3" style="background:#FAF7F5;">
                                    <dt class="text-[11px] font-semibold uppercase tracking-wide pf-muted">Delivered by</dt>
                                    <dd class="mt-1 text-sm font-semibold" style="color:#222;">{{ $riderDelivery->rider->full_name ?? 'ALVY rider' }}<span class="block text-xs font-normal pf-muted">{{ $riderDelivery->rider?->sortingCenter ? \App\Support\SortingCenters::displayName($riderDelivery->rider->sortingCenter) : 'ALVY logistics' }}</span></dd>
                                </div>
                                @if ((float) $riderDelivery->cod_collected > 0)
                                    <div class="rounded-xl p-3 sm:col-span-2" style="background:#FAF7F5;">
                                        <dt class="text-[11px] font-semibold uppercase tracking-wide pf-muted">Cash paid to the rider</dt>
                                        <dd class="mt-1 text-sm font-semibold" style="color:#222;">₱{{ number_format($riderDelivery->cod_collected, 2) }}</dd>
                                    </div>
                                @endif
                            </dl>

                            @if ($order->canConfirmReceived())
                                <div class="mt-4 rounded-xl border p-3.5 text-sm" style="border-color:#FBD5C5;background:#FFF7F3;color:#57534E;">
                                    <p class="font-semibold" style="color:#222;">Got everything in good condition?</p>
                                    <p class="mt-0.5 text-xs">Tap <strong>Order Received</strong> to complete the order. Something wrong? You can request a return until {{ $order->returnDeadline()?->format('M j, Y') }}.</p>
                                </div>
                            @elseif ($order->reviewWindowOpen() && $order->items->contains(fn ($i) => ! $i->review))
                                <p class="mt-4 text-xs pf-muted">Enjoying it? <a href="#rate" class="font-semibold" style="color:#fa4e1c;">Rate your items</a> to help other buyers.</p>
                            @endif
                        </div>

                        <figure class="sm:w-48">
                            <a href="{{ $podUrl }}" target="_blank" rel="noopener" class="group relative block overflow-hidden rounded-xl border" style="border-color:#F0E4DC;" title="Open the full photo">
                                <img src="{{ $podUrl }}" alt="Proof of delivery" class="h-48 w-full object-cover transition duration-200 group-hover:scale-[1.03]">
                                <span class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 py-1.5 text-[11px] font-semibold text-white" style="background:linear-gradient(0deg,rgba(0,0,0,.55),transparent);">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                                    View full photo
                                </span>
                            </a>
                            <figcaption class="mt-1.5 text-center text-[11px] pf-muted">Proof of delivery · taken by the rider</figcaption>
                        </figure>
                    </div>
                </section>
            @endif

            {{-- Items (+ rating) --}}
            <section class="pf-card overflow-hidden" id="rate">
                <h2 class="border-b px-5 py-3.5 text-sm font-bold" style="border-color:#F0E4DC;color:#222;">Items <span class="font-normal pf-muted">· {{ $order->items->sum('quantity') }}</span></h2>
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

            {{-- Shipment tracking (verified logistics events) --}}
            @include('shipment._tracking', ['track' => \App\Support\ShipmentProgress::for($order)])

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
                    @if ($rs = $return->shipment)
                        <p class="mt-1 text-sm" style="color:#1E40AF;" data-return-shipment="{{ $rs->status }}">Return pickup: <strong>{{ $rs->statusLabel() }}</strong>@if ($rs->status === 'pickup_assigned' && $rs->pickupRider) · rider {{ $rs->pickupRider->full_name }}@endif</p>
                    @endif
                    @if ($return->seller_note)<p class="mt-1 text-sm"><strong>Seller:</strong> {{ $return->seller_note }}</p>@endif
                    @if ($return->status === 'rejected' && $order->status === \App\Models\Order::DELIVERED)
                        <form method="POST" action="{{ route('orders.escalate', $order->id) }}" class="mt-3 space-y-2 rounded-lg border p-3" style="border-color:#FECACA;">
                            @csrf
                            <p class="text-xs" style="color:#57534E;">Don't agree with the seller? Ask ALVY to review it.</p>
                            <input name="dispute_note" maxlength="500" class="w-full rounded-md border px-3 py-1.5 text-sm" style="border-color:#E7E5E4;" placeholder="Why you disagree (optional)">
                            <button class="rounded-md px-4 py-1.5 text-sm font-semibold text-white" style="background:#B91C1C;">Escalate</button>
                        </form>
                    @endif
                    <div class="mt-2 flex flex-wrap gap-2">@foreach ($return->photos() as $ph)<img src="{{ \App\Support\ProtectedFile::url($ph) }}" class="h-20 rounded-md object-cover" alt="Evidence">@endforeach</div>
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
            @php
                $methodLabel = $order->paymentMethodLabel();
                $paid = $order->payment_status === 'Paid';
                $line = fn ($label, $amount, $green = false) => '<div class="flex justify-between gap-3' . ($green ? '" style="color:#059669;' : '') . '"><dt>' . e($label) . '</dt><dd class="tabular-nums">' . ($green ? '−' : '') . '₱' . number_format((float) $amount, 2) . '</dd></div>';
                // Street = address_line without the barangay / city / province / region / ZIP it repeats.
                $known = collect([$order->barangay, $order->city, $order->province, $order->region, $order->zip_code])->filter()->map(fn ($p) => mb_strtolower(trim($p)));
                $street = collect(explode(',', (string) $order->address_line))->map(fn ($p) => trim($p))->filter(fn ($p) => $p !== '' && ! $known->contains(mb_strtolower($p)))->implode(', ');
                $areaLine = collect([$order->barangay, $order->city, $order->province])->filter()->implode(', ');
                $regionLine = collect([$order->region, $order->zip_code])->filter()->implode(' · ');
            @endphp
            <section class="pf-card overflow-hidden text-sm">
                <div class="flex items-center justify-between gap-2 border-b px-5 py-3.5" style="border-color:#F0E4DC;">
                    <h2 class="flex items-center gap-2 font-bold" style="color:#222;">
                        <svg class="h-4 w-4" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l2 2 4-4M7 3h10a2 2 0 012 2v16l-3-2-2 2-2-2-2 2-2-2-3 2V5a2 2 0 012-2z"/></svg>
                        Payment
                    </h2>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold" style="{{ $paid ? 'background:#ECFDF5;color:#047857;' : ($order->payment_status === 'For verification' ? 'background:#FFFBEB;color:#B45309;' : 'background:#FFF7ED;color:#C2410C;') }}">{{ $order->payment_status }}</span>
                </div>
                <dl class="space-y-1.5 px-5 py-4" style="color:#57534E;">
                    {!! $line('Merchandise', $order->subtotal) !!}
                    {!! $line('Shipping' . ($order->shipping_weight_kg ? ' (' . (float) $order->shipping_weight_kg . ' kg)' : ''), $order->shipping_fee) !!}
                    @if ($order->promo_discount > 0){!! $line('Bundle deals & promos', $order->promo_discount, true) !!}@endif
                    @if ($order->seller_discount > 0){!! $line('Store voucher', $order->seller_discount, true) !!}@endif
                    @if ($order->platform_discount > 0){!! $line('ALVY voucher ' . $order->platformVoucher?->code, $order->platform_discount, true) !!}@endif
                    @if ($order->shipping_discount > 0){!! $line('Shipping voucher ' . $order->shippingVoucher?->code, $order->shipping_discount, true) !!}@endif
                </dl>
                <div class="border-t border-dashed px-5 py-3.5" style="border-color:#E6D9CF;background:#FFFBF9;">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-bold" style="color:#222;">Total</span>
                        <span class="font-display text-xl font-extrabold tabular-nums" style="color:#fa4e1c;">₱{{ number_format($order->total_price, 2) }}</span>
                    </div>
                    <div class="mt-1 flex justify-between text-xs pf-muted"><span>Method</span><span>{{ $methodLabel }} · {{ $order->payment_status }}</span></div>
                    @if ((float) $order->refunded_amount > 0)
                        <div class="mt-1 flex justify-between text-xs font-semibold" style="color:#047857;"><span>Refunded</span><span>₱{{ number_format($order->refunded_amount, 2) }}</span></div>
                    @endif
                    @if ($order->isGcashReceipt())
                        <div class="mt-3 flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-xs" style="background:#F5F9FF;color:#1E3A8A;" data-gcash-receipt>
                            <span>{{ $order->payment_reference ? 'Ref No. ' . $order->payment_reference : 'GCash receipt' }}
                                @if ($order->payment_status === 'For verification')<span class="block" style="color:#5B7BA8;">ALVY is checking your payment.</span>@elseif ($order->payment_status === 'Refund pending')<span class="block" style="color:#5B7BA8;">ALVY will send the money back to your GCash.</span>@endif
                            </span>
                            @if ($order->payment_proof_path)<a href="{{ \App\Support\Portal::route('orders.payment-proof', $order) }}" target="_blank" rel="noopener" class="shrink-0 font-semibold hover:underline" style="color:#007DFE;">View your GCash receipt</a>@endif
                        </div>
                    @endif
                </div>
            </section>

            <section class="pf-card p-5 text-sm">
                <h2 class="mb-3 flex items-center gap-2 font-bold" style="color:#222;">
                    <svg class="h-4 w-4" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Deliver to
                </h2>
                <p class="font-semibold" style="color:#222;">{{ $order->full_name }}</p>
                <p class="mt-0.5 flex items-center gap-1.5 text-xs pf-muted">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/></svg>
                    {{ $order->phone }}
                </p>
                <div class="mt-3 border-l-2 pl-3 leading-relaxed" style="border-color:#FBD5C5;" title="{{ $order->fullAddress() }}">
                    @if ($street !== '' && $areaLine !== '')
                        <p class="font-semibold" style="color:#222;">{{ $street }}</p>
                        <p class="pf-text">{{ $areaLine }}</p>
                        @if ($regionLine)<p class="text-xs pf-muted">{{ $regionLine }}</p>@endif
                    @else
                        <p class="pf-text">{{ $order->fullAddress() }}</p>
                    @endif
                </div>
                @if ($order->buyer_note)
                    <p class="mt-3 rounded-lg px-3 py-2 text-xs" style="background:#FAF7F5;color:#57534E;"><strong>Your note:</strong> {{ $order->buyer_note }}</p>
                @endif
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
                <form action="{{ \App\Support\Portal::route('messages.new') }}" method="POST" class="pf-card">@csrf
                    <input type="hidden" name="receiver_id" value="{{ $order->seller_id }}">
                    <button class="flex w-full items-center gap-3 rounded-[1.25rem] px-4 py-3.5 text-left transition hover:bg-[#FFF7F3]">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full font-display text-sm font-extrabold" style="color:#fff;background:#fa4e1c;" aria-hidden="true">{{ mb_strtoupper(mb_substr($order->seller?->storeName() ?? 'S', 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold" style="color:#222;">Message the seller</span>
                            <span class="block truncate text-xs pf-muted">{{ $order->seller?->storeName() ?? 'Seller' }}</span>
                        </span>
                        <svg class="h-5 w-5 shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5M21 12a8 8 0 01-11.6 7.1L4 20l1-4.4A8 8 0 1121 12z"/></svg>
                    </button>
                </form>
            @endif
        </aside>
    </div>
</div>
</div>
</x-layout>
