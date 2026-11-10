{{--
    Shipment tracking for a buyer or seller ($track = App\Support\ShipmentProgress::for($order)).
    Built from recorded events only; upcoming steps are shown as expected, never as done.
--}}
@php
    $warn = ['delivery_failed', 'transfer_delayed', 'exception_reported', 'return_to_sender', 'cancelled', 'lost', 'damaged', 'pickup_rejected'];
    $pStatus = $track['parcel']?->status;
    $delivered = $pStatus === 'delivered';
    $trouble = in_array($pStatus, ['failed', 'return_to_sender', 'returned', 'lost', 'damaged', 'cancelled', 'pickup_rejected'], true);

    // Progress bar: how far the parcel got (0 ordered … 4 delivered), from its confirmed status.
    $milestones = ['Ordered', 'Collected', 'At sorting center', 'Out for delivery', 'Delivered', 'Received'];
    $reached = match (true) {
        ! $pStatus                                                                             => 0,
        in_array($pStatus, ['pending_pickup', 'pickup_approved', 'pickup_rejected', 'cancelled'], true) => 0,
        $pStatus === 'in_transit_to_hub'                                                       => 1,
        in_array($pStatus, ['in_transit', 'failed'], true)                                    => 3,
        $delivered && ! empty($track['received'])                                              => 5,
        $delivered                                                                             => 4,
        default                                                                                => 2,   // at / between sorting centers, or on its way back
    };
    $pill = $delivered ? 'background:#ECFDF5;color:#047857;' : ($trouble ? 'background:#FEF2F2;color:#B91C1C;' : 'background:#FFF1EE;color:#D93D0E;');

    // Route: seller → each sorting center it was confirmed at → buyer.
    $route = collect($track['hubs'])->pluck('name')->filter()->unique()->values();

    $events = $track['events']->values();
    $shown = 5;
    $older = $events->count() > $shown ? $events->slice(0, $events->count() - $shown) : collect();
    $recent = $events->slice($older->count());
    $dot = fn ($e, $isLatest) => in_array($e->event_type, $warn, true) ? '#DC2626' : ($isLatest ? ($e->event_type === 'delivered' ? '#059669' : '#fa4e1c') : '#D6CFC9');
