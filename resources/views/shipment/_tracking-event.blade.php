{{-- One update on the shipment timeline ($e: ShipmentEvent; $color: dot; $latest: newest; $line: connector below). --}}
<li class="relative flex gap-3 pb-4" data-event="{{ $e->event_type }}">
    @if ($line)<span class="absolute left-[5px] top-4 h-full w-px" style="background:#EDE6E0;" aria-hidden="true"></span>@endif
    <span class="relative mt-1 h-3 w-3 shrink-0 rounded-full" style="background:{{ $color }};{{ $latest ? 'box-shadow:0 0 0 4px ' . ($color === '#DC2626' ? '#FEE2E2' : ($color === '#059669' ? '#D1FAE5' : '#FFE4D9')) . ';' : '' }}"></span>
    <div class="min-w-0">
        <p class="text-sm {{ $latest ? 'font-bold' : 'font-medium' }}" style="color:{{ $latest ? '#222' : '#57534E' }};">
            {{ $e->label() }}
            @if ($latest)<span class="ml-1 rounded-full px-1.5 py-0.5 align-middle text-[10px] font-bold" style="{{ $color === '#059669' ? 'background:#ECFDF5;color:#047857;' : 'background:#FFF1EE;color:#D93D0E;' }}">Latest</span>@endif
        </p>
        @if ($e->note && $e->event_type !== 'transfer_dispatched')<p class="text-xs" style="color:#8a7f78;">{{ $e->note }}</p>@endif
        <p class="text-xs" style="color:#a39890;">{{ $e->occurred_at->format('M j, Y g:i A') }}</p>
    </div>
</li>
