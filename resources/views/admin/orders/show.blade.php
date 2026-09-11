<x-admin-layout title="Order Details" active="orders">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

<div class="mb-6">
    <a href="{{ route('admin.orders.index') }}"
       class="text-sm transition" style="color:#fa4e1c;"
       onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
        ← Back to all orders
    </a>
</div>

<div class="grid gap-6 lg:grid-cols-[1fr_320px]">

    {{-- Left: items + shipping --}}
    <div class="space-y-6">

        {{-- Items --}}
        <div class="card overflow-hidden">
            <div class="px-6 py-4" style="border-bottom:1px solid #dce8f0;">
                <h2 class="font-display text-base font-bold" style="color:#222222;">Items Ordered</h2>
            </div>
            <div class="p-6 space-y-4">
                @foreach ($order->items as $item)
                    <div class="flex items-center gap-4">
                        <img src="{{ $item->book && $item->book->image ? asset('storage/'.$item->book->image) : 'https://placehold.co/48x64/FF6300/FFFFFF?text=B' }}"
                             class="h-14 w-10 rounded object-cover flex-shrink-0"
                             alt="{{ $item->book->title ?? '' }}">
                        <div class="flex flex-1 items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold" style="color:#222222;">{{ $item->book->title ?? '—' }}</p>
                                <p class="text-xs mt-0.5" style="color:#6b90aa;">
                                    by {{ $item->book->author ?? '—' }}
                                    @if ($item->book && $item->book->seller)
                                        · Seller: <span style="color:#fa4e1c;">{{ $item->book->seller->name }}</span>
                                    @endif
                                </p>
                                <p class="text-xs mt-1" style="color:#555555;">
                                    Qty: {{ $item->quantity }} × ${{ number_format($item->price, 2) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-sm" style="color:#222222;">${{ number_format($item->price * $item->quantity, 2) }}</p>
                                <p class="text-xs mt-0.5" style="color:#C97D6E;">Commission: ${{ number_format($item->commission_amount, 2) }}</p>
                                <p class="text-xs" style="color:#059669;">Seller earns: ${{ number_format($item->seller_earning, 2) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="px-6 py-4 space-y-2 text-sm" style="background:#e8f0f6;border-top:1px solid #dce8f0;">
                <div class="flex justify-between" style="color:#555555;">
                    <span>Subtotal</span><span>${{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between" style="color:#555555;">
                    <span>Shipping</span>
                    <span style="color:{{ $order->shipping_fee == 0 ? '#059669' : '#222222' }};">
                        {{ $order->shipping_fee == 0 ? 'Free' : '$'.number_format($order->shipping_fee,2) }}
                    </span>
                </div>
                <div class="flex justify-between border-t pt-2 font-display text-base font-bold"
                     style="border-color:#dce8f0;color:#222222;">
                    <span>Total</span><span>${{ number_format($order->total_price, 2) }}</span>
                </div>
                <div class="flex justify-between text-xs pt-1" style="color:#6b90aa;">
                    <span>Total Commission</span><span style="color:#C97D6E;">${{ number_format($totalCommission, 2) }}</span>
                </div>
                <div class="flex justify-between text-xs" style="color:#6b90aa;">
                    <span>Total Seller Earnings</span><span style="color:#059669;">${{ number_format($totalEarnings, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Shipping info --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Shipping Information</h2>
            <dl class="text-sm divide-y" style="--tw-divide-color:#dce8f0;">
                @foreach ([
                    'Full Name'  => $order->full_name,
                    'Phone'      => $order->phone,
                    'Email'      => $order->email,
                    'Address'    => $order->address_line,
                    'City'       => $order->city,
                    'Province'   => $order->province,
                    'ZIP Code'   => $order->zip_code,
                ] as $label => $val)
                    <div class="flex justify-between py-2.5" style="border-color:#dce8f0;">
                        <dt style="color:#6b90aa;">{{ $label }}</dt>
                        <dd class="font-medium text-right" style="color:#222222;">{{ $val ?? '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    {{-- Right: status + buyer --}}
    <div class="space-y-5">

        {{-- Order status card --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Order Status</h2>

            @php
                $sc = $order->statusColor();
                $ps = $order->payment_status === 'Paid'
                    ? ['bg'=>'#ECFDF5','text'=>'#059669']
                    : ['bg'=>'#e8f0f6','text'=>'#fa4e1c'];
            @endphp

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs" style="color:#6b90aa;">Order</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                          style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                        {{ $order->status }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs" style="color:#6b90aa;">Payment</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                          style="background:{{ $ps['bg'] }};color:{{ $ps['text'] }};">
                        {{ $order->payment_status }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs" style="color:#6b90aa;">Method</span>
                    <span class="text-xs font-semibold" style="color:#222222;">{{ $order->payment_method }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs" style="color:#6b90aa;">Placed</span>
                    <span class="text-xs" style="color:#222222;">{{ $order->created_at->format('M d, Y H:i') }}</span>
                </div>
            </div>

            {{-- Update status form --}}
            <form action="{{ route('admin.orders.update', $order->id) }}" method="POST" class="mt-5 space-y-3">
                @csrf @method('PATCH')
                <label class="text-xs font-semibold block" style="color:#6b90aa;">Update Status</label>
                <select name="status" class="input text-sm">
                    @foreach (['Pending','Processing','Shipped','Delivered','Cancelled'] as $s)
                        <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-gold w-full !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Save Status</button>
            </form>
        </div>

        {{-- Buyer card --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-3" style="color:#222222;">Buyer</h2>
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full font-bold"
                      style="background:#fa4e1c;color:#FFFFFF;">
                    {{ strtoupper(substr($order->user->name ?? 'G', 0, 1)) }}
                </span>
                <div>
                    <p class="font-semibold text-sm" style="color:#222222;">{{ $order->user->name ?? 'Deleted User' }}</p>
                    <p class="text-xs" style="color:#6b90aa;">{{ $order->user->email ?? '' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

</x-admin-layout>