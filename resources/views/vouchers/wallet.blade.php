<x-layout title="My Vouchers — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    @include('profile._page-header', ['title' => 'My Vouchers', 'subtitle' => 'Vouchers you claimed. The best ones are applied for you at checkout.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">
            @if (session('success'))<div class="pf-alert pf-alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="pf-alert pf-alert-error">{{ session('error') }}</div>@endif

            <div class="pf-card p-5">
                <h3 class="pf-card-title mb-3">Claimed</h3>
                @forelse ($claims as $claim)
                    @php $v = $claim->voucher; $ok = $claim->state === 'Available'; @endphp
                    <div class="mb-2 flex flex-wrap items-center gap-3 rounded-lg border p-3 {{ $ok ? '' : 'opacity-60' }}" style="border-color:#F0E4DC;">
                        <span class="rounded-md px-2 py-1 font-mono text-xs font-bold" style="background:#FFF1EB;color:#C2410C;">{{ $v->code }}</span>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="font-semibold" style="color:#222;">{{ $v->discountLabel() }}@if ($v->min_spend > 0) <span class="font-normal pf-muted">· min spend ₱{{ number_format($v->min_spend, 0) }}</span>@endif</p>
                            <p class="text-xs pf-muted">
                                {{ $v->scope === 'seller' ? ($v->seller?->storeName() ?? 'Store') . ' voucher' : ($v->isShipping() ? 'Shipping voucher' : 'ALVY voucher') }}
                                @if ($v->ends_at) · until {{ $v->ends_at->format('M d, Y') }}@endif
                            </p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" style="{{ $ok ? 'background:#ECFDF5;color:#047857;' : 'background:#F5F5F4;color:#57534E;' }}">{{ $claim->state }}</span>
                    </div>
                @empty
                    <p class="text-sm pf-muted">No vouchers yet. Claim them on store pages, product pages, or below.</p>
                @endforelse
            </div>

            @if ($claimable->isNotEmpty())
                <div class="pf-card p-5">
                    <h3 class="pf-card-title mb-3">ALVY vouchers you can claim</h3>
                    @foreach ($claimable as $v)
                        <div class="mb-2 flex flex-wrap items-center gap-3 rounded-lg border p-3" style="border-color:#F0E4DC;">
                            <span class="rounded-md px-2 py-1 font-mono text-xs font-bold" style="background:#EFF6FF;color:#1D4ED8;">{{ $v->code }}</span>
                            <p class="min-w-0 flex-1 text-sm" style="color:#222;">{{ $v->discountLabel() }}@if ($v->min_spend > 0) <span class="pf-muted">· min spend ₱{{ number_format($v->min_spend, 0) }}</span>@endif</p>
                            <form action="{{ route('vouchers.claim', $v) }}" method="POST">@csrf
                                <button class="pf-btn !py-1.5 px-4 text-sm">Claim</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
</div>
</x-layout>
