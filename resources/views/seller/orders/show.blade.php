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
            <div class="pl-head"><h2>Return / refund request</h2><span class="pl-tag pending">{{ ucfirst($rr->status) }}</span></div>
            <div class="pl-body space-y-2 text-sm">
                <p><strong>Reason:</strong> {{ $rr->reason }}</p>
                @if ($rr->details)<p class="pl-muted">{{ $rr->details }}</p>@endif
                <a href="{{ asset('storage/' . $rr->evidence_path) }}" target="_blank" class="inline-block">
                    <img src="{{ asset('storage/' . $rr->evidence_path) }}" alt="Evidence photo" class="h-28 rounded-md border object-cover" style="border-color:#ECE7E3;">
                </a>
                <p class="text-xs pl-muted">Requested {{ $rr->created_at->format('M j, Y · g:i A') }} · refund ₱{{ number_format($rr->refund_amount, 2) }}</p>
                @if ($rr->seller_note)<p class="text-xs"><strong>Your note:</strong> {{ $rr->seller_note }}</p>@endif
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
                            <p class="text-xs pl-muted">@if ($item->variation_name){{ $item->variation_name }} · @endif₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}</p>
                        </div>
                        <p class="text-sm font-medium" style="color:#222;">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                    </div>
                @endforeach
                <dl class="pl-dl border-t px-5 py-3" style="border-color:#F2EEEA;background:#FCFBFA;border-radius:0 0 .75rem .75rem;">
                    <div><dt>Subtotal</dt><dd>₱{{ number_format($gross, 2) }}</dd></div>
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

            {{-- Update status --}}
            @if (! in_array($order->status, \App\Models\Order::LOGISTICS_DONE))
                <section class="pl-card pl-body">
                    <form action="{{ route('seller.orders.update', $order->id) }}" method="POST">
                        @csrf @method('PATCH')
                        <label for="status" class="pl-label">Order status</label>
                        <div class="flex gap-2">
                            <select id="status" name="status" class="input flex-1 !py-1.5 text-sm">
                                @foreach ($steps as $s)
                                    <option value="{{ $s }}" @selected($order->status === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="pl-btn !px-3">Save</button>
                        </div>
                    </form>
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
                @if ((! $delivery && ! $parcel) || ($delivery && $delivery->status === 'available' && ! $delivery->pickup_scheduled_at) || ($parcel && $parcel->status === 'pickup_rejected'))
                    <section class="pl-card">
                        <div class="pl-head"><h2>Schedule courier pickup</h2></div>
                        <form action="{{ route('seller.orders.handover', $order->id) }}" method="POST" class="pl-body space-y-3">
                            @csrf
                            <div>
                                <label for="courier_name" class="pl-label">Courier or rider</label>
                                <input type="text" id="courier_name" name="courier_name" class="input text-sm" required placeholder="e.g. J&T Express or rider's name">
                            </div>
                            <div>
                                <label for="tracking_number" class="pl-label">Tracking number <span style="color:#a39890;font-weight:400;">— optional</span></label>
                                <input type="text" id="tracking_number" name="tracking_number" class="input text-sm" placeholder="e.g. JT12345678">
                            </div>
                            <div>
                                <label for="pickup_scheduled_at" class="pl-label">Pickup date and time</label>
                                <input type="datetime-local" id="pickup_scheduled_at" name="pickup_scheduled_at" class="input text-sm" required min="{{ now()->format('Y-m-d\TH:i') }}">
                            </div>
                            <div>
                                <label for="notes" class="pl-label">Notes <span style="color:#a39890;font-weight:400;">— optional</span></label>
                                <textarea id="notes" name="notes" rows="2" class="input text-sm" placeholder="Instructions for the courier"></textarea>
                            </div>
                            <button type="submit" class="pl-btn w-full">Schedule pickup</button>
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
