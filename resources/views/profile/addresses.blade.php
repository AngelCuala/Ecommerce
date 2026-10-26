<x-layout title="My Addresses — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'Addresses', 'subtitle' => 'Manage your saved delivery addresses.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">

            {{-- Success and error messages show as the pop-up (components/layout) --}}
            @if (request('return') === 'checkout')
                <div class="pf-alert" style="background:#FFF8F4;border:1px solid #FDD5C8;color:#9A3412;">
                    Add or fix your address, then <a href="{{ route('checkout.index', session()->has('buy_now') ? ['buy_now' => 1] : []) }}" class="font-semibold underline">go back to checkout</a>.
                </div>
            @endif
            @if ($errors->any())
                <div class="pf-alert pf-alert-error">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- ═══════════ SAVED ADDRESSES ═══════════ --}}
            @php
                $serviceArea = \App\Support\ServiceArea::enabled();
                $icons = [
                    'Home'  => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
                    'Work'  => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
                    'Other' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
                ];
            @endphp
            <style>
                .ad-card { position: relative; display: flex; flex-direction: column; overflow: hidden; border-radius: 1.1rem; background: #fff;
                           border: 1px solid #F0E4DC; transition: border-color .15s, box-shadow .15s, transform .15s; }
                .ad-card:hover { border-color: #F3C7B6; box-shadow: 0 14px 30px -20px rgba(58,47,41,.35); transform: translateY(-1px); }
                .ad-card.is-default { border: 1.5px solid #fa4e1c; box-shadow: 0 14px 30px -22px rgba(250,78,28,.55); }
                .ad-ribbon { display: flex; align-items: center; gap: .4rem; padding: .45rem 1.1rem; font-size: .7rem; font-weight: 700; letter-spacing: .04em;
                             text-transform: uppercase; color: #fff; background: linear-gradient(90deg, #fa4e1c, #ff7a45); }
                .ad-icon { display: flex; height: 2.6rem; width: 2.6rem; flex: none; align-items: center; justify-content: center; border-radius: .8rem; }
                .ad-tag { display: inline-flex; align-items: center; gap: .3rem; border-radius: 999px; padding: .15rem .55rem; font-size: .68rem; font-weight: 700; }
                .ad-actions { display: flex; align-items: stretch; border-top: 1px solid #F5EBE4; background: #FCFAF8; }
                .ad-actions > * { flex: 1; display: flex; }
                .ad-actions > * + * { border-left: 1px solid #F5EBE4; }
                .ad-action { display: flex; flex: 1; align-items: center; justify-content: center; gap: .4rem; padding: .7rem .5rem; font-size: .78rem; font-weight: 700; white-space: nowrap;
                             color: #57534E; transition: background .15s, color .15s; }
                .ad-action:hover { background: #FFF4EF; color: #fa4e1c; }
                .ad-action.danger:hover { background: #FEF2F2; color: #B91C1C; }
                .ad-add { display: flex; min-height: 14rem; flex-direction: column; align-items: center; justify-content: center; gap: .6rem; border-radius: 1.1rem;
                          border: 1.5px dashed #E6D9CF; background: #FDFAF8; color: #8a7a70; transition: border-color .15s, color .15s, background .15s; }
                .ad-add:hover { border-color: #fa4e1c; color: #fa4e1c; background: #FFF7F3; }
            </style>

            <div class="pf-card p-6 sm:p-7">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="pf-icon">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <div>
                            <h3 class="pf-card-title">My Addresses
                                @if ($addresses->count())<span class="ml-1 rounded-full px-2 py-0.5 align-middle text-[11px] font-bold" style="background:#FFF1EA;color:#D93D0E;">{{ $addresses->count() }} saved</span>@endif
                            </h3>
                            <p class="text-xs pf-muted">Where we deliver your orders. Your default address is picked first at checkout.</p>
                        </div>
                    </div>
                    <button type="button" onclick="openAddressModal()" class="pf-btn">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                        Add Address
                    </button>
                </div>

                {{-- Address list --}}
                <div class="grid gap-4 md:grid-cols-2">
                    @forelse ($addresses as $address)
                        @php
                            $kind = in_array($address->label, ['Home', 'Work'], true) ? $address->label : 'Other';
                            $complete = (bool) $address->municipality_code;
                            $deliverable = ! $serviceArea || ($complete && \App\Support\ServiceArea::coversLocality($address->municipality_code));
                            $street = $address->address_line ?: trim(implode(' ', array_filter([$address->house_number, $address->street])));   // address_line already holds the house no.
                            $area = collect([$address->barangay, $address->city, $address->province])->filter()->implode(', ');
                            $extra = collect([$address->region, $address->zip])->filter()->implode(' · ');
                        @endphp
                        <article class="ad-card {{ $address->is_default ? 'is-default' : '' }}" data-address="{{ $address->id }}">
                            @if ($address->is_default)
                                <div class="ad-ribbon">
                                    <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                    Default address
                                </div>
                            @endif

                            <div class="flex flex-1 flex-col p-5">
                                <div class="flex items-start gap-3">
                                    <span class="ad-icon" style="{{ $address->is_default ? 'background:#fa4e1c;color:#fff;' : 'background:#FFF1EA;color:#fa4e1c;' }}" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icons[$kind] !!}</svg>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <p class="font-display text-[15px] font-extrabold" style="color:#222;">{{ $address->label }}</p>
                                            @if (! $complete)
                                                <span class="ad-tag" style="background:#FFFBEB;color:#B45309;">Needs update</span>
                                            @elseif ($serviceArea)
                                                <span class="ad-tag" style="{{ $deliverable ? 'background:#ECFDF5;color:#047857;' : 'background:#FFFBEB;color:#B45309;' }}" data-deliverable="{{ $deliverable ? 'yes' : 'no' }}">
                                                    <span class="h-1.5 w-1.5 rounded-full" style="background:currentColor;"></span>
                                                    {{ $deliverable ? 'Delivers here' : 'Outside delivery area' }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="mt-0.5 truncate text-sm font-semibold pf-text">{{ $address->full_name }}
                                            @if ($address->phone)<span class="font-normal pf-muted"> · {{ $address->phone }}</span>@endif
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 flex-1 border-l-2 pl-3 text-sm leading-relaxed" style="border-color:{{ $address->is_default ? '#FBD5C5' : '#F0E4DC' }};">
                                    @if ($street || $area)
                                        @if ($street)<p class="font-semibold" style="color:#222;">{{ $street }}</p>@endif
                                        @if ($area)<p class="pf-text">{{ $area }}</p>@endif
                                        @if ($extra)<p class="text-xs pf-muted">{{ $extra }}</p>@endif
                                    @else
                                        <p class="pf-text">{{ $address->full_address }}</p>
                                    @endif
                                    @unless ($complete)
                                        <p class="mt-2 rounded-lg px-2.5 py-1.5 text-xs" style="background:#FFFBEB;color:#B45309;">Edit this address and choose the region, city and barangay from the lists so it can be used at checkout.</p>
                                    @endunless
                                    @if ($complete && ! $deliverable)
                                        <p class="mt-2 rounded-lg px-2.5 py-1.5 text-xs" style="background:#FFFBEB;color:#B45309;">We only deliver within {{ \App\Support\ServiceArea::label() }} for now, so this address can't be used at checkout.</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="ad-actions">
                                @php $editData = $address->only(['id', 'label', 'full_name', 'phone', 'address_line', 'zip', 'region', 'province', 'city', 'barangay', 'province_code', 'municipality_code', 'barangay_code', 'is_default', 'latitude', 'longitude']); @endphp
                                <div>
                                    <button type="button" onclick='openAddressModal(@json($editData))' class="ad-action">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        Edit
                                    </button>
                                </div>
                                @if (! $address->is_default)
                                    <form action="{{ route('addresses.set-default', $address) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="ad-action">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                            Make default
                                        </button>
                                    </form>
                                @endif
                                <form action="{{ route('addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Remove the {{ $address->label }} address of {{ $address->full_name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="ad-action danger">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/></svg>
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="flex flex-col items-center gap-3 rounded-2xl px-6 py-14 text-center md:col-span-2"
                             style="background:#FDF8F5;border:1px dashed #E6D9CF;">
                            <span class="pf-icon h-16 w-16 rounded-2xl">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            </span>
                            <p class="font-display text-base font-extrabold" style="color:#222;">No addresses saved yet</p>
                            <p class="text-sm pf-muted">Add an address to speed up checkout.@if ($serviceArea) We deliver within {{ \App\Support\ServiceArea::label() }}.@endif</p>
                            <button onclick="openAddressModal()" class="pf-btn mt-2">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                                Add Your First Address
                            </button>
                        </div>
                    @endforelse

                    @if ($addresses->count())
                        <button type="button" onclick="openAddressModal()" class="ad-add">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full border-2 border-current" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                            </span>
                            <span class="text-sm font-bold">Add a new address</span>
                            <span class="text-xs">Home, work or anywhere else</span>
                        </button>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
</div>

@include('profile._address-modal')
</x-layout>
