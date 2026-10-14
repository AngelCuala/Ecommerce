<x-admin-layout title="Product Approvals" active="product-approvals">

<div class="mb-5">
    <p class="text-sm" style="color:#6b90aa;">
        New products from sellers stay hidden from the shop until you approve them. Editing an approved product does not need a new approval.
    </p>
</div>

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex gap-2">
        @foreach (['pending' => 'Waiting for approval', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('admin.product-approvals.index', ['status' => $key]) }}"
               class="rounded-full border px-4 py-1.5 text-sm font-semibold"
               style="{{ $status === $key ? 'background:#fa4e1c;border-color:#fa4e1c;color:#fff;' : 'border-color:#FFDCC2;color:#6b5a50;' }}">
                {{ $label }} <span class="ml-1 opacity-80">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </div>
    <form method="GET" class="flex items-center gap-2">
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Product, code or seller…"
               class="input w-56 py-2 text-sm" style="border-color:#FFDCC2;">
        <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">Search</button>
    </form>
</div>

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">{{ $errors->first() }}</div>
@endif

<div class="space-y-4">
    @forelse ($products as $product)
        @php
            $photos = collect([$product->image])->merge($product->images->pluck('path'))->filter()->unique()->values();
        @endphp
        <div class="card p-5">
            <div class="flex flex-col gap-5 md:flex-row">
                <div class="shrink-0">
                    @if ($photos->isNotEmpty())
                        <a href="{{ asset('storage/' . $photos->first()) }}" target="_blank" rel="noopener">
                            <img src="{{ asset('storage/' . $photos->first()) }}" alt="" class="h-36 w-36 rounded-xl border object-cover" style="border-color:#F0E4DC;">
                        </a>
                    @else
                        <div class="flex h-36 w-36 items-center justify-center rounded-xl border text-xs" style="border-color:#F0E4DC;color:#b5a79d;">No photo</div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-display text-lg font-bold" style="color:#222;">{{ $product->title }}</p>
                            <p class="text-xs" style="color:#6b90aa;">
                                {{ $product->product_code ?: 'No code' }} ·
                                {{ $product->category->name ?? 'No category' }}{{ $product->subcategory ? ' › ' . $product->subcategory : '' }}
                            </p>
                        </div>
                        <p class="text-right text-xs" style="color:#6b90aa;">
                            Submitted {{ $product->created_at->format('M d, Y g:i A') }}
                        </p>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
                        <p><span style="color:#6b90aa;">Seller:</span> <span class="font-semibold">{{ $product->seller->name ?? '—' }}</span></p>
                        <p><span style="color:#6b90aa;">Price:</span> <span class="font-semibold">₱{{ number_format($product->price, 2) }}</span>
                            @if ($product->sale_price) <span style="color:#fa4e1c;">(sale ₱{{ number_format($product->sale_price, 2) }})</span> @endif</p>
                        <p><span style="color:#6b90aa;">Stock:</span> <span class="font-semibold">{{ $product->stock }}</span></p>
                        <p><span style="color:#6b90aa;">Photos:</span> <span class="font-semibold">{{ $photos->count() }}</span></p>
                        @if ($product->brand)<p><span style="color:#6b90aa;">Brand:</span> {{ $product->brand }}</p>@endif
                        @if ($product->sku)<p><span style="color:#6b90aa;">SKU:</span> {{ $product->sku }}</p>@endif
                        @if ($product->weight_kg)<p><span style="color:#6b90aa;">Weight:</span> {{ $product->weight_kg }} kg</p>@endif
                    </div>

                    @if ($photos->count() > 1)
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($photos->slice(1) as $photo)
                                <a href="{{ asset('storage/' . $photo) }}" target="_blank" rel="noopener">
                                    <img src="{{ asset('storage/' . $photo) }}" alt="" class="h-14 w-14 rounded-lg border object-cover" style="border-color:#F0E4DC;">
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($product->description)
                        <details class="mt-3 text-sm" style="color:#4a3f38;">
                            <summary class="cursor-pointer">{{ \Illuminate\Support\Str::limit(strip_tags($product->description), 200) }}</summary>
                            <p class="mt-2 whitespace-pre-line">{{ strip_tags($product->description) }}</p>
                        </details>
                    @endif

                    @if ($status === 'rejected')
                        <div class="mt-3 rounded-lg border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#B91C1C;">
                            <strong>Rejected</strong>
                            {{ $product->approval_reviewed_at?->format('M d, Y') }}{{ $product->approvalReviewer ? ' by ' . $product->approvalReviewer->name : '' }}:
                            {{ $product->approval_note }}
                            <span class="block text-xs opacity-80">It comes back here when the seller edits and saves it.</span>
                        </div>
                    @else
                        <div class="mt-4 grid gap-3 border-t pt-4 md:grid-cols-[auto,1fr]" style="border-color:#F3E8E0;">
                            <form method="POST" action="{{ route('admin.product-approvals.approve', $product) }}">
                                @csrf @method('PATCH')
                                <button class="btn-gold w-full !py-2 text-sm md:w-auto" style="background:#16a34a;border-color:#16a34a;color:#fff;">✓ Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.product-approvals.reject', $product) }}" class="flex flex-col gap-2 sm:flex-row">
                                @csrf @method('PATCH')
                                <input type="text" name="approval_note" maxlength="500" required
                                       placeholder="Reason for rejecting (shown to the seller)…"
                                       class="input flex-1 py-2 text-sm" style="border-color:#FFDCC2;">
                                <button class="btn-outline !py-2 text-sm" style="border-color:#DC2626;color:#DC2626;">Reject</button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="card p-10 text-center text-sm" style="color:#6b90aa;">
            {{ $status === 'pending' ? 'No products are waiting for approval.' : 'No rejected products.' }}
        </div>
    @endforelse
</div>

<div class="mt-5">{{ $products->links() }}</div>

</x-admin-layout>
