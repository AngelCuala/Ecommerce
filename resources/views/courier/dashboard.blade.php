<x-layout title="Sorting Center Dashboard — ALVY">
<x-courier-nav active="dashboard" />
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

    @if (session('success'))
        <div class="mb-6 rounded-xl border p-3 text-sm"
             style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#fa4e1c;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 rounded-xl border p-3 text-sm"
             style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats row --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 mb-8">
        @php
            $stats = [
                ['label'=>'Total Earnings',    'value'=>'₱'.number_format($courier->total_earnings,2), 'icon'=>'💰'],
                ['label'=>'Today Completed',   'value'=>$todayDone,  'icon'=>'✅'],
                ['label'=>'Active Deliveries', 'value'=>$myActive->count(), 'icon'=>'🚚'],
            ];
        @endphp
        @foreach($stats as $s)
            <div class="rounded-2xl p-5 text-center" style="background:#fff;border:1px solid #cfdce8;">
                <p class="text-2xl">{{ $s['icon'] }}</p>
                <p class="mt-1 font-display text-xl font-bold" style="color:#fa4e1c;">{{ $s['value'] }}</p>
                <p class="text-xs mt-0.5" style="color:#6b90aa;">{{ $s['label'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- My active deliveries --}}
    @if ($myActive->count())
        <h2 class="font-display text-lg font-bold mb-4" style="color:#222;">My Active Deliveries</h2>
        <div class="space-y-3 mb-8">
            @foreach($myActive as $delivery)
                <div class="rounded-2xl p-5" style="background:#fff;border:1px solid #cfdce8;">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-sm" style="color:#222;">
                                Order #{{ str_pad($delivery->order_id,6,'0',STR_PAD_LEFT) }}
                            </p>
                            <p class="text-xs mt-0.5" style="color:#6b90aa;">
                                {{ ucfirst(str_replace('_',' ',$delivery->status)) }}
                                · ₱{{ number_format($delivery->delivery_fee,2) }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            @if($delivery->status === 'accepted')
                                <form action="{{ route('courier.deliveries.pickup',$delivery->id) }}" method="POST">
                                    @csrf
                                    <button class="rounded-xl px-4 py-2 text-xs font-semibold text-white" style="background:#002b4d;">
                                        Confirm Pickup
                                    </button>
                                </form>
                            @elseif($delivery->status === 'picked_up')
                                <form action="{{ route('courier.deliveries.in-transit',$delivery->id) }}" method="POST">
                                    @csrf
                                    <button class="rounded-xl px-4 py-2 text-xs font-semibold text-white" style="background:#2563EB;">
                                        Mark In Transit
                                    </button>
                                </form>
                            @elseif($delivery->status === 'in_transit')
                                <form action="{{ route('courier.deliveries.complete',$delivery->id) }}" method="POST">
                                    @csrf
                                    <button class="rounded-xl px-4 py-2 text-xs font-semibold text-white" style="background:#059669;">
                                        Complete Delivery
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('courier.deliveries.show',$delivery->id) }}"
                               class="rounded-xl border px-4 py-2 text-xs font-semibold"
                               style="border-color:#cfdce8;color:#374151;">Details</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Available deliveries --}}
    <h2 class="font-display text-lg font-bold mb-4" style="color:#222;">Available Deliveries</h2>
    @forelse($available as $delivery)
        <div class="rounded-2xl p-5 mb-3" style="background:#fff;border:1px solid #cfdce8;">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-sm" style="color:#222;">
                        Order #{{ str_pad($delivery->order_id,6,'0',STR_PAD_LEFT) }}
                    </p>
                    <p class="text-xs mt-0.5" style="color:#6b90aa;">
                        Delivery fee: ₱{{ number_format($delivery->delivery_fee,2) }}
                    </p>
                    @if($delivery->order)
                        <p class="text-xs mt-0.5" style="color:#6b90aa;">
                            To: {{ $delivery->order->full_name }} · {{ $delivery->order->city }}
                        </p>
                    @endif
                </div>
                <form action="{{ route('courier.deliveries.accept',$delivery->id) }}" method="POST">
                    @csrf
                    <button class="rounded-xl px-5 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                            style="background:#002b4d;">
                        Accept Delivery
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="rounded-2xl py-12 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <p class="text-3xl">📭</p>
            <p class="mt-3 font-display font-bold" style="color:#222;">No deliveries available right now</p>
            <p class="text-sm mt-1" style="color:#6b90aa;">Check back soon for new orders.</p>
        </div>
    @endforelse
</div>
</x-layout>
