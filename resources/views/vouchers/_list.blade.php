{{--
    Voucher table rows, shared by the admin and seller lists.
    Expects $vouchers and $panel ('admin' | 'seller'); $showSeller adds a store column.
--}}
@php
    $stateStyle = [
        'Running'   => 'background:#ECFDF5;color:#047857;',
        'Scheduled' => 'background:#EFF6FF;color:#1D4ED8;',
        'Ended'     => 'background:#F5F5F4;color:#78716C;',
        'Used up'   => 'background:#F5F5F4;color:#78716C;',
        'Off'       => 'background:#FEF2F2;color:#B91C1C;',
    ];
    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider';
@endphp
<div class="overflow-x-auto rounded-xl border bg-white" style="border-color:#ECE7E3;">
    <table class="w-full text-sm">
        <thead style="background:#FAF7F5;">
            <tr style="color:#8a7a70;">
                <th class="{{ $th }}">Voucher</th>
                @if (! empty($showSeller))<th class="{{ $th }}">Store</th>@endif
                <th class="{{ $th }}">Discount</th>
                <th class="{{ $th }}">Min. spend</th>
                <th class="{{ $th }}">Dates</th>
                <th class="{{ $th }}">Used</th>
                <th class="{{ $th }}">Status</th>
                <th class="{{ $th }} text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($vouchers as $v)
            @php $state = $v->stateLabel(); @endphp
            <tr class="border-t" style="border-color:#F3EDE8;">
                <td class="px-4 py-3">
                    <div class="font-mono text-sm font-bold" style="color:#222;">{{ $v->code }}</div>
                    <div class="text-xs" style="color:#8a7a70;">{{ $v->name }}</div>
                </td>
                @if (! empty($showSeller))
                    <td class="px-4 py-3 text-xs">{{ $v->seller->sellerApplication->shop_name ?? $v->seller->name ?? '—' }}</td>
                @endif
                <td class="px-4 py-3 font-semibold" style="color:#fa4e1c;">{{ $v->discountLabel() }}</td>
                <td class="px-4 py-3">{{ $v->min_spend > 0 ? '₱' . number_format($v->min_spend, 2) : 'None' }}</td>
                <td class="px-4 py-3 text-xs" style="color:#555;">
                    {{ $v->starts_at ? $v->starts_at->format('M d, Y g:i A') : 'Now' }}<br>
                    to {{ $v->ends_at ? $v->ends_at->format('M d, Y g:i A') : 'no end' }}
                </td>
                <td class="px-4 py-3">{{ $v->used_count }}{{ $v->usage_limit ? ' / ' . $v->usage_limit : '' }}</td>
                <td class="px-4 py-3"><span class="rounded-md px-2 py-0.5 text-xs font-semibold" style="{{ $stateStyle[$state] }}">{{ $state }}</span></td>
                <td class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold">
                    @if ($panel === 'seller' || $v->scope !== 'seller')
                        <a href="{{ route($panel . '.vouchers.edit', $v) }}" style="color:#fa4e1c;">Edit</a>
                        <span class="mx-1" style="color:#E7E5E4;">|</span>
                    @endif
                    <form method="POST" action="{{ route($panel . '.vouchers.toggle', $v) }}" class="inline">@csrf @method('PATCH')
                        <button style="color:#57534E;">{{ $v->is_active ? 'Turn off' : 'Turn on' }}</button>
                    </form>
                    @if (($panel === 'seller' || $v->scope !== 'seller') && $v->used_count === 0)
                        <span class="mx-1" style="color:#E7E5E4;">|</span>
                        <form method="POST" action="{{ route($panel . '.vouchers.destroy', $v) }}" class="inline"
                              onsubmit="return confirm('Delete voucher {{ $v->code }}?')">@csrf @method('DELETE')
                            <button style="color:#DC2626;">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-10 text-center text-sm" style="color:#8a7a70;">No vouchers yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
