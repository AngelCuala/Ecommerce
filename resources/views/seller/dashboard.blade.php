<x-seller-layout title="Dashboard" active="dashboard">
<div>

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="section-eyebrow" style="color:#fa4e1c;">Seller Center</span>
            <h1 class="mt-1 font-display text-3xl font-bold" style="color:#222222;">
                My Dashboard
            </h1>
            <p class="text-sm mt-1" style="color:#6b90aa;">
                Platform commission: <strong style="color:#fa4e1c;">{{ $commissionRate }}%</strong> per sale
            </p>
        </div>
        <a href="{{ route('seller.books.create') }}"
           class="rounded-xl px-5 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
           style="background:#002b4d;">+ List a Product</a>
    </div>

    @if (session('success'))
        <div class="mt-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#d93d0e;">
            ✓ {{ session('success') }}
        </div>
    @endif

    {{-- Stat cards --}}
    <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">

        <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background:linear-gradient(135deg,#002b4d 0%,#003d6b 100%);">
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.15);"></div>
            <p class="text-xs font-semibold uppercase tracking-widest opacity-90">My Products</p>
            <p class="mt-2 font-display text-3xl font-bold">{{ $books->count() }}</p>
        </div>

        <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background:linear-gradient(135deg,#fa4e1c 0%,#fb7048 100%);">
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.15);"></div>
            <p class="text-xs font-semibold uppercase tracking-widest opacity-90">Total Orders</p>
            <p class="mt-2 font-display text-3xl font-bold">{{ $totalOrders }}</p>
        </div>

        <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background:linear-gradient(135deg,#002b4d 0%,#004a80 100%);">
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.15);"></div>
            <p class="text-xs font-semibold uppercase tracking-widest opacity-90">Gross Revenue</p>
            <p class="mt-2 font-display text-3xl font-bold">${{ number_format($totalRevenue, 2) }}</p>
            <p class="mt-1 text-xs opacity-80">Before {{ $commissionRate }}% commission</p>
        </div>

        <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background:linear-gradient(135deg,#059669 0%,#34D399 100%);">
            <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.15);"></div>
            <p class="text-xs font-semibold uppercase tracking-widest opacity-90">My Earnings</p>
            <p class="mt-2 font-display text-3xl font-bold">${{ number_format($totalEarnings, 2) }}</p>
            <p class="mt-1 text-xs opacity-80">After deducting ${{ number_format($totalCommission, 2) }} commission</p>
        </div>
    </div>

    {{-- Commission info banner --}}
    <div class="mt-6 rounded-2xl border p-4 text-sm" style="background:#e8f0f6;border-color:#cfdce8;">
        <p style="color:#002b4d;">
            💡 <strong>How commission works:</strong>
            For every sale, ALVY deducts <strong>{{ $commissionRate }}%</strong> as a platform fee.
            For example, on a $100 book, you receive <strong>${{ number_format(100 - (100 * $commissionRate / 100), 2) }}</strong> and ALVY keeps <strong>${{ number_format(100 * $commissionRate / 100, 2) }}</strong>.
        </p>
    </div>

    {{-- My Products table --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-xl font-bold" style="color:#222222;">My Products</h2>
            <a href="{{ route('seller.books.index') }}"
               class="text-sm font-semibold transition" style="color:#fa4e1c;"
               onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
                Manage all →
            </a>
        </div>
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead style="background:#e8f0f6;">
                    <tr>
                        <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Title</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Price</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">You Earn</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Stock</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($books->take(6) as $book)
                        @php $earn = round($book->price * (1 - $commissionRate / 100), 2); @endphp
                        <tr style="border-top:1px solid #dce8f0;"
                            onmouseover="this.style.background='#f0f6fa';"
                            onmouseout="this.style.background='';">
                            <td class="px-5 py-3 font-semibold" style="color:#222222;">{{ $book->title }}</td>
                            <td class="px-3 py-3" style="color:#7A7A7A;">${{ number_format($book->price, 2) }}</td>
                            <td class="px-3 py-3 font-semibold" style="color:#059669;">${{ number_format($earn, 2) }}</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background:{{ $book->stock > 0 ? '#ECFDF5' : '#FEF2F2' }};
                                             color:{{ $book->stock > 0 ? '#059669' : '#DC2626' }};">
                                    {{ $book->stock }} left
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('seller.books.edit', $book->id) }}"
                                   class="text-xs font-semibold mr-3" style="color:#fa4e1c;"
                                   onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">Edit</a>
                                <form action="{{ route('seller.books.destroy', $book->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Remove this book?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs font-semibold" style="color:#DC2626;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">
                                No books yet.
                                <a href="{{ route('seller.books.create') }}" style="color:#fa4e1c;" class="underline ml-1">Add your first book</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent orders --}}
    @if ($recentOrders->count())
    <div class="mt-10">
        <h2 class="font-display text-xl font-bold mb-4" style="color:#222222;">Recent Sales</h2>
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead style="background:#e8f0f6;">
                    <tr>
                        <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Book</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Buyer</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Qty</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Gross</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Commission</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Your Earning</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentOrders as $item)
                        <tr style="border-top:1px solid #dce8f0;"
                            onmouseover="this.style.background='#f0f6fa';"
                            onmouseout="this.style.background='';">
                            <td class="px-5 py-3 font-semibold" style="color:#222222;">{{ $item->book->title }}</td>
                            <td class="px-3 py-3" style="color:#7A7A7A;">{{ $item->order->user->name ?? 'Guest' }}</td>
                            <td class="px-3 py-3" style="color:#7A7A7A;">{{ $item->quantity }}</td>
                            <td class="px-3 py-3" style="color:#7A7A7A;">${{ number_format($item->price * $item->quantity, 2) }}</td>
                            <td class="px-3 py-3" style="color:#fa4e1c;">-${{ number_format($item->commission_amount, 2) }}</td>
                            <td class="px-5 py-3 text-right font-bold" style="color:#059669;">${{ number_format($item->seller_earning, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
</x-seller-layout>