<footer style="background:#002b4d;color:rgba(255,255,255,.65);">

    {{-- Trust / service features --}}
    <div style="border-bottom:1px solid rgba(255,255,255,.08);">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-8 sm:px-6 md:grid-cols-4 lg:px-8">
            @foreach ([
                ['M5 8h14l1 8H4L5 8zM8 8V6a4 4 0 018 0v2','Free Shipping','On qualifying orders'],
                ['M12 2l8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6l8-4z','Secure Payment','100% protected'],
                ['M3 12a9 9 0 019-9 9 9 0 018 5M21 12a9 9 0 01-9 9 9 9 0 01-8-5M8 8H3V3m13 13h5v5','Easy Returns','30-day returns'],
                ['M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0','24/7 Support','Always here to help'],
            ] as [$path, $title, $sub])
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full"
                          style="background:rgba(250,78,28,.15);">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#fa4e1c;">
                            <path d="{{ $path }}"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-bold" style="color:#fff;">{{ $title }}</p>
                        <p class="text-xs" style="color:rgba(255,255,255,.5);">{{ $sub }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:px-8">

        {{-- Brand --}}
        <div>
            <div class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl overflow-hidden"
                      style="background:transparent;">
                    <img src="{{ asset('images/logo.png') }}" alt="ALVY"
                         style="width:100%;height:100%;object-fit:cover;transform:scale(1.5);transform-origin:center;">
                </span>
                <span class="font-display text-xl font-extrabold" style="color:#fff;">ALVY</span>
            </div>
            <p class="mt-4 text-sm leading-relaxed" style="color:rgba(255,255,255,.5);">
                Your one-stop online marketplace. Shop thousands of products from trusted sellers.
            </p>
            <div class="mt-6 flex gap-3">
                @foreach (['𝕏','IG','FB'] as $soc)
                    <a href="#" aria-label="{{ $soc }}"
                       class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition"
                       style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);"
                       onmouseover="this.style.background='#fa4e1c';this.style.color='#fff';"
                       onmouseout="this.style.background='rgba(255,255,255,.08)';this.style.color='rgba(255,255,255,.7)';">
                        {{ $soc }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Shop --}}
        <div>
            <h4 class="mb-4 text-sm font-bold uppercase tracking-wider" style="color:#fff;">Shop</h4>
            <ul class="space-y-2.5 text-sm">
                <li>
                    <a href="{{ route('categories.page') }}" style="color:rgba(255,255,255,.55);"
                       onmouseover="this.style.color='#fa4e1c';"
                       onmouseout="this.style.color='rgba(255,255,255,.55)';">All Categories</a>
                </li>
                @php $footerCats = \App\Models\Category::orderBy('name')->take(6)->get(); @endphp
                @foreach ($footerCats as $cat)
                    <li>
                        <a href="{{ route('categories.show', \App\Http\Controllers\CategoryPageController::catalog()[0]['slug'] ?? '') }}"
                           style="color:rgba(255,255,255,.55);"
                           onmouseover="this.style.color='#fa4e1c';"
                           onmouseout="this.style.color='rgba(255,255,255,.55)';">{{ $cat->name }}</a>
                    </li>
                @endforeach
            </ul>
        </div>

    </div>

    {{-- Bottom bar --}}
    <div style="border-top:1px solid rgba(255,255,255,.08);">
        <div class="mx-auto flex max-w-7xl items-center justify-center px-4 py-4 text-xs sm:px-6 lg:px-8"
             style="color:rgba(255,255,255,.3);">
            <p>&copy; {{ date('Y') }} ALVY. All rights reserved.</p>
        </div>
    </div>
</footer>