@endphp
<style>.trk-more:not([open]) .trk-open, .trk-more[open] .trk-closed { display: none; } .trk-more summary::-webkit-details-marker { display: none; }</style>
<section class="overflow-hidden rounded-2xl border bg-white" style="border-color:#F0E4DC;box-shadow:0 1px 2px rgba(58,47,41,.04),0 10px 30px -18px rgba(58,47,41,.18);" data-shipment-tracking>
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4" style="border-color:#F0E4DC;">
        <div class="flex min-w-0 items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:#FFF1EA;color:#fa4e1c;" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/></svg>
            </span>
            <div class="min-w-0">
                <h2 class="text-sm font-bold" style="color:#222;">Shipment tracking</h2>
                <p class="mt-0.5 text-xs" style="color:#8a7f78;">
                    Tracking no. <span class="font-mono font-semibold" style="color:#222;" data-tracking-number>{{ $track['tracking'] }}</span>
                    @if ($track['tracking'])
                        <button type="button" class="ml-1 rounded px-1.5 py-0.5 text-[11px] font-semibold hover:underline" style="color:#fa4e1c;"
                                onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $track['tracking'] }}').then(() => { this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1500); })">Copy</button>
                    @endif
                    @if ($track['parcel']?->waybill_number)<span class="block sm:inline">Waybill <span class="font-mono">{{ $track['parcel']->waybill_number }}</span></span>@endif
                </p>
            </div>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold" style="{{ $pill }}" data-shipment-status>{{ $track['status'] }}</span>
    </div>

    <div class="px-5 py-5">
        {{-- Progress --}}
        <ol class="grid grid-cols-6 gap-1" aria-label="Shipment progress">
            @foreach ($milestones as $i => $label)
                @php
                    $isDone = $i < $reached || ($i === $reached && $delivered);
                    $isNow = $i === $reached && ! $delivered;
                    $isBad = $isNow && $trouble;
                    $color = $isBad ? '#DC2626' : ($isDone || $isNow ? '#fa4e1c' : '#E7DED7');
                    if ($delivered) { $color = '#059669'; }
                @endphp
                <li class="flex flex-col items-center text-center" @if ($isNow) aria-current="step" @endif>
                    <div class="flex w-full items-center">
                        <span class="h-0.5 flex-1 rounded {{ $i === 0 ? 'opacity-0' : '' }}" style="background:{{ $i <= $reached ? $color : '#E7DED7' }};"></span>
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 text-[11px] font-bold"
                              style="border-color:{{ $color }};background:{{ $isDone ? $color : '#fff' }};color:{{ $isDone ? '#fff' : $color }};">
                            @if ($isDone)
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @elseif ($isBad)!
                            @else{{ $i + 1 }}@endif
                        </span>
                        <span class="h-0.5 flex-1 rounded {{ $loop->last ? 'opacity-0' : '' }}" style="background:{{ $i < $reached ? $color : '#E7DED7' }};"></span>
                    </div>
                    <span class="mt-1.5 text-[11px] leading-tight {{ $isNow ? 'font-bold' : 'font-medium' }}" style="color:{{ $isNow || $isDone ? '#222' : '#a8a29e' }};">{{ $label }}</span>
                </li>
            @endforeach
        </ol>

        {{-- Key facts --}}
        <dl class="mt-5 grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl p-3" style="background:#FAF7F5;">
                <dt class="text-[11px] font-semibold uppercase tracking-wide" style="color:#8a7f78;">Last confirmed location</dt>
                <dd class="mt-1 text-sm font-semibold" style="color:#222;" data-location>{{ $track['location'] }}</dd>
            </div>
            <div class="rounded-xl p-3" style="background:#FAF7F5;">
                <dt class="text-[11px] font-semibold uppercase tracking-wide" style="color:#8a7f78;">Delivery rider</dt>
                <dd class="mt-1 text-sm font-semibold" style="color:#222;">{{ $delivered ? 'Delivery completed' : ($track['rider_assigned'] ? 'Assigned' : 'Not assigned yet') }}</dd>
            </div>
            <div class="rounded-xl p-3" style="background:{{ $track['delivered_at'] ? '#ECFDF5' : '#FAF7F5' }};">
                <dt class="text-[11px] font-semibold uppercase tracking-wide" style="color:{{ $track['delivered_at'] ? '#047857' : '#8a7f78' }};">{{ $track['delivered_at'] ? 'Delivered' : 'Sorting centers visited' }}</dt>
                <dd class="mt-1 text-sm font-semibold" style="color:#222;">
                    @if ($track['delivered_at']){{ $track['delivered_at']->format('M j, Y g:i A') }}
                    @else{{ $route->implode(' → ') ?: 'None yet' }}@endif
                </dd>
            </div>
        </dl>

        @if ($track['under_review'])
            <p class="mt-3 flex items-start gap-2 rounded-xl px-3 py-2.5 text-xs" style="background:#FFFBEB;color:#92400E;">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                This shipment is being reviewed by our logistics team. We'll update you here.
            </p>
        @endif
        @if ($track['attempts']->isNotEmpty())
            <p class="mt-3 rounded-xl px-3 py-2.5 text-xs" style="background:#FEF2F2;color:#B91C1C;">
                {{ $track['attempts']->count() }} failed delivery {{ \Illuminate\Support\Str::plural('attempt', $track['attempts']->count()) }}:
                {{ $track['attempts']->map(fn ($a) => $a->failure_reason . ' (' . $a->updated_at->format('M j') . ')')->implode(', ') }}
            </p>
        @endif

        {{-- Route between sorting centers --}}
        @if ($track['transfers']->isNotEmpty())
            <div class="mt-5">
                <p class="text-[11px] font-semibold uppercase tracking-wide" style="color:#8a7f78;">Route between sorting centers</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($track['transfers'] as $t)
                        @php $arrived = $t->status === 'received'; @endphp
                        <li class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border px-3 py-2 text-xs" style="border-color:#F0E4DC;color:#57534E;" data-transfer-row>
                            <span class="font-semibold" style="color:#222;">{{ \App\Support\SortingCenters::displayName($t->fromSortingCenter) }}</span>
                            <svg class="h-3.5 w-3.5" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                            <span class="font-semibold" style="color:#222;">{{ \App\Support\SortingCenters::displayName($t->toSortingCenter) }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="{{ $arrived ? 'background:#ECFDF5;color:#047857;' : 'background:#FFF7ED;color:#C2410C;' }}">{{ $t->status === 'pending' ? 'Being prepared' : $t->statusLabel() }}</span>
                            <span class="w-full sm:ml-auto sm:w-auto" style="color:#a39890;">
                                @if ($t->dispatched_at)left {{ $t->dispatched_at->format('M j, g:i A') }}@endif
                                @if ($t->received_at && $arrived) · arrived {{ $t->received_at->format('M j, g:i A') }}@endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Timeline (oldest first; older updates folded away) --}}
        <div class="mt-6">
            <p class="text-[11px] font-semibold uppercase tracking-wide" style="color:#8a7f78;">Updates</p>
            <ol class="mt-3" data-timeline>
                @if ($older->isNotEmpty())
                    <li class="mb-3 pl-6">
                        <details class="trk-more">
                            <summary class="cursor-pointer list-none text-xs font-semibold" style="color:#fa4e1c;">
                                <span class="trk-closed">Show {{ $older->count() }} earlier {{ \Illuminate\Support\Str::plural('update', $older->count()) }}</span>
                                <span class="trk-open">Hide earlier updates</span>
                            </summary>
                            <ol class="mt-3">
                                @foreach ($older as $e)
                                    @include('shipment._tracking-event', ['e' => $e, 'color' => $dot($e, false), 'latest' => false, 'line' => true])
                                @endforeach
                            </ol>
                        </details>
                    </li>
                @endif
                @foreach ($recent as $e)
                    @include('shipment._tracking-event', ['e' => $e, 'color' => $dot($e, $loop->last), 'latest' => $loop->last, 'line' => ! $loop->last || $track['expected'] !== []])
                @endforeach
                @foreach ($track['expected'] as $step)
                    <li class="relative flex gap-3 pb-4 pl-0" data-expected>
                        @unless ($loop->last)<span class="absolute left-[5px] top-4 h-full w-px" style="background:repeating-linear-gradient(#E7DED7 0 4px, transparent 4px 8px);" aria-hidden="true"></span>@endunless
                        <span class="relative mt-1 h-3 w-3 shrink-0 rounded-full border-2" style="border-color:#D6D3D1;background:#fff;"></span>
                        <p class="text-sm" style="color:#a8a29e;">{{ $step }} <span class="text-xs">· expected</span></p>
                    </li>
                @endforeach
            </ol>
        </div>

        @if ($track['pod'])
            <a href="{{ \App\Support\Portal::route('shipments.pod', $track['parcel']->order_id) }}" target="_blank" rel="noopener"
               class="mt-1 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold hover:underline" style="border-color:#FBD5C5;color:#fa4e1c;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="12" cy="12" r="3"/></svg>
                View proof of delivery photo
            </a>
        @endif
    </div>
    <p class="border-t px-5 py-3 text-[11px]" style="border-color:#F0E4DC;color:#a8a29e;background:#FCFAF9;">Updated whenever our staff or riders confirm a step (scan, receipt, delivery). No live GPS tracking.</p>
</section>
