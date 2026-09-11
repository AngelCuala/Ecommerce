<x-admin-layout title="Seller Compliance Review" active="compliance">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

<div class="mb-6 flex items-center gap-4">
    <a href="{{ route('admin.compliance.index') }}"
       class="text-sm font-semibold transition" style="color:#fa4e1c;"
       onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
        ← Compliance Dashboard
    </a>
</div>

{{-- Seller header --}}
<div class="card mb-6 p-6 flex flex-wrap items-center gap-5">
    <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-full font-bold text-2xl text-white"
         style="background:#002b4d;">
        {{ strtoupper(substr($seller->name, 0, 1)) }}
    </div>
    <div class="flex-1 min-w-0">
        <h1 class="font-display text-xl font-bold" style="color:#222222;">{{ $seller->name }}</h1>
        <p class="text-sm" style="color:#6b90aa;">{{ $seller->email }}</p>
        <p class="text-xs mt-1" style="color:#fa4e1c;">
            Registered for: <strong>{{ $seller->sellerApplication?->line_of_business ?? 'Not specified' }}</strong>
            @if ($seller->sellerApplication?->shop_name)
                · Shop: <strong>{{ $seller->sellerApplication->shop_name }}</strong>
            @endif
        </p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <span class="rounded-full px-3 py-1 text-sm font-bold" style="background:#F0FDF4;color:#059669;">
            {{ ucfirst($seller->role) }}
        </span>
        <span class="rounded-full px-3 py-1 text-sm font-semibold" style="background:#e8f0f6;color:#fa4e1c;">
            {{ $seller->books->count() }} products
        </span>
        <span class="rounded-full px-3 py-1 text-sm font-semibold" style="background:#FEF2F2;color:#DC2626;">
            {{ $violations->count() }} violations
        </span>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-[1fr_380px]">

    {{-- Products --}}
    <div class="space-y-5">

        {{-- Flagged products --}}
        @if ($flaggedBooks->count())
            <div class="card overflow-hidden">
                <div class="flex items-center gap-2 px-5 py-4" style="background:#FFFBEB;border-bottom:2px solid #FDE68A;">
                    <span class="text-lg">⚠️</span>
                    <h2 class="font-display text-base font-bold" style="color:#92400E;">
                        {{ $flaggedBooks->count() }} Flagged Product{{ $flaggedBooks->count() > 1 ? 's' : '' }}
                    </h2>
                    <span class="text-xs ml-1" style="color:#B45309;">Category mismatch</span>
                </div>
                @foreach ($flaggedBooks as $book)
                    <div class="flex items-center gap-3 border-b px-5 py-3" style="border-color:#FEF9C3;">
                        <img src="{{ $book->image ? asset('storage/'.$book->image) : 'https://placehold.co/40x54/FF6300/fff?text=P' }}"
                             class="h-10 w-7 rounded object-cover flex-shrink-0" alt="{{ $book->title }}">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate" style="color:#222222;">{{ $book->title }}</p>
                            <p class="text-xs" style="color:#DC2626;">
                                Category: {{ $book->category->name ?? '—' }}
                                (expected: {{ $seller->sellerApplication?->line_of_business ?? '?' }})
                            </p>
                        </div>
                        <form action="{{ route('admin.compliance.store') }}" method="POST">
                            @csrf
                            <input type="hidden" name="seller_id" value="{{ $seller->id }}">
                            <input type="hidden" name="book_id" value="{{ $book->id }}">
                            <input type="hidden" name="type" value="wrong_category">
                            <input type="hidden" name="action" value="product_removed">
                            <input type="hidden" name="note" value="Product removed: category '{{ $book->category->name }}' does not match seller's registered line of business '{{ $seller->sellerApplication?->line_of_business }}'.">
                            <button type="submit"
                                    class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                    style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;"
                                    onmouseover="this.style.background='#DC2626';this.style.color='#fff';"
                                    onmouseout="this.style.background='#FEF2F2';this.style.color='#DC2626';"
                                    onclick="return confirm('Remove this product?')">
                                🗑 Remove
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- All products --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4" style="background:#e8f0f6;border-bottom:1px solid #cfdce8;">
                <h2 class="font-display text-base font-bold" style="color:#222222;">All Products ({{ $seller->books->count() }})</h2>
            </div>
            @forelse ($seller->books as $book)
                <div class="flex items-center gap-3 border-b px-5 py-3" style="border-color:#fff1ee;"
                     onmouseover="this.style.background='#FFF9F5';" onmouseout="this.style.background='';">
                    <img src="{{ $book->image ? asset('storage/'.$book->image) : 'https://placehold.co/40x54/FF6300/fff?text=P' }}"
                         class="h-10 w-7 rounded object-cover flex-shrink-0" alt="{{ $book->title }}">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate" style="color:#222222;">{{ $book->title }}</p>
                        <p class="text-xs" style="color:#6b90aa;">
                            {{ $book->category->name ?? '—' }} · ₱{{ number_format($book->price, 2) }} · Stock: {{ $book->stock }}
                        </p>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold flex-shrink-0"
                          style="background:{{ $book->stock > 0 ? '#ECFDF5' : '#FEF2F2' }};
                                 color:{{ $book->stock > 0 ? '#059669' : '#DC2626' }};">
                        {{ $book->stock > 0 ? 'In Stock' : 'Out of Stock' }}
                    </span>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm" style="color:#6b90aa;">No products listed.</p>
            @endforelse
        </div>
    </div>

    {{-- Right: Issue action + violation history --}}
    <div class="space-y-5">

        {{-- Issue warning / take action --}}
        <div class="card p-5">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Issue Warning or Take Action</h2>

            <form action="{{ route('admin.compliance.store') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="seller_id" value="{{ $seller->id }}">

                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Product (optional)</label>
                    <select name="book_id" class="input mt-1 text-sm">
                        <option value="">— No specific product —</option>
                        @foreach ($seller->books as $book)
                            <option value="{{ $book->id }}">{{ $book->title }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Violation Type *</label>
                    <select name="type" class="input mt-1 text-sm" required>
                        <option value="">Select type</option>
                        @foreach (\App\Models\SellerViolation::typeLabels() as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Action to Take *</label>
                    <select name="action" class="input mt-1 text-sm" required id="action-select"
                            onchange="updateActionBtn(this.value)">
                        <option value="">Select action</option>
                        @foreach (\App\Models\SellerViolation::actionLabels() as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Note / Reason *</label>
                    <textarea name="note" rows="4" class="input mt-1 text-sm" required
                              placeholder="Describe the violation and reason for this action…" maxlength="1000"></textarea>
                </div>

                <div id="action-warning" class="hidden rounded-xl border p-3 text-xs" style="background:#FEF2F2;border-color:#FECACA;color:#DC2626;">
                    ⚠️ This action will immediately affect the seller's account.
                </div>

                <button type="submit" id="action-btn"
                        class="w-full rounded-xl py-3 text-sm font-bold transition text-white"
                        style="background:#002b4d;"
                        onmouseover="this.style.opacity='.85';" onmouseout="this.style.opacity='1';"
                        onclick="return confirm('Apply this action to the seller?')">
                    Apply Action
                </button>
            </form>
        </div>

        {{-- Violation history --}}
        <div class="card overflow-hidden">
            <div class="px-5 py-4" style="background:#e8f0f6;border-bottom:1px solid #cfdce8;">
                <h2 class="font-display text-base font-bold" style="color:#222222;">
                    Violation History ({{ $violations->count() }})
                </h2>
            </div>
            <div class="divide-y" style="--tw-divide-color:#fff1ee;">
                @forelse ($violations as $v)
                    @php $ac = $v->actionColor(); @endphp
                    <div class="px-5 py-4" style="border-color:#fff1ee;">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-xs font-bold" style="color:#555555;">{{ $v->typeLabel() }}</span>
                                @if ($v->book)
                                    <span class="text-xs" style="color:#6b90aa;"> · {{ $v->book->title }}</span>
                                @endif
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold flex-shrink-0"
                                  style="background:{{ $ac['bg'] }};color:{{ $ac['text'] }};">
                                {{ $v->actionLabel() }}
                            </span>
                        </div>
                        <p class="mt-1.5 text-xs leading-relaxed" style="color:#7A7A7A;">{{ $v->note }}</p>
                        <p class="mt-1.5 text-[10px]" style="color:#B0B0B0;">
                            by {{ $v->admin->name ?? 'Admin' }} · {{ $v->created_at->format('M d, Y H:i') }}
                        </p>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm" style="color:#6b90aa;">No violations recorded.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
function updateActionBtn(val) {
    const btn = document.getElementById('action-btn');
    const warn = document.getElementById('action-warning');
    const dangerous = ['product_removed','account_suspended','account_deactivated'];
    warn.classList.toggle('hidden', !dangerous.includes(val));
    const colors = {
        'warning':             '#D97706',
        'product_removed':     '#fa4e1c',
        'account_suspended':   '#DC2626',
        'account_deactivated': '#6B7280',
    };
    btn.style.background = colors[val] || '#fa4e1c';
}
</script>

</x-admin-layout>
