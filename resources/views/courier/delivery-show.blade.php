<x-layout title="Delivery Details — ALVY">
<x-courier-nav active="history" />
<div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">

    <a href="{{ route('courier.dashboard') }}"
       class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold transition"
       style="color:#fa4e1c;"
       onmouseover="this.style.opacity='.7';" onmouseout="this.style.opacity='1';">
        ← Back to Dashboard
    </a>

    <div class="rounded-2xl overflow-hidden mt-4" style="background:#fff;border:1px solid #cfdce8;">

        {{-- Header --}}
        <div class="px-6 py-4" style="background:#FFF6EE;border-bottom:1px solid #cfdce8;">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-display font-bold" style="color:#222;">
                        Order #{{ str_pad($delivery->order_id,6,'0',STR_PAD_LEFT) }}
                    </p>
                    <p class="text-xs mt-0.5" style="color:#6b90aa;">
                        Accepted {{ $delivery->accepted_at?->format('M d, Y H:i') ?? '—' }}
                    </p>
                </div>
                @php
                    $c = match($delivery->status) {
                        'delivered'=>'#059669','failed'=>'#DC2626', default=>'#fa4e1c'
                    };
                @endphp
                <span class="rounded-full px-3 py-1 text-xs font-bold text-white"
                      style="background:{{ $c }};">
                    {{ ucfirst(str_replace('_',' ',$delivery->status)) }}
                </span>
            </div>
        </div>

        {{-- Delivery address --}}
        <div class="px-6 py-5" style="border-bottom:1px solid #cfdce8;">
            <p class="text-xs font-semibold mb-2" style="color:#6b90aa;">DELIVER TO</p>
            @if($delivery->order)
                <p class="font-semibold text-sm" style="color:#222;">{{ $delivery->order->full_name }}</p>
                @if($delivery->order->phone)
                    <p class="text-sm mt-0.5" style="color:#6B7280;">📞 {{ $delivery->order->phone }}</p>
                @endif
                <p class="text-sm mt-0.5" style="color:#6B7280;">{{ $delivery->order->fullAddress() }}</p>
            @endif
        </div>

        {{-- Order items --}}
        @if($delivery->order?->items->count())
            <div class="px-6 py-5" style="border-bottom:1px solid #cfdce8;">
                <p class="text-xs font-semibold mb-3" style="color:#6b90aa;">ITEMS</p>
                <div class="space-y-3">
                    @foreach($delivery->order->items as $item)
                        <div class="flex items-center gap-3">
                            <img src="{{ $item->book && $item->book->image ? asset('storage/'.$item->book->image) : 'https://placehold.co/40x54/F5F0EB/4A2C17?text=📖' }}"
                                 class="h-12 w-9 rounded object-cover flex-shrink-0"
                                 alt="{{ $item->book->title ?? '' }}">
                            <div>
                                <p class="text-sm font-medium" style="color:#222;">{{ $item->book->title ?? '—' }}</p>
                                <p class="text-xs" style="color:#6b90aa;">x{{ $item->quantity }} · ₱{{ number_format($item->price,2) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Earnings --}}
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid #cfdce8;">
            <span class="text-sm" style="color:#6B7280;">Delivery Fee</span>
            <span class="font-display font-bold" style="color:#fa4e1c;">
                ₱{{ number_format($delivery->delivery_fee,2) }}
            </span>
        </div>

        {{-- Action buttons --}}
        @if(in_array($delivery->status, ['accepted','picked_up','in_transit']))
            <div class="px-6 py-5 space-y-3">
                @if($delivery->status === 'accepted')
                    <form action="{{ route('courier.deliveries.pickup',$delivery->id) }}" method="POST">
                        @csrf
                        <button class="w-full rounded-xl py-3 text-sm font-bold text-white" style="background:#002b4d;">
                            Confirm Pickup
                        </button>
                    </form>
                @elseif($delivery->status === 'picked_up')
                    <form action="{{ route('courier.deliveries.in-transit',$delivery->id) }}" method="POST">
                        @csrf
                        <button class="w-full rounded-xl py-3 text-sm font-bold text-white" style="background:#2563EB;">
                            Mark In Transit
                        </button>
                    </form>
                @elseif($delivery->status === 'in_transit')
                    <form action="{{ route('courier.deliveries.complete',$delivery->id) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Delivery Notes (optional)</label>
                            <input type="text" name="notes" placeholder="e.g. Left at gate"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
                        </div>
                        <button class="w-full rounded-xl py-3 text-sm font-bold text-white" style="background:#059669;">
                            ✅ Complete Delivery
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
</x-layout>
