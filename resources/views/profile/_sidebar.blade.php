{{-- Profile sidebar navigation --}}
@php
    $navItems = [
        'profile.show'          => ['icon' => '👤', 'label' => 'My Profile'],
        'profile.personal-info' => ['icon' => '✏️',  'label' => 'Personal Info'],
        'profile.orders'        => ['icon' => '📦',  'label' => 'My Orders'],
        'profile.addresses'     => ['icon' => '📍',  'label' => 'Addresses'],
        'profile.settings'      => ['icon' => '⚙️',  'label' => 'Settings'],
    ];
@endphp

<aside class="w-full lg:w-56 shrink-0">
    {{-- Avatar card --}}
    <div class="mb-4 rounded-2xl p-5 text-center" style="background:#fff;border:1px solid #cfdce8;">
        <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full font-display text-2xl font-bold"
             style="background:#fa4e1c;color:#fff;">
            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
        </div>
        <p class="font-display font-bold truncate" style="color:#002b4d;">{{ auth()->user()->name }}</p>
        <p class="mt-0.5 text-xs truncate" style="color:#6b90aa;">{{ auth()->user()->email }}</p>
        <span class="mt-2 inline-block rounded px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
              style="background:rgba(250,78,28,.1);color:#fa4e1c;">
            {{ ucfirst(auth()->user()->role ?? 'Buyer') }}
        </span>
    </div>

    {{-- Nav links --}}
    <nav class="rounded-2xl overflow-hidden" style="background:#fff;border:1px solid #cfdce8;">
        @foreach ($navItems as $routeName => $item)
            @php $active = request()->routeIs($routeName); @endphp
            <a href="{{ route($routeName) }}"
               class="flex items-center gap-3 px-4 py-3 text-sm font-semibold transition border-b last:border-b-0"
               style="border-color:#dce8f0;
                      {{ $active ? 'background:#FFF1E6;color:#fa4e1c;' : 'color:#1a4d6e;' }}"
               @if (!$active)
               onmouseover="this.style.background='#FFF8F3';this.style.color='#fa4e1c';"
               onmouseout="this.style.background='';this.style.color='#1a4d6e';"
               @endif>
                <span class="text-base leading-none">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
                @if ($active)
                    <span class="ml-auto h-1.5 w-1.5 rounded-full" style="background:#002b4d;"></span>
                @endif
            </a>
        @endforeach
    </nav>
</aside>
