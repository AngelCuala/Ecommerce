@php
    $footerCats = array_slice(\App\Http\Controllers\CategoryPageController::catalog(), 0, 4);
    $policy = fn ($key) => route('policies.show', $key);
    $columns = [
        'Shop' => array_merge(
            array_map(fn ($c) => [$c['name'], route('categories.show', $c['slug'])], $footerCats),
            [['All categories', route('categories.page')]]
        ),
        'Help' => [
            ['My orders', route('profile.orders')],
            ['Cart', route('cart.index')],
            ['Return & refund policy', $policy('return_refund_policy')],
            ['Prohibited items', $policy('prohibited_items')],
        ],
        'ALVY' => [
            ['About us', $policy('about_us')],
            ['Sell on ALVY', route('seller.register')],
            ['Seller policy', $policy('seller_policy')],
        ],
    ];
@endphp
<style>
    .ft-link { color: rgba(255,255,255,.65); transition: color .12s; }
    .ft-link:hover { color: #fff; text-decoration: underline; text-underline-offset: 3px; }
</style>
<footer style="background:#002b4d;color:rgba(255,255,255,.65);">
    <div class="mx-auto grid max-w-7xl grid-cols-2 gap-x-6 gap-y-4 px-4 py-6 sm:grid-cols-3 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr_1fr] lg:px-8">

        {{-- Brand --}}
        <div class="col-span-2 max-w-xs sm:col-span-3 md:col-span-1">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-md" style="background:#fff;padding:3px;">
                    <img src="{{ asset('images/logo-mark.png') }}" alt=""
                         style="width:100%;height:100%;object-fit:contain;display:block;">
                </span>
                <span class="font-display text-base font-extrabold" style="color:#fff;">ALVY</span>
            </a>
            <p class="mt-1.5 text-xs leading-relaxed">
                An online marketplace for local sellers, with delivery to your barangay.
            </p>
        </div>

        {{-- Link columns --}}
        @foreach ($columns as $heading => $links)
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider" style="color:#fff;">{{ $heading }}</h4>
                <ul class="mt-1.5 space-y-1 text-xs">
                    @foreach ($links as [$label, $url])
                        <li><a href="{{ $url }}" class="ft-link">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    {{-- Bottom bar --}}
    <div style="border-top:1px solid rgba(255,255,255,.1);">
        <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-2.5 text-[11px] sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
             style="color:rgba(255,255,255,.5);">
            <p>&copy; {{ date('Y') }} ALVY. All rights reserved.</p>
            <div class="flex gap-5">
                <a href="{{ $policy('terms_of_service') }}" class="ft-link">Terms of Service</a>
                <a href="{{ $policy('privacy_policy') }}" class="ft-link">Privacy Policy</a>
            </div>
        </div>
    </div>
</footer>
