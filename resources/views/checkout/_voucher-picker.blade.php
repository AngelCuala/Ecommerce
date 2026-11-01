{{--
    Shopee-style voucher picker for checkout.
    The hidden <select> is the real form field (and what checkout.js sends to the preview);
    the bar + ticket list are just a nicer way to set it (public/js/voucher-picker.js).

    Params: $kind ('seller'|'platform'|'shipping'), $id, $name, $title, $options (VoucherService::options rows),
            $chosen (applied code or null), $where (text for tickets), $blocks (fn(array $v): bool)
--}}
@php
    $codes = collect($options)->pluck('code')->all();
    $usable = collect($options)->reject(fn ($v) => $blocks($v))->count();
    $chipText = fn (array $v) => $kind === 'shipping'
        ? 'Free shipping' . ($v['value'] ? ' up to ₱' . number_format((float) $v['value']) : '')
        : $v['label'];
@endphp
{{-- Hidden when there are no vouchers of this kind (the field stays, so a typed code can still land here). --}}
<div class="vp {{ count($options) || $chosen ? '' : 'hidden' }}" data-vp="{{ $id }}">
    <select id="{{ $id }}" name="{{ $name }}" class="v-select hidden" data-kind="{{ $kind }}" tabindex="-1" aria-hidden="true">
        <option value="">No voucher</option>
        @foreach ($options as $v)
            <option value="{{ $v['code'] }}" data-chip="{{ $chipText($v) }}" @disabled($blocks($v)) @selected($chosen === $v['code'])>{{ $v['code'] }}</option>
        @endforeach
        @if ($chosen && ! in_array($chosen, $codes, true))
            <option value="{{ $chosen }}" data-chip="{{ $chosen }}" selected>{{ $chosen }}</option>
        @endif
    </select>

    <button type="button" class="vp-bar" data-vp-open="{{ $id }}" aria-haspopup="dialog" @disabled(! count($options) && ! $chosen)>
        <span class="vp-ico vp-ico-{{ $kind }}" aria-hidden="true">
            @if ($kind === 'shipping')
                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            @else
                <svg fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            @endif
        </span>
        <span class="vp-title">{{ $title }}</span>
        <span class="vp-state" data-vp-state
              data-empty="{{ count($options) ? $usable . ' available' : 'None available' }}"></span>
        @if (count($options) || $chosen)
            <svg class="vp-chev" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
        @endif
    </button>

    {{-- Ticket list --}}
    <div class="vp-modal hidden" id="{{ $id }}-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <div class="vp-panel">
            <div class="vp-head">
                <h3 id="{{ $id }}-title">{{ $title }}</h3>
                <button type="button" class="vp-x" data-vp-close aria-label="Close">&times;</button>
            </div>
            <div class="vp-list">
                <label class="vp-none">
                    <input type="radio" name="{{ $id }}-pick" value="" form="vp-no-submit">
                    <span>Don't use a voucher</span>
                </label>
                @foreach ($options as $v)
                    @php $blocked = $blocks($v); @endphp
                    <label class="vp-item {{ $blocked ? 'is-blocked' : '' }}">
                        <x-voucher-ticket :kind="$kind" :type="$v['type']" :value="$v['value']" :max="$v['max']" :min-spend="$v['min_spend']"
                                          :name="$v['name'] ?? null" :where="$where" :ends-at="$v['ends_at']"
                                          :reason="$v['reason']" :saving="! $v['reason'] && $v['discount'] > 0 ? $v['discount'] : null">
                            <input type="radio" name="{{ $id }}-pick" value="{{ $v['code'] }}" form="vp-no-submit" class="vp-radio" @disabled($blocked)
                                   aria-label="Use {{ $chipText($v) }}">
                        </x-voucher-ticket>
                    </label>
                @endforeach
                @if (! count($options))
                    <p class="vp-empty">No {{ strtolower($title) }}s right now. Find more in the <a href="{{ route('vouchers.center') }}" target="_blank">Voucher Center</a>.</p>
                @endif
            </div>
            <div class="vp-foot">
                <a href="{{ route('vouchers.center') }}" target="_blank" class="vp-more">Voucher Center</a>
                <button type="button" class="vp-ok" data-vp-ok>OK</button>
            </div>
        </div>
    </div>
</div>
