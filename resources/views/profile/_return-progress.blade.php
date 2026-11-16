{{--
    The buyer's return on the order page: where it stands (a step tracker), everything that happened (with times),
    what the seller / ALVY said, the photos, and the Escalate form after a rejection. Needs $order and $return.
--}}
@php
    $rp = \App\Support\ReturnProgress::for($return);
    $tone = ['done' => ['#ECFDF5', '#047857', '#A7F3D0'], 'bad' => ['#FEF2F2', '#B91C1C', '#FECACA'], 'wait' => ['#FFF7ED', '#C2410C', '#FED7AA']][$rp->tone()];
    $dot = ['done' => '#059669', 'bad' => '#DC2626', 'muted' => '#A8A29E', 'info' => '#fa4e1c'];
    $rs = $return->shipment;
@endphp
<section class="pf-card overflow-hidden" id="return" data-return-progress="{{ $return->status }}">
    <div class="flex flex-wrap items-center gap-3 border-b px-5 py-4" style="border-color:{{ $tone[2] }};background:linear-gradient(90deg,{{ $tone[0] }} 0,#FFFFFF 85%);">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white" style="background:{{ $tone[1] }};" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 010 11H11"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold uppercase tracking-wide" style="color:{{ $tone[1] }};">Return / refund</p>
            <h2 class="text-base font-bold" style="color:#222;">{{ $rp->headline() }}</h2>
            <p class="text-sm pf-muted">{{ $return->reason }} · ₱{{ number_format((float) $return->refund_amount, 2) }}{{ $return->isPartial() ? ' (some items)' : '' }} · requested {{ $return->created_at->format('M j, Y') }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold" style="background:{{ $tone[0] }};color:{{ $tone[1] }};">{{ $rp->badge() }}</span>
    </div>

    {{-- The refund: where to send it (asked once the return is approved), then where it stands. --}}
    @php $refund = $rp->refund(); @endphp
    @if ($refund && $refund->status === 'awaiting_details')
        @php $m = old('method', 'gcash'); @endphp
        <form method="POST" action="{{ route('orders.refund-details', $order->id) }}" class="mx-5 mt-5 rounded-xl border-2 p-4" style="border-color:#fa4e1c;background:#FFF8F4;" data-refund-details-form>
            @csrf
            <p class="text-base font-bold" style="color:#222;">Where should we send your ₱{{ number_format((float) $refund->amount, 2) }}?</p>
            <p class="mt-0.5 text-xs" style="color:#57534E;">
                @if ($refund->item_back_at)
                    The item is already back with the seller, so ALVY sends your refund as soon as you save this.
                @else
                    ALVY sends it as soon as the item is back with the seller.
                @endif
                Use an account in your name. Once saved, the details are locked.
            </p>
            @if ($refund->admin_note)
                <p class="mt-2 rounded-lg px-3 py-2 text-xs font-semibold" style="background:#FEF2F2;color:#B91C1C;" data-refund-sent-back>ALVY couldn't send your refund: {{ $refund->admin_note }}</p>
            @endif

            <div class="mt-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Send my refund to">
                @foreach (['gcash' => 'GCash', 'maya' => 'Maya', 'bank' => 'Bank account'] as $key => $label)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm font-semibold" style="border-color:#E7D8CE;">
                        <input type="radio" name="method" value="{{ $key }}" @checked($m === $key) onchange="this.form.dataset.method = this.value" required> {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div data-bank-only @style(['display:none' => $m !== 'bank'])>
                    <label class="mb-1 block text-xs font-semibold" for="rf-bank">Bank *</label>
                    <select id="rf-bank" name="bank_name" class="pf-input">
                        <option value="">Choose your bank</option>
                        @foreach (\App\Models\Refund::BANKS as $b)<option @selected(old('bank_name') === $b)>{{ $b }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold" for="rf-name">Name on the account *</label>
                    <input id="rf-name" name="account_name" value="{{ old('account_name', auth()->user()->name) }}" required maxlength="100" class="pf-input" autocomplete="name">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold" for="rf-number"><span data-number-label>{{ $m === 'bank' ? 'Account number' : 'Mobile number' }}</span> *</label>
                    <input id="rf-number" name="account_number" value="{{ old('account_number') }}" required inputmode="numeric" maxlength="{{ $m === 'bank' ? 16 : 11 }}"
                           class="pf-input font-mono" autocomplete="off" placeholder="{{ $m === 'bank' ? '8 to 16 numbers' : '09XXXXXXXXX' }}"
                           oninput="this.value = this.value.replace(/\D/g, '').slice(0, this.maxLength)">
                    <p class="mt-1 text-[11px] pf-muted" data-number-hint>{{ $m === 'bank' ? 'Numbers only, 8 to 16.' : 'Numbers only, 11 digits.' }}</p>
                </div>
            </div>
            <label class="mt-3 flex items-start gap-2 text-xs" style="color:#57534E;">
                <input type="checkbox" name="confirm" value="1" required class="mt-0.5"> These details are correct. I understand ALVY sends my refund to this account.
            </label>
            <button class="pf-btn mt-3 px-6">Save refund details</button>
        </form>
        <script>
            (function () {
                var f = document.querySelector('[data-refund-details-form]');
                if (!f) return;
                f.addEventListener('change', function () {
                    var bank = (f.querySelector('input[name=method]:checked') || {}).value === 'bank';
                    f.querySelector('[data-bank-only]').style.display = bank ? '' : 'none';
                    f.querySelector('[data-number-label]').textContent = bank ? 'Account number' : 'Mobile number';
                    var n = f.querySelector('#rf-number');
                    n.placeholder = bank ? '8 to 16 numbers' : '09XXXXXXXXX';
                    n.maxLength = bank ? 16 : 11;
                    n.value = n.value.slice(0, n.maxLength);
                    f.querySelector('[data-number-hint]').textContent = bank ? 'Numbers only, 8 to 16.' : 'Numbers only, 11 digits.';
                });
            })();
        </script>
    @elseif ($refund && $refund->method)
        <div class="mx-5 mt-5 flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3 text-sm" style="{{ $refund->isSent() ? 'border-color:#A7F3D0;background:#ECFDF5;' : 'border-color:#BFDBFE;background:#F5F9FF;' }}" data-refund-status="{{ $refund->status }}">
            <div class="min-w-0 flex-1">
                <p class="font-bold" style="color:{{ $refund->isSent() ? '#047857' : '#1E3A8A' }};">
                    @if ($refund->isSent()) Refund sent · ₱{{ number_format((float) $refund->amount, 2) }}
                    @elseif ($refund->status === 'ready_to_send') ALVY is sending your ₱{{ number_format((float) $refund->amount, 2) }} now
                    @else Your ₱{{ number_format((float) $refund->amount, 2) }} is sent once the item is back with the seller
                    @endif
                </p>
                <p class="text-xs" style="color:#57534E;">To {{ $refund->destination() }}@if ($refund->method !== 'original' && $refund->account_name) · {{ $refund->account_name }}@endif
                    @if ($refund->isSent()) · {{ $refund->sent_at->format('M j, Y g:i A') }}@if ($refund->reference) · Ref No. <span class="font-mono">{{ $refund->reference }}</span>@endif @endif
                </p>
            </div>
        </div>
    @endif

    {{-- Steps --}}
    @php $steps = $rp->steps(); @endphp
    <ol class="grid gap-y-4 px-5 pt-5" style="grid-template-columns:repeat({{ count($steps) }},minmax(0,1fr));" data-return-steps>
        @foreach ($steps as $s)
            @php
                [$bg, $fg, $ring] = match ($s['state']) {
                    'done'    => ['#059669', '#fff', '#D1FAE5'],
                    'failed'  => ['#DC2626', '#fff', '#FEE2E2'],
                    'current' => ['#fa4e1c', '#fff', '#FFE4D6'],
                    'skipped' => ['#F5F5F4', '#A8A29E', '#F5F5F4'],
                    default   => ['#fff', '#A8A29E', '#E7E5E4'],
                };
                $next = $steps[$loop->index + 1] ?? null;
                $lineDone = $next && in_array($next['state'], ['done', 'failed', 'current'], true);
            @endphp
            <li class="relative flex flex-col items-center text-center" data-return-step="{{ $s['key'] }}" data-state="{{ $s['state'] }}">
                @if ($next)
                    <span class="absolute left-1/2 top-[15px] h-[3px] w-full" style="background:{{ $lineDone ? '#fa4e1c' : '#EDE7E2' }};" aria-hidden="true"></span>
                @endif
                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 text-xs font-bold"
                      style="background:{{ $bg }};color:{{ $fg }};border-color:{{ $s['state'] === 'todo' ? '#E7E5E4' : $bg }};box-shadow:0 0 0 4px {{ $ring }};">
                    @if ($s['state'] === 'done')✓@elseif ($s['state'] === 'failed')✕@elseif ($s['state'] === 'skipped')–@else{{ $loop->iteration }}@endif
                </span>
                <span class="mt-2 text-xs font-semibold leading-tight" style="color:{{ in_array($s['state'], ['todo', 'skipped'], true) ? '#A8A29E' : '#222' }};">{{ $s['label'] }}</span>
                @if ($s['sub'])<span class="mt-0.5 text-[11px] leading-tight pf-muted">{{ $s['sub'] }}</span>@endif
            </li>
        @endforeach
    </ol>

    <div class="grid gap-5 px-5 py-5 md:grid-cols-[1fr_auto]">
        {{-- What happened --}}
        <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide pf-muted">What happened</h3>
            <ol class="relative ml-2 space-y-3 border-l pl-5" style="border-color:#F0E4DC;" data-return-history>
                @foreach ($rp->history() as $e)
                    <li class="relative">
                        <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full" style="background:{{ $loop->last ? ($dot[$e['tone']] ?? '#fa4e1c') : '#D6CFC9' }};"></span>
                        <p class="text-sm font-semibold" style="color:{{ $e['tone'] === 'bad' ? '#B91C1C' : '#222' }};">{{ $e['title'] }}</p>
                        <p class="text-xs pf-muted">{{ $e['at']?->format('M j, Y · g:i A') }}@if ($e['note']){{ $e['at'] ? ' — ' : '' }}{{ $e['note'] }}@endif</p>
                    </li>
                @endforeach
            </ol>

            @if ($rs && $rs->isOpen())
                <p class="mt-4 rounded-lg px-3 py-2 text-xs" style="background:#EFF6FF;color:#1E40AF;" data-return-shipment="{{ $rs->status }}">
                    <strong>Return pickup:</strong> {{ $rs->statusLabel() }}@if ($rs->status === 'pickup_assigned' && $rs->pickupRider) · rider {{ $rs->pickupRider->full_name }}@endif.
                    @if (in_array($rs->status, ['requested', 'pickup_assigned'], true)) Keep the item packed with its waybill for the rider.@endif
                </p>
            @endif

            @if ($return->status === 'rejected' && $order->status === \App\Models\Order::DELIVERED)
                <form method="POST" action="{{ route('orders.escalate', $order->id) }}" class="mt-4 space-y-2 rounded-xl border p-3.5" style="border-color:#FECACA;background:#FFFBFB;">
                    @csrf
                    <p class="text-sm font-semibold" style="color:#222;">Don't agree with the seller?</p>
                    <p class="text-xs" style="color:#57534E;">Ask ALVY to review it within {{ \App\Models\ReturnRequest::ESCALATE_DAYS }} days of the rejection.</p>
                    <input name="dispute_note" maxlength="500" class="pf-input" placeholder="Why you disagree (optional)">
                    <button class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#B91C1C;">Escalate to ALVY</button>
                </form>
            @endif
        </div>

        {{-- Photos --}}
        @if (count($return->photos()))
            <div class="md:w-44">
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wide pf-muted">Your photos</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($return->photos() as $ph)
                        <a href="{{ \App\Support\ProtectedFile::url($ph) }}" target="_blank" rel="noopener" title="Open the photo">
                            <img src="{{ \App\Support\ProtectedFile::url($ph) }}" class="h-20 w-20 rounded-lg border object-cover" style="border-color:#F0E4DC;" alt="Evidence">
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
