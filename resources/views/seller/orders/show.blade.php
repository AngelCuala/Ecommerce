<x-seller-layout title="Order Detail" active="orders">
<div>

    <div class="mb-5 flex items-center gap-4">
        <a href="{{ route('seller.orders.index') }}"
           class="text-sm font-semibold transition" style="color:#fa4e1c;"
           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
            ← My Orders
        </a>
        <span style="color:#E0E0E0;">|</span>
        <h1 class="font-display text-xl font-bold" style="color:#222222;">
            Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
        </h1>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#d93d0e;">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Delivery confirmed notification --}}
    @if ($order->status === 'Delivered')
        <div class="mb-5 flex items-start gap-3 rounded-xl border p-4"
             style="background:#F0FDF4;border-color:#BBF7D0;">
            <span class="text-2xl">✅</span>
            <div>
                <p class="font-bold text-sm" style="color:#059669;">Buyer has confirmed delivery!</p>
                <p class="text-xs mt-0.5" style="color:#6B7280;">
                    {{ $order->full_name }} confirmed receipt of this order.
                    Payment status: <strong style="color:#059669;">{{ $order->payment_status }}</strong>.
                    Your earnings have been credited.
                </p>
            </div>
        </div>
    @endif

    @php
        $statusMap = [
            'Pending'    => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'],
            'Processing' => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'],
            'Shipped'    => ['bg'=>'#EFF6FF','text'=>'#2563EB'],
            'Delivered'  => ['bg'=>'#F0FDF4','text'=>'#059669'],
            'Cancelled'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
        ];
        $sc = $statusMap[$order->status] ?? ['bg'=>'#F5F5F5','text'=>'#555'];
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1fr_300px]">

        {{-- Left: items + buyer --}}
        <div class="space-y-5">

            {{-- Items --}}
            <div class="card overflow-hidden">
                <div class="px-5 py-4" style="background:#e8f0f6;border-bottom:1px solid #cfdce8;">
                    <h2 class="font-display text-base font-bold" style="color:#222222;">Items in This Order</h2>
                </div>
                <div class="divide-y" style="--tw-divide-color:#fff1ee;">
                    @foreach ($order->sellerItems as $item)
                        <div class="flex items-center gap-4 px-5 py-4" style="border-color:#fff1ee;">
                            <img src="{{ $item->book && $item->book->image ? asset('storage/'.$item->book->image) : 'https://placehold.co/48x64/FF6300/fff?text=P' }}"
                                 class="h-16 w-12 rounded-lg object-cover flex-shrink-0 shadow-sm" alt="">
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-sm" style="color:#222222;">{{ $item->book->title ?? '—' }}</p>
                                <p class="text-xs mt-0.5" style="color:#6b90aa;">Qty: {{ $item->quantity }} × ₱{{ number_format($item->price, 2) }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="font-bold" style="color:#fa4e1c;">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                                <p class="text-xs" style="color:#059669;">You earn: ₱{{ number_format($item->seller_earning, 2) }}</p>
                                <p class="text-[10px]" style="color:#B0B0B0;">-₱{{ number_format($item->commission_amount, 2) }} comm.</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Buyer info --}}
            <div class="card p-6">
                <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">📍 Ship To</h2>
                <div class="grid gap-y-2 text-sm">
                    <div class="flex gap-3"><span style="color:#6b90aa;width:80px;flex-shrink:0;">Name</span><strong style="color:#222;">{{ $order->full_name }}</strong></div>
                    <div class="flex gap-3"><span style="color:#6b90aa;width:80px;flex-shrink:0;">Phone</span><span style="color:#555;">{{ $order->phone }}</span></div>
                    <div class="flex gap-3"><span style="color:#6b90aa;width:80px;flex-shrink:0;">Email</span><span style="color:#555;">{{ $order->email }}</span></div>
                    <div class="flex gap-3"><span style="color:#6b90aa;width:80px;flex-shrink:0;">Address</span><span style="color:#555;">{{ $order->fullAddress() }}</span></div>
                </div>

                {{-- Payment --}}
                <div class="mt-5 pt-5 border-t" style="border-color:#fff1ee;">
                    <h3 class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#6b90aa;">Payment</h3>
                    <div class="flex flex-wrap gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-bold"
                              style="background:#F5F5F5;color:#555;">{{ $order->payment_method }}</span>
                        <span class="rounded-full px-3 py-1 text-xs font-bold"
                              style="background:{{ $order->payment_status==='Paid'?'#ECFDF5':'#FFFBEB' }};
                                     color:{{ $order->payment_status==='Paid'?'#059669':'#D97706' }};">
                            {{ $order->payment_status }}
                        </span>
                        @if ($order->payment_method === 'COD')
                            <span class="text-xs" style="color:#D97706;">Collect ₱{{ number_format($order->total_price, 2) }} on delivery</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: status + actions --}}
        <div class="space-y-5">

            {{-- Current status --}}
            <div class="card p-5">
                <h2 class="font-display text-base font-bold mb-3" style="color:#222222;">Order Status</h2>

                {{-- Progress tracker --}}
                @php
                    $steps   = ['Pending','Processing','Shipped','Delivered'];
                    $currIdx = array_search($order->status, $steps);
                @endphp
                @if ($order->status !== 'Cancelled')
                    <div class="flex items-center mb-4">
                        @foreach ($steps as $i => $step)
                            @php $done = $currIdx !== false && $i <= $currIdx; @endphp
                            @if ($i > 0)
                                <div class="h-0.5 flex-1 mx-0.5" style="background:{{ $done ? '#fa4e1c' : '#E0E0E0' }};"></div>
                            @endif
                            <div class="flex flex-col items-center gap-1 flex-shrink-0">
                                <div class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold"
                                     style="background:{{ $done ? '#fa4e1c' : '#E0E0E0' }};color:{{ $done ? '#fff' : '#999' }};">
                                    {{ $done && $i < $currIdx ? '✓' : ($i+1) }}
                                </div>
                                <span class="text-[9px]" style="color:{{ $done ? '#fa4e1c' : '#6b90aa' }};">{{ $step }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mb-4 rounded-lg p-3 text-sm text-center" style="background:#FEF2F2;color:#DC2626;">✕ Cancelled</div>
                @endif

                <span class="inline-block rounded-full px-3 py-1 text-sm font-bold"
                      style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                    {{ $order->status }}
                </span>
                <p class="text-xs mt-2" style="color:#6b90aa;">Placed {{ $order->created_at->format('M d, Y H:i') }}</p>
            </div>

            {{-- Update status --}}
            @if (! in_array($order->status, ['Delivered','Cancelled']))
                <div class="card p-5">
                    <h3 class="text-sm font-bold mb-3" style="color:#222222;">Update Status</h3>
                    <form action="{{ route('seller.orders.update', $order->id) }}" method="POST" class="space-y-3">
                        @csrf @method('PATCH')
                        <select name="status" class="input text-sm" style="border-color:#cfdce8;">
                            @foreach (['Pending','Processing','Shipped','Delivered'] as $s)
                                <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                        <button type="submit"
                                class="w-full rounded-xl py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                                style="background:#002b4d;">Save Status</button>
                    </form>
                </div>
            @endif

            {{-- ══ COURIER HANDOVER SECTION ══ --}}
            @php $delivery = $order->delivery; @endphp

            @if (! in_array($order->status, ['Delivered','Cancelled']))

                {{-- If no delivery scheduled yet — show schedule form --}}
                @if (! $delivery || $delivery->status === 'available')
                    <div class="card p-5">
                        <h3 class="text-sm font-bold mb-1" style="color:#222222;">🚚 Schedule Courier Pickup</h3>
                        <p class="text-xs mb-4" style="color:#6b90aa;">Fill in the courier details and set a pickup date/time.</p>
                        <form action="{{ route('seller.orders.handover', $order->id) }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="text-xs font-semibold" style="color:#6b90aa;">Courier / Rider Name *</label>
                                <input type="text" name="courier_name" class="input mt-1 text-sm" required
                                       placeholder="e.g. LBC, J&T Express, or rider name">
                            </div>
                            <div>
                                <label class="text-xs font-semibold" style="color:#6b90aa;">Tracking Number</label>
                                <input type="text" name="tracking_number" class="input mt-1 text-sm"
                                       placeholder="e.g. JT12345678 (optional)">
                            </div>
                            <div>
                                <label class="text-xs font-semibold" style="color:#6b90aa;">Pickup Schedule *</label>
                                <input type="datetime-local" name="pickup_scheduled_at" class="input mt-1 text-sm" required
                                       min="{{ now()->format('Y-m-d\TH:i') }}">
                            </div>
                            <div>
                                <label class="text-xs font-semibold" style="color:#6b90aa;">Notes</label>
                                <textarea name="notes" rows="2" class="input mt-1 text-sm"
                                          placeholder="Any special instructions for the courier…"></textarea>
                            </div>
                            <button type="submit"
                                    class="w-full rounded-xl py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                                    style="background:#002b4d;">📅 Schedule Pickup</button>
                        </form>
                    </div>

                {{-- Courier assigned, not yet handed over --}}
                @elseif (in_array($delivery->status, ['accepted']))
                    <div class="card p-5 space-y-3">
                        <h3 class="text-sm font-bold" style="color:#222222;">🚚 Courier Pickup Scheduled</h3>
                        <div class="rounded-xl p-4 space-y-2 text-sm" style="background:#F0FDF4;border:1px solid #BBF7D0;">
                            <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Courier</span><strong style="color:#222;">{{ $delivery->courier_name }}</strong></div>
                            @if ($delivery->tracking_number)
                            <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Tracking No.</span><strong style="color:#222;">{{ $delivery->tracking_number }}</strong></div>
                            @endif
                            <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Pickup Time</span><strong style="color:#222;">{{ $delivery->pickup_scheduled_at?->format('M d, Y h:i A') }}</strong></div>
                            @if ($delivery->notes)
                            <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Notes</span><span style="color:#555;">{{ $delivery->notes }}</span></div>
                            @endif
                        </div>
                        <div class="rounded-lg p-3 text-xs" style="background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;">
                            ⏳ Waiting for courier pickup. Click below once you've handed the parcel to the courier.
                        </div>
                        <form action="{{ route('seller.orders.handed-over', $order->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="w-full rounded-xl py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                                    style="background:#059669;"
                                    onclick="return confirm('Confirm that you have handed this order to the courier?')">
                                ✅ Mark as Handed Over to Courier
                            </button>
                        </form>
                    </div>

                {{-- Handed over / in transit --}}
                @elseif (in_array($delivery->status, ['picked_up','in_transit']))
                    <div class="card p-5 space-y-3">
                        <h3 class="text-sm font-bold" style="color:#222222;">📦 Shipment In Transit</h3>
                        <div class="rounded-xl p-4 space-y-2 text-sm" style="background:#EFF6FF;border:1px solid #BFDBFE;">
                            <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Courier</span><strong style="color:#222;">{{ $delivery->courier_name }}</strong></div>
                            @if ($delivery->tracking_number)
                            <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Tracking No.</span>
                                <strong style="color:#2563EB;">{{ $delivery->tracking_number }}</strong>
                            </div>
                            @endif
                            <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Handed Over</span><span style="color:#555;">{{ $delivery->handed_over_at?->format('M d, Y h:i A') }}</span></div>
                        </div>
                        <div class="flex items-center gap-2 rounded-lg p-3 text-xs font-semibold"
                             style="background:#EFF6FF;color:#2563EB;">
                            🚚 Parcel is on its way to the buyer.
                        </div>
                    </div>
                @endif
            @endif

            {{-- Delivered state --}}
            @if ($order->status === 'Delivered' && $delivery)
                <div class="card p-5">
                    <h3 class="text-sm font-bold mb-3" style="color:#222222;">✅ Delivery Complete</h3>
                    <div class="rounded-xl p-4 space-y-2 text-sm" style="background:#F0FDF4;border:1px solid #BBF7D0;">
                        <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Courier</span><strong style="color:#222;">{{ $delivery->courier_name }}</strong></div>
                        @if ($delivery->tracking_number)
                        <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Tracking No.</span><strong style="color:#059669;">{{ $delivery->tracking_number }}</strong></div>
                        @endif
                        <div class="flex gap-2"><span style="color:#6B7280;width:120px;flex-shrink:0;">Delivered At</span><span style="color:#555;">{{ $delivery->delivered_at?->format('M d, Y h:i A') ?? '—' }}</span></div>
                    </div>
                </div>
            @endif

            {{-- Earnings summary --}}
            <div class="card p-5">
                <h3 class="text-sm font-bold mb-3" style="color:#222222;">Earnings Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between" style="color:#777;">
                        <span>Gross</span>
                        <span>₱{{ number_format($order->sellerItems->sum(fn($i) => $i->price * $i->quantity), 2) }}</span>
                    </div>
                    <div class="flex justify-between" style="color:#fa4e1c;">
                        <span>Commission</span>
                        <span>-₱{{ number_format($order->sellerItems->sum('commission_amount'), 2) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-bold text-base" style="border-color:#fff1ee;color:#059669;">
                        <span>Your Earnings</span>
                        <span>₱{{ number_format($order->sellerItems->sum('seller_earning'), 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Chat link --}}
            <a href="{{ route('messages.show', $order->id) }}"
               class="card flex items-center gap-3 p-4 transition hover:-translate-y-0.5">
                <span class="text-2xl">💬</span>
                <div>
                    <p class="font-bold text-sm" style="color:#222222;">Chat with Buyer</p>
                    <p class="text-xs" style="color:#6b90aa;">{{ $order->full_name }}</p>
                </div>
                <span class="ml-auto text-sm" style="color:#fa4e1c;">→</span>
            </a>
        </div>
    </div>
</div>
</x-seller-layout>
