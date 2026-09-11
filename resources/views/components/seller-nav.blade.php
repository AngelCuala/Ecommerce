@php
    $sellerLinks = [
        ['route' => 'seller.dashboard',    'label' => 'Dashboard',  'icon' => '🏠'],
        ['route' => 'seller.books.index',  'label' => 'Products',   'icon' => '📦'],
        ['route' => 'seller.orders.index', 'label' => 'Orders',     'icon' => '📋'],
        ['route' => 'seller.reports',      'label' => 'Reports',    'icon' => '📊'],
        ['route' => 'messages.inbox',      'label' => 'Messages',   'icon' => '💬'],
        ['route' => 'seller.account',      'label' => 'Account',    'icon' => '👤'],
    ];
    $currentRoute   = request()->route()->getName();
    $sellerUnreadMsgs = \App\Models\Message::where('receiver_id', auth()->id())->where('is_read', false)->count();
@endphp

<div class="sticky top-0 z-40 border-b bg-white shadow-sm" style="border-color:#F0E4D8;">
    <div class="mx-auto flex max-w-7xl items-center overflow-x-auto px-4 sm:px-6 lg:px-8"
         style="scrollbar-width:none;">
        {{-- Brand mark --}}
        <a href="{{ route('seller.dashboard') }}"
           class="mr-5 flex flex-shrink-0 items-center gap-2 py-3 text-sm font-extrabold"
           style="color:#fa4e1c;">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg text-xs font-extrabold text-white"
                  style="background:#002b4d;">A</span>
            <span class="hidden sm:inline">Seller Centre</span>
        </a>

        <nav class="flex items-center">
            @foreach ($sellerLinks as $link)
                @php
                    $isActive = $currentRoute === $link['route']
                        || str_starts_with($currentRoute ?? '', str_replace(['.index','.show','.create','.edit'], '', $link['route']));
                @endphp
                <a href="{{ route($link['route']) }}"
                   class="relative flex items-center gap-1.5 px-3.5 py-3.5 text-sm font-semibold whitespace-nowrap transition"
                   style="{{ $isActive ? 'color:#fa4e1c;' : 'color:#666666;' }}"
                   onmouseover="this.style.color='#fa4e1c';"
                   onmouseout="this.style.color='{{ $isActive ? '#fa4e1c' : '#666666' }}';">
                    <span class="text-base leading-none">{{ $link['icon'] }}</span>
                    <span class="hidden sm:inline">{{ $link['label'] }}</span>
                    @if ($link['route'] === 'messages.inbox' && $sellerUnreadMsgs > 0)
                        <span class="flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-bold"
                              style="background:#fa4e1c;color:#fff;">
                            {{ $sellerUnreadMsgs > 9 ? '9+' : $sellerUnreadMsgs }}
                        </span>
                    @endif
                    @if ($isActive)
                        <span class="absolute bottom-0 left-0 right-0 h-[3px] rounded-t-full"
                              style="background:#002b4d;"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <a href="{{ route('home') }}"
           class="ml-auto flex flex-shrink-0 items-center gap-1 rounded-full border px-4 py-1.5 text-xs font-semibold transition"
           style="border-color:#cfdce8;color:#fa4e1c;"
           onmouseover="this.style.background='#fa4e1c';this.style.color='#fff';"
           onmouseout="this.style.background='';this.style.color='#fa4e1c';">
            ← Store
        </a>
    </div>
</div>
