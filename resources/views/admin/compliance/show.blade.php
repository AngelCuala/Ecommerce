<x-admin-layout title="Seller Compliance Review" active="compliance">
@include('partials.panel.plain')
@php
    $registered = $seller->sellerApplication?->line_of_business;
    $productCount = $seller->products->count();
    $violationCount = $violations->count();
    $actionTag = fn ($a) => ['warning' => 'pending', 'product_removed' => 'processing', 'account_deactivated' => 'neutral'][$a] ?? null;
@endphp

<div class="mx-auto max-w-6xl">

    @if (session('success'))
        <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#FCFBFA;border-color:#ECE7E3;color:#44403C;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Header --}}
    <a href="{{ route('admin.compliance.index') }}" class="text-sm hover:underline pl-muted">&larr; Compliance</a>
    <div class="mt-1 mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="font-display text-2xl font-bold" style="color:#222;">{{ $seller->name }}</h1>
            <span class="pl-tag neutral">{{ ucfirst($seller->role) }}</span>
        </div>
        <p class="mt-0.5 text-sm pl-muted">
            {{ $seller->email }}
            · Registered for: <span style="color:#44403C;">{{ $registered ?? 'Not specified' }}</span>
            @if ($seller->sellerApplication?->shop_name)
                · Shop: <span style="color:#44403C;">{{ $seller->sellerApplication->shop_name }}</span>
            @endif
            · {{ $productCount }} products
            · {{ $violationCount }} violations
        </p>
    </div>

    <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">

        {{-- Products --}}
        <div class="space-y-5 min-w-0">

            {{-- Flagged products --}}
            @if ($flaggedBooks->count())
                <section class="pl-card overflow-hidden">
                    <div class="pl-head">
                        <h2>Flagged products <span class="font-normal pl-muted">({{ $flaggedBooks->count() }})</span></h2>
                        <span class="text-xs pl-muted">Category doesn't match the seller's registered category</span>
                    </div>
                    <ul>
                        @foreach ($flaggedBooks as $book)
                            <li class="flex items-center gap-3 px-5 py-3" style="{{ $loop->last ? '' : 'border-bottom:1px solid #F2EEEA;' }}">
                                <img src="{{ $book->image ? asset('storage/'.$book->image) : 'https://placehold.co/40x54/FF6300/fff?text=P' }}"
                                     class="h-10 w-7 rounded object-cover flex-shrink-0" style="border:1px solid #ECE7E3;" alt="{{ $book->title }}">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate" style="color:#222;">{{ $book->title }}</p>
                                    <p class="text-xs" style="color:#B45309;">
                                        {{ $book->category->name ?? '—' }} — expected {{ $registered ?? '?' }}
                                    </p>
                                </div>
                                <form action="{{ route('admin.compliance.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="seller_id" value="{{ $seller->id }}">
                                    <input type="hidden" name="product_id" value="{{ $book->id }}">
                                    <input type="hidden" name="type" value="wrong_category">
                                    <input type="hidden" name="action" value="product_removed">
                                    <input type="hidden" name="note" value="Product removed: category '{{ $book->category?->name }}' does not match seller's registered line of business '{{ $seller->sellerApplication?->line_of_business }}'.">
                                    <button type="submit"
                                            class="rounded-md border px-3 py-1 text-xs font-semibold transition hover:bg-red-50"
                                            style="border-color:#FECACA;color:#B91C1C;background:#fff;"
                                            onclick="return confirm('Remove this product?')">
                                        Remove
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- All products --}}
            <section class="pl-card overflow-hidden">
                <div class="pl-head">
                    <h2>All products <span class="font-normal pl-muted">({{ $productCount }})</span></h2>
                </div>
                @if ($seller->products->isEmpty())
                    <p class="px-5 py-8 text-center text-sm pl-muted">No products listed.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="pl-table">
                            <thead>
                                <tr><th>Product</th><th>Category</th><th class="!text-right">Price</th><th class="!text-right">Stock</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($seller->products as $book)
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-3 min-w-0">
                                                <img src="{{ $book->image ? asset('storage/'.$book->image) : 'https://placehold.co/40x54/FF6300/fff?text=P' }}"
                                                     class="h-10 w-7 rounded object-cover flex-shrink-0" style="border:1px solid #ECE7E3;" alt="{{ $book->title }}">
                                                <span class="font-medium truncate max-w-[16rem]" style="color:#222;">{{ $book->title }}</span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap pl-muted">{{ $book->category->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap text-right">₱{{ number_format($book->price, 2) }}</td>
                                        <td class="text-right">{{ $book->stock }}</td>
                                        <td><span class="pl-tag {{ $book->stock > 0 ? 'delivered' : 'cancelled' }}">{{ $book->stock > 0 ? 'In stock' : 'Out of stock' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        {{-- Right: Issue action + violation history --}}
        <div class="space-y-5 min-w-0">

            {{-- Issue warning / take action --}}
            <section class="pl-card">
                <div class="pl-head"><h2>Issue warning or take action</h2></div>
                <form action="{{ route('admin.compliance.store') }}" method="POST" class="pl-body space-y-4">
                    @csrf
                    <input type="hidden" name="seller_id" value="{{ $seller->id }}">

                    <div>
                        <label class="pl-label">Product (optional)</label>
                        <select name="product_id" class="input text-sm">
                            <option value="">— No specific product —</option>
                            @foreach ($seller->products as $book)
                                <option value="{{ $book->id }}">{{ $book->title }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="pl-label">Violation type *</label>
                        <select name="type" class="input text-sm" required>
                            <option value="">Select type</option>
                            @foreach (\App\Models\SellerViolation::typeLabels() as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="pl-label">Action to take *</label>
                        <select name="action" class="input text-sm" required id="action-select"
                                onchange="updateActionBtn(this.value)">
                            <option value="">Select action</option>
                            @foreach (\App\Models\SellerViolation::actionLabels() as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="pl-label">Note / reason *</label>
                        <textarea name="note" rows="4" class="input text-sm" required
                                  placeholder="Describe the violation and reason for this action…" maxlength="1000"></textarea>
                    </div>

                    <div id="action-warning" class="hidden rounded-md border px-3 py-2 text-xs" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
                        This action will immediately affect the seller's account.
                    </div>

                    <button type="submit" id="action-btn" class="pl-btn w-full"
                            onclick="return confirm('Apply this action to the seller?')">
                        Apply action
                    </button>
                </form>
            </section>

            {{-- Violation history --}}
            <section class="pl-card overflow-hidden">
                <div class="pl-head">
                    <h2>Violation history <span class="font-normal pl-muted">({{ $violationCount }})</span></h2>
                </div>
                @forelse ($violations as $v)
                    @php $tag = $actionTag($v->action); @endphp
                    <div class="px-5 py-3" style="{{ $loop->last ? '' : 'border-bottom:1px solid #F2EEEA;' }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm min-w-0">
                                <span class="font-medium" style="color:#222;">{{ $v->typeLabel() }}</span>
                                @if ($v->product)
                                    <span class="pl-muted"> · {{ $v->product->title }}</span>
                                @endif
                            </p>
                            @if ($tag)
                                <span class="pl-tag {{ $tag }} flex-shrink-0">{{ $v->actionLabel() }}</span>
                            @else
                                <span class="pl-tag flex-shrink-0" style="{{ $v->action === 'account_suspended' ? 'background:#FEF2F2;color:#B91C1C;' : 'background:#F5F5F4;color:#57534E;' }}">{{ $v->actionLabel() }}</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs leading-relaxed" style="color:#44403C;">{{ $v->note }}</p>
                        <p class="mt-1 text-xs" style="color:#a39890;">
                            by {{ $v->admin->name ?? 'Admin' }} · {{ $v->created_at->format('M d, Y H:i') }}
                        </p>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm pl-muted">No violations recorded.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>

<script src="{{ asset('js/admin-compliance.js') }}"></script>

</x-admin-layout>
