<x-seller-layout title="Orders" active="orders">
<div>

    <div class="mb-6">
        <span class="section-eyebrow" style="color:#fa4e1c;">Seller Panel</span>
        <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222222;">Orders for My Products</h1>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#d93d0e;">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Shopee-style status filter tabs (client-side, so it works with your existing controller) --}}
    <div class="mb-6 flex flex-wrap gap-2 border-b" style="border-color:#F0E4D8;" id="statusTabs">
        @php
            $tabs = ['All','Pending','Processing','Shipped','Delivered','Cancelled'];
        @endphp
        @foreach ($tabs as $tab)
            <button type="button"
                    class="status-tab -mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition"
                    data-status="{{ $tab }}"
                    style="border-color:{{ $tab === 'All' ? '#fa4e1c' : 'transparent' }}; color:{{ $tab === 'All' ? '#fa4e1c' : '#8A8A8A' }};">
                {{ $tab }}
            </button>
        @endforeach
    </div>

    @forelse ($orders as $order)
        @php
            // Shopee-flavored status colors
            $statusMap = [
                'Pending'    => ['bg' => '#e8f0f6', 'text' => '#fa4e1c'],
                'Processing' => ['bg' => '#e8f0f6', 'text' => '#fa4e1c'],
                'Shipped'    => ['bg' => '#EFF6FF', 'text' => '#2563EB'],
                'Delivered'  => ['bg' => '#F0FDF4', 'text' => '#059669'],
                'Cancelled'  => ['bg' => '#FEF2F2', 'text' => '#DC2626'],
            ];
            $sc = $statusMap[$order->status] ?? $order->statusColor();
        @endphp
        <div class="order-card card mb-5 overflow-hidden" data-status="{{ $order->status }}">
            {{-- Order header, Shopee "shop row" style --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4" style="background:#f0f6fa;border-bottom:1px solid #dce8f0;">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold text-white" style="background:#002b4d;">📦</span>
                    <div>
                        <p class="font-semibold text-sm" style="color:#222222;">
                            Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
                        </p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $order->created_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-xs font-bold"
                          style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                        {{ strtoupper($order->status) }}
                    </span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                          style="background:{{ $order->payment_status==='Paid'?'#ECFDF5':'#FFFBEB' }};
                                 color:{{ $order->payment_status==='Paid'?'#059669':'#B45309' }};">
                        {{ $order->payment_status }}
                    </span>
                    <span class="text-xs" style="color:#6b90aa;">{{ $order->payment_method }}</span>
                    <a href="{{ route('seller.orders.show', $order->id) }}"
                       class="ml-2 rounded-lg border px-3 py-1 text-xs font-bold transition"
                       style="border-color:#cfdce8;color:#fa4e1c;"
                       onmouseover="this.style.background='#fa4e1c';this.style.color='#fff';"
                       onmouseout="this.style.background='';this.style.color='#fa4e1c';">
                        View Detail →
                    </a>
                    @if ($order->status === 'Delivered')
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold"
                              style="background:#F0FDF4;color:#059669;">
                            ✅ Buyer Confirmed
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid gap-0 lg:grid-cols-[1fr_300px]">
                {{-- Books in this order that belong to this seller --}}
                <div class="px-6 py-5 space-y-3">
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#B0B0B0;">Items</p>
                    @foreach ($order->sellerItems as $item)
                        <div class="flex items-center gap-3 rounded-lg p-2 transition hover:bg-[#f0f6fa]">
                            <img src="{{ $item->book && $item->book->image ? asset('storage/'.$item->book->image) : 'https://placehold.co/48x64/FF6300/FFFFFF?text=B' }}"
                                 class="h-14 w-11 rounded-md object-cover flex-shrink-0 border" style="border-color:#F0E4D8;"
                                 alt="{{ $item->book->title ?? '' }}">
                            <div class="flex flex-1 items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold" style="color:#222222;">{{ $item->book->title ?? '—' }}</p>
                                    <p class="text-xs" style="color:#6b90aa;">Qty: {{ $item->quantity }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold" style="color:#fa4e1c;">${{ number_format($item->price * $item->quantity, 2) }}</p>
                                    <p class="text-xs" style="color:#059669;">You earn: ${{ number_format($item->seller_earning, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Buyer info + status update --}}
                <div class="border-l px-6 py-5" style="border-color:#dce8f0;background:#FFFBF7;">
                    <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#B0B0B0;">Buyer</p>
                    <p class="font-semibold text-sm" style="color:#222222;">{{ $order->full_name }}</p>
                    <p class="text-xs mt-0.5" style="color:#7A7A7A;">{{ $order->phone }}</p>
                    <p class="text-xs" style="color:#7A7A7A;">{{ $order->email }}</p>
                    <p class="text-xs mt-1" style="color:#7A7A7A;">{{ $order->fullAddress() }}</p>

                    {{-- Update status --}}
                    @if (! in_array($order->status, ['Delivered','Cancelled']))
                        <form action="{{ route('seller.orders.update', $order->id) }}" method="POST" class="mt-4">
                            @csrf @method('PATCH')
                            <label class="text-xs font-semibold block mb-1" style="color:#B0B0B0;">Update Status</label>
                            <div class="flex gap-2">
                                <select name="status" class="input py-1.5 text-xs flex-1" style="border-color:#F0E4D8;">
                                    @foreach (['Pending','Processing','Shipped','Delivered'] as $s)
                                        <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ $s }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-bold text-white transition hover:opacity-90" style="background:#002b4d;">Save</button>
                            </div>
                        </form>
                    @else
                        <p class="mt-4 text-xs font-semibold" style="color:{{ $order->status==='Delivered'?'#059669':'#DC2626' }};">
                            {{ $order->status === 'Delivered' ? '✓ Delivered' : '✕ Cancelled' }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card flex flex-col items-center gap-3 py-16 text-center">
            <span class="text-3xl">📋</span>
            <p class="font-display text-lg font-semibold" style="color:#222222;">No orders yet</p>
            <p class="text-sm" style="color:#6b90aa;">Orders for your books will appear here.</p>
        </div>
    @endforelse
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const tabs = document.querySelectorAll('.status-tab');
        const cards = document.querySelectorAll('.order-card');

        tabs.forEach(tab => {
            tab.addEventListener('click', function () {
                const status = this.dataset.status;

                tabs.forEach(t => {
                    t.style.borderColor = 'transparent';
                    t.style.color = '#8A8A8A';
                });
                this.style.borderColor = '#fa4e1c';
                this.style.color = '#fa4e1c';

                cards.forEach(card => {
                    card.style.display = (status === 'All' || card.dataset.status === status) ? '' : 'none';
                });
            });
        });
    });
</script>
</x-seller-layout>