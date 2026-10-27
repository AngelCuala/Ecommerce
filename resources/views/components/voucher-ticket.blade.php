{{--
    Shopee-style voucher ticket. The right-hand action (Claim / Use / radio) goes in the slot.

    Props:
      kind      'shipping' | 'platform' | 'seller'
      type      'fixed' | 'percent'      value, max (cap), minSpend
      name      voucher name             where  e.g. "All stores", "Only at Lucy's Shop"
      endsAt    Carbon|string|null       usedPct  0–100 or null (no total limit)
      reason    why it can't be used now (greys the ticket)   saving  ₱ saved on this order, if known
      muted     grey it out without a reason (e.g. already used)
--}}
@props([
    'kind' => 'platform', 'type' => 'fixed', 'value' => 0, 'max' => null, 'minSpend' => 0,
    'name' => null, 'where' => null, 'endsAt' => null, 'usedPct' => null,
    'reason' => null, 'saving' => null, 'muted' => false,
])
@php
    $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $isShip = $kind === 'shipping';
    if ($isShip) {
        [$big, $small] = ['FREE', 'SHIPPING'];
    } elseif ($type === 'percent') {
        [$big, $small] = [$num($value) . '%', 'OFF'];
    } else {
        [$big, $small] = ['₱' . number_format((float) $value), 'OFF'];
    }
    $headline = match (true) {
        $isShip           => 'Free shipping' . ($value ? ' up to ₱' . number_format((float) $value) : ''),
        $type === 'percent' => $num($value) . '% off' . ($max ? ' · capped at ₱' . number_format((float) $max) : ''),
        default           => '₱' . number_format((float) $value) . ' off',
    };
    $tone = ['shipping' => ['#0F9F8A', '#E8F7F4'], 'seller' => ['#fa4e1c', '#FFF1EB'], 'platform' => ['#E0431A', '#FFF1EB']][$kind] ?? ['#fa4e1c', '#FFF1EB'];
    $end = $endsAt ? (is_string($endsAt) ? \Illuminate\Support\Carbon::parse($endsAt) : $endsAt) : null;
    $endText = ! $end ? 'No expiry' : ($end->isPast() ? 'Expired' : ($end->diffInHours(now(), true) < 72 ? 'Ends ' . $end->diffForHumans() : 'Valid till ' . $end->copy()->tz(config('app.business_timezone'))->format('M j, Y')));
    $urgent = $end && ! $end->isPast() && $end->diffInHours(now(), true) < 72;
    $grey = $muted || filled($reason);
@endphp
@once
<style>
    .vt { position: relative; display: flex; min-height: 6.25rem; border-radius: .625rem; background: #fff; border: 1px solid #ECE7E3; overflow: hidden; transition: border-color .15s, box-shadow .15s; }
    .vt:hover { box-shadow: 0 6px 16px -10px rgba(58,47,41,.35); }
    .vt.is-grey { background: #FAFAF9; }
    .vt.is-grey .vt-stub { filter: grayscale(1); opacity: .55; }
    .vt.is-grey .vt-title { color: #8a7f78; }
    .vt-stub { position: relative; display: flex; width: 6.5rem; flex-shrink: 0; flex-direction: column; align-items: center; justify-content: center; gap: .1rem; color: #fff; text-align: center; padding: .5rem; }
    /* perforated edge */
    .vt-stub::after { content: ""; position: absolute; right: -5px; top: 0; bottom: 0; width: 10px; background: radial-gradient(circle at 5px 6px, #fff 3.5px, transparent 4px) 0 0 / 10px 12px repeat-y; }
    .vt.is-grey .vt-stub::after { background-image: radial-gradient(circle at 5px 6px, #FAFAF9 3.5px, transparent 4px); }
    .vt-big { font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.5rem; font-weight: 900; line-height: 1; letter-spacing: -.02em; }
    .vt-big.is-word { font-size: 1.15rem; letter-spacing: 0; }
    .vt-small { font-size: .625rem; font-weight: 800; letter-spacing: .12em; }
    .vt-body { display: flex; min-width: 0; flex: 1; flex-direction: column; justify-content: center; gap: .15rem; padding: .7rem .9rem .7rem 1.1rem; }
    .vt-title { font-size: .875rem; font-weight: 700; color: #222; }
    .vt-cond { display: flex; flex-wrap: wrap; gap: .3rem .5rem; font-size: .72rem; color: #57534E; }
    .vt-cond span + span::before { content: "·"; margin-right: .5rem; color: #c4b8b0; }
    .vt-foot { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem .6rem; margin-top: .15rem; font-size: .7rem; color: #8a7f78; }
    .vt-bar { display: inline-block; width: 4.5rem; height: 4px; border-radius: 999px; background: #F2EEEA; overflow: hidden; vertical-align: middle; }
    .vt-bar i { display: block; height: 100%; border-radius: 999px; }
    .vt-reason { margin-top: .2rem; font-size: .72rem; font-weight: 600; color: #B45309; }
    .vt-save { margin-top: .2rem; font-size: .72rem; font-weight: 700; color: #059669; }
    .vt-act { display: flex; flex-shrink: 0; align-items: center; padding: 0 .9rem 0 .25rem; }
    @media (max-width: 420px) { .vt-stub { width: 5.25rem; } .vt-big { font-size: 1.25rem; } }
</style>
@endonce
<div {{ $attributes->class(['vt', 'is-grey' => $grey]) }}>
    <div class="vt-stub" style="background:{{ $tone[0] }};">
        @if ($isShip)
            <svg class="mb-0.5 h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        @endif
        <span class="vt-big {{ $isShip ? 'is-word' : '' }}">{{ $big }}</span>
        <span class="vt-small">{{ $small }}</span>
    </div>
    <div class="vt-body">
        <div class="vt-title">{{ $headline }}</div>
        <div class="vt-cond">
            <span>{{ $minSpend > 0 ? 'Min. spend ₱' . number_format((float) $minSpend) : 'No min. spend' }}</span>
            @if ($where)<span>{{ $where }}</span>@endif
        </div>
        @if ($name && $name !== $headline)
            <div class="vt-foot" style="margin-top:0;">{{ $name }}</div>
        @endif
        <div class="vt-foot">
            <span style="{{ $urgent ? 'color:#DC2626;font-weight:600;' : '' }}">{{ $endText }}</span>
            @if ($usedPct !== null)
                <span><span class="vt-bar"><i style="width:{{ max(4, min(100, (int) $usedPct)) }}%;background:{{ $tone[0] }};"></i></span> {{ (int) $usedPct }}% used</span>
            @endif
        </div>
        @if ($reason)
            <div class="vt-reason">{{ ucfirst(rtrim($reason, '.')) }}.</div>
        @elseif ($saving)
            <div class="vt-save">Saves ₱{{ number_format((float) $saving, 2) }} on this order</div>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="vt-act">{{ $slot }}</div>
    @endif
</div>
