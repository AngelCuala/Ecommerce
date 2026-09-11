<footer style="background:#002b4d;color:rgba(255,255,255,.65);">

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
