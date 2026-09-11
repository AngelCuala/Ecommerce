<x-admin-layout title="Seller Compliance" active="compliance">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

{{-- Overview stats --}}
<div class="grid gap-5 sm:grid-cols-3 mb-8">
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background:linear-gradient(135deg,#002b4d,#fb7048);">
        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.15);"></div>
        <p class="text-xs font-semibold uppercase tracking-widest opacity-90">Total Sellers</p>
        <p class="mt-2 font-display text-3xl font-bold">{{ $sellers->count() }}</p>
    </div>
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background:linear-gradient(135deg,#D97706,#F59E0B);">
        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.15);"></div>
        <p class="text-xs font-semibold uppercase tracking-widest opacity-90">Flagged Products</p>
        <p class="mt-2 font-display text-3xl font-bold">{{ $flagged->count() }}</p>
        <p class="mt-1 text-xs opacity-80">Category mismatch detected</p>
    </div>
    <div class="relative overflow-hidden rounded-2xl p-6 text-white" style="background:linear-gradient(135deg,#DC2626,#EF4444);">
        <div class="absolute -right-4 -top-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.15);"></div>
        <p class="text-xs font-semibold uppercase tracking-widest opacity-90">Total Violations</p>
        <p class="mt-2 font-display text-3xl font-bold">{{ $recentViolations->count() }}</p>
    </div>
</div>

{{-- Flagged products (category mismatch) --}}
@if ($flagged->count())
<div class="card overflow-hidden mb-8">
    <div class="flex items-center gap-3 px-6 py-4" style="background:#FFFBEB;border-bottom:2px solid #FDE68A;">
        <span class="text-xl">⚠️</span>
        <div>
            <h2 class="font-display text-base font-bold" style="color:#92400E;">Category Mismatch Detected</h2>
            <p class="text-xs mt-0.5" style="color:#B45309;">These products appear to be outside the seller's registered line of business.</p>
        </div>
    </div>
    <table class="w-full text-sm">
        <thead style="background:#FFFDF0;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Product</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Listed Category</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Seller</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Registered For</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($flagged as $book)
                <tr style="border-top:1px solid #FEF9C3;"
                    onmouseover="this.style.background='#FFFDF0';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $book->image ? asset('storage/'.$book->image) : 'https://placehold.co/40x54/FF6300/fff?text=P' }}"
                                 class="h-10 w-7 rounded object-cover flex-shrink-0" alt="{{ $book->title }}">
                            <p class="font-semibold" style="color:#222222;">{{ $book->title }}</p>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:#FEF2F2;color:#DC2626;">
                            {{ $book->category->name ?? '—' }}
                        </span>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $book->seller->name ?? '—' }}</td>
                    <td class="px-3 py-3 text-xs" style="color:#D97706;">
                        {{ $book->seller?->sellerApplication?->line_of_business ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.compliance.show', $book->seller_id) }}"
                           class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                           style="background:#e8f0f6;color:#fa4e1c;border:1px solid #cfdce8;"
                           onmouseover="this.style.background='#fa4e1c';this.style.color='#fff';"
                           onmouseout="this.style.background='#e8f0f6';this.style.color='#fa4e1c';">
                            Review →
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Sellers list --}}
<div class="grid gap-6 lg:grid-cols-2">

    {{-- All sellers --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4" style="background:#e8f0f6;border-bottom:1px solid #cfdce8;">
            <h2 class="font-display text-base font-bold" style="color:#222222;">All Sellers</h2>
        </div>
        <table class="w-full text-sm">
            <thead style="background:#FFFBF7;">
                <tr>
                    <th class="px-5 py-3 text-left text-[11px] font-bold uppercase" style="color:#6b90aa;">Seller</th>
                    <th class="px-3 py-3 text-left text-[11px] font-bold uppercase" style="color:#6b90aa;">Products</th>
                    <th class="px-5 py-3 text-right text-[11px] font-bold uppercase" style="color:#6b90aa;">Review</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sellers as $s)
                    <tr style="border-top:1px solid #fff1ee;"
                        onmouseover="this.style.background='#FFF9F5';" onmouseout="this.style.background='';">
                        <td class="px-5 py-3">
                            <p class="font-semibold text-sm" style="color:#222222;">{{ $s->name }}</p>
                            <p class="text-xs" style="color:#6b90aa;">
                                {{ $s->sellerApplication?->line_of_business ?? 'No category' }}
                            </p>
                        </td>
                        <td class="px-3 py-3" style="color:#555555;">{{ $s->books_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.compliance.show', $s->id) }}"
                               class="text-xs font-semibold transition" style="color:#fa4e1c;"
                               onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
                                View →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-8 text-center text-sm" style="color:#6b90aa;">No sellers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent violations --}}
    <div class="card overflow-hidden">
        <div class="px-5 py-4" style="background:#e8f0f6;border-bottom:1px solid #cfdce8;">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Recent Violations & Actions</h2>
        </div>
        <div class="divide-y" style="--tw-divide-color:#fff1ee;">
            @forelse ($recentViolations as $v)
                @php $ac = $v->actionColor(); @endphp
                <div class="px-5 py-4" style="border-color:#fff1ee;">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-sm truncate" style="color:#222222;">{{ $v->seller->name }}</p>
                            <p class="text-xs mt-0.5" style="color:#6b90aa;">{{ $v->typeLabel() }}</p>
                            @if ($v->book)
                                <p class="text-xs" style="color:#6b90aa;">re: {{ $v->book->title }}</p>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-1 flex-shrink-0">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                                  style="background:{{ $ac['bg'] }};color:{{ $ac['text'] }};">
                                {{ $v->actionLabel() }}
                            </span>
                            <span class="text-[10px]" style="color:#B0B0B0;">{{ $v->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed" style="color:#7A7A7A;">{{ \Illuminate\Support\Str::limit($v->note, 80) }}</p>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm" style="color:#6b90aa;">No violations recorded yet.</div>
            @endforelse
        </div>
    </div>
</div>

</x-admin-layout>
