<x-layout title="Delivery History — ALVY">
<x-courier-nav active="history" />
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-6" style="color:#222;">Delivery History</h1>

    @forelse($deliveries as $delivery)
        @php
            $color = match($delivery->status) {
                'delivered' => '#059669', 'failed' => '#DC2626', default => '#fa4e1c'
            };
            $bg = match($delivery->status) {
                'delivered' => '#ECFDF5', 'failed' => '#FEF2F2', default => 'rgba(250,78,28,.08)'
            };
        @endphp
        <div class="rounded-2xl p-5 mb-3" style="background:#fff;border:1px solid #cfdce8;">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-sm" style="color:#222;">
                        Order #{{ str_pad($delivery->order_id,6,'0',STR_PAD_LEFT) }}
                    </p>
                    @if($delivery->order)
                        <p class="text-xs mt-0.5" style="color:#6b90aa;">
                            {{ $delivery->order->full_name }} · {{ $delivery->order->city }}
                        </p>
                    @endif
                    <p class="text-xs mt-0.5" style="color:#6b90aa;">
                        {{ $delivery->delivered_at?->format('M d, Y H:i') ?? $delivery->updated_at->format('M d, Y H:i') }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold"
                          style="background:{{ $bg }};color:{{ $color }};">
                        {{ ucfirst($delivery->status) }}
                    </span>
                    <p class="mt-1 text-sm font-bold" style="color:#fa4e1c;">
                        +₱{{ number_format($delivery->delivery_fee,2) }}
                    </p>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-2xl py-16 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <p class="text-4xl">📋</p>
            <p class="mt-3 font-display font-bold" style="color:#222;">No delivery history yet</p>
            <p class="text-sm mt-1" style="color:#6b90aa;">Completed deliveries will appear here.</p>
        </div>
    @endforelse

    <div class="mt-4">{{ $deliveries->links() }}</div>
</div>
</x-layout>
