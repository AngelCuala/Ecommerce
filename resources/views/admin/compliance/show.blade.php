<x-admin-layout title="Seller Compliance Review" active="compliance">
@include('partials.panel.plain')
@php
    $app = $seller->sellerApplication;
    $registered = $app?->line_of_business;
    $productCount = $seller->products->count();
    $violationCount = $violations->count();
    $flaggedIds = $flaggedBooks->pluck('id')->all();
    $actionTag = fn ($a) => ['warning' => 'pending', 'product_removed' => 'processing', 'account_deactivated' => 'neutral'][$a] ?? null;
    $dot = fn ($a) => match ($a) {
        'account_suspended', 'account_deactivated' => '#DC2626',
        'product_removed'                          => '#EA580C',
        default                                    => '#F59E0B',
    };
    $statusLabel = match ($seller->role) {
        'suspended'   => ['Suspended', '#FEF2F2', '#B91C1C'],
        'deactivated' => ['Deactivated', '#F3F4F6', '#4B5563'],
        default       => ['Active', '#ECFDF5', '#047857'],
    };
    $img = fn ($book) => $book->image ? asset('storage/' . $book->image) : null;
@endphp
<style>
    .cp-stat { padding: 12px 14px; border-radius: 12px; background: #FAFAF9; border: 1px solid #F2EEEA; }
    .cp-stat p:first-child { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #8a7f78; }
    .cp-stat p:last-child { margin-top: 2px; font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 22px; font-weight: 800; color: #002b4d; }
    .cp-chip { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .cp-thumb { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; flex-shrink: 0; border: 1px solid #ECE7E3; background: #FAFAF9; }
    .cp-thumb-empty { display: flex; align-items: center; justify-content: center; color: #b3a79f; }
    .cp-products th, .cp-products td { padding-left: .9rem; padding-right: .9rem; }
    .cp-products td { vertical-align: middle; }
    .cp-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; line-height: 1.35; }
    .cp-step { display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 999px; background: #FFF1EE; color: #fa4e1c; font-size: 11px; font-weight: 800; flex-shrink: 0; }
</style>

<div class="mx-auto max-w-6xl">

    <a href="{{ route('admin.compliance.index') }}"
       class="mb-4 inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold transition hover:bg-white"
       style="border-color:#E7E5E4;color:#57534E;">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
        Compliance
    </a>

    {{-- Seller header --}}
    <section class="pl-card mb-5 overflow-hidden" data-compliance-head>
        <div class="h-1.5" style="background:linear-gradient(90deg,#fa4e1c,#fb7048 45%,#002b4d);"></div>
        <div class="flex flex-wrap items-center gap-4 px-5 pt-5">
            @if ($app?->logo_path)
                <img src="{{ asset('storage/' . $app->logo_path) }}" alt="" class="h-14 w-14 shrink-0 rounded-2xl object-cover" style="border:1px solid #ECE7E3;">
            @else
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl font-display text-2xl font-bold" style="background:#FFF1EE;color:#fa4e1c;">{{ strtoupper(mb_substr($app?->shop_name ?: $seller->name, 0, 1)) }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <h1 class="truncate font-display text-2xl font-bold" style="color:#002b4d;">{{ $app?->shop_name ?: $seller->name }}</h1>
                <p class="mt-0.5 truncate text-sm" style="color:#57534E;">
                    @if ($app?->shop_name)Owned by <strong style="color:#222;">{{ $seller->name }}</strong> · @endif
                    <a href="mailto:{{ $seller->email }}" class="hover:underline" style="color:#6b90aa;">{{ $seller->email }}</a>
                </p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span class="cp-chip" style="background:#FFF7ED;color:#C2410C;">Registered for: {{ $registered ?? 'Not specified' }}</span>
                    <span class="cp-chip" style="background:{{ $statusLabel[1] }};color:{{ $statusLabel[2] }};">{{ $statusLabel[0] }}</span>
                </div>
            </div>
            <a href="{{ route('admin.sellers.show', $seller->id) }}" class="pl-btn-ghost inline-flex items-center rounded-md border px-4 py-2 text-sm font-semibold" style="border-color:#E7E5E4;">View seller</a>
        </div>
        <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4">
            <div class="cp-stat"><p>Products</p><p data-stat="products">{{ $productCount }}</p></div>
            <div class="cp-stat" @if ($flaggedBooks->count()) style="background:#FFFBEB;border-color:#FDE68A;" @endif><p>Flagged</p><p data-stat="flagged" @if ($flaggedBooks->count()) style="color:#B45309;" @endif>{{ $flaggedBooks->count() }}</p></div>
            <div class="cp-stat" @if ($violationCount) style="background:#FEF2F2;border-color:#FECACA;" @endif><p>Violations</p><p data-stat="violations" @if ($violationCount) style="color:#B91C1C;" @endif>{{ $violationCount }}</p></div>
            <div class="cp-stat"><p>Last violation</p><p style="font-size:15px;padding-top:5px;">{{ $violations->first()?->created_at?->format('M j, Y') ?? 'None' }}</p></div>
        </div>
    </section>

    <div class="grid gap-5 lg:grid-cols-[1fr_22rem]">

        {{-- Products --}}
        <div class="space-y-5 min-w-0">

            {{-- Flagged products --}}
            @if ($flaggedBooks->count())
                <section class="pl-card overflow-hidden" style="border-color:#FDE68A;">
                    <div class="flex items-start gap-3 px-5 py-4" style="background:#FFFBEB;border-bottom:1px solid #FDE68A;">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full" style="background:#FEF3C7;color:#B45309;">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                        </span>
                        <div>
                            <h2 class="font-display text-base font-bold" style="color:#92400E;">Flagged products ({{ $flaggedBooks->count() }})</h2>
                            <p class="text-xs" style="color:#B45309;">Their category doesn't match the shop's registered business.</p>
                        </div>
                    </div>
                    <ul>
                        @foreach ($flaggedBooks as $book)
                            <li class="flex flex-wrap items-center gap-3 px-5 py-3.5" style="{{ $loop->last ? '' : 'border-bottom:1px solid #F2EEEA;' }}" data-flagged="{{ $book->id }}">
                                @if ($img($book))<img src="{{ $img($book) }}" class="cp-thumb" alt="">@else<span class="cp-thumb cp-thumb-empty">?</span>@endif
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold" style="color:#222;">{{ $book->title }}</p>
                                    <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="cp-chip" style="background:#FEF2F2;color:#B91C1C;">{{ $book->category->name ?? '—' }}</span>
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="#a39890" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg>
                                        <span class="cp-chip" style="background:#ECFDF5;color:#047857;">should be {{ $registered ?? '?' }}</span>
                                    </p>
                                </div>
                                <form action="{{ route('admin.compliance.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="seller_id" value="{{ $seller->id }}">
                                    <input type="hidden" name="product_id" value="{{ $book->id }}">
                                    <input type="hidden" name="type" value="wrong_category">
                                    <input type="hidden" name="action" value="warning">
                                    <input type="hidden" name="note" value="Wrong category: '{{ $book->title }}' is listed under '{{ $book->category?->name }}', but the shop is registered for '{{ $app?->line_of_business }}'. Please move it to the right category or unlist it.">
                                    <button type="submit"
                                            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border px-3 py-1.5 text-xs font-bold transition hover:bg-amber-50"
                                            style="border-color:#FCD34D;color:#B45309;background:#fff;"
                                            onclick="return confirm('Send this seller a wrong-category warning?')">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                        Warn seller
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
                    @if ($flaggedBooks->count())<span class="text-xs pl-muted">Flagged rows are highlighted</span>@endif
                </div>
                @if ($seller->products->isEmpty())
                    <p class="px-5 py-10 text-center text-sm pl-muted">This seller has no products yet.</p>
                @else
                    {{-- Fits the card (no sideways scroll): names wrap to two lines, stock and status share a column. --}}
                    <table class="pl-table cp-products">
                        <thead>
                            <tr><th>Product</th><th>Category</th><th class="!text-right">Price</th><th class="!text-right">Stock</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($seller->products as $book)
                                @php $flagged = in_array($book->id, $flaggedIds, true); @endphp
                                <tr @if ($flagged) style="background:#FFFBEB;" @endif>
                                    <td>
                                        <div class="flex min-w-0 items-center gap-3">
                                            @if ($img($book))<img src="{{ $img($book) }}" class="cp-thumb" alt="">@else<span class="cp-thumb cp-thumb-empty">?</span>@endif
                                            <span class="cp-name font-medium" style="color:#222;" title="{{ $book->title }}">{{ $book->title }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($flagged)
                                            <span class="cp-chip" style="background:#FEF2F2;color:#B91C1C;white-space:normal;" title="Doesn't match {{ $registered }}">{{ $book->category->name ?? '—' }}</span>
                                        @else
                                            <span class="pl-muted">{{ $book->category->name ?? '—' }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-right">₱{{ number_format($book->price, 2) }}</td>
                                    <td class="text-right">
                                        <span class="block font-semibold" style="color:#222;">{{ $book->stock }}</span>
                                        <span class="pl-tag {{ $book->stock > 0 ? 'delivered' : 'cancelled' }} mt-0.5 inline-block whitespace-nowrap" style="font-size:10.5px;">{{ $book->stock > 0 ? 'In stock' : 'Sold out' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>

        {{-- Right: Issue action + violation history --}}
        <div class="space-y-5 min-w-0">

            {{-- Issue warning / take action --}}
            <section class="pl-card">
                <div class="pl-head">
                    <h2>Issue warning or take action</h2>
                </div>
                <form action="{{ route('admin.compliance.store') }}" method="POST" class="pl-body space-y-4">
                    @csrf
                    <input type="hidden" name="seller_id" value="{{ $seller->id }}">

                    <div>
                        <label class="pl-label" style="display:flex;align-items:center;gap:8px;"><span class="cp-step">1</span> Product <span class="font-normal pl-muted">(optional)</span></label>
                        <select name="product_id" class="input text-sm">
                            <option value="">— No specific product —</option>
                            @foreach ($seller->products as $book)
                                <option value="{{ $book->id }}">{{ $book->title }}{{ in_array($book->id, $flaggedIds, true) ? ' · flagged' : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="pl-label" style="display:flex;align-items:center;gap:8px;"><span class="cp-step">2</span> Violation type *</label>
                        <select name="type" class="input text-sm" required>
                            <option value="">Select type</option>
                            @foreach (\App\Models\SellerViolation::typeLabels() as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="pl-label" style="display:flex;align-items:center;gap:8px;"><span class="cp-step">3</span> Action to take *</label>
                        <select name="action" class="input text-sm" required id="action-select"
                                onchange="updateActionBtn(this.value)">
                            <option value="">Select action</option>
                            @foreach (\App\Models\SellerViolation::adminActionLabels() as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs pl-muted">A warning only records it. Suspending or deactivating locks the seller out.</p>
                    </div>

                    <div>
                        <label class="pl-label" style="display:flex;align-items:center;gap:8px;"><span class="cp-step">4</span> Note / reason *</label>
                        <textarea name="note" rows="4" class="input text-sm" required
                                  placeholder="Describe the violation and reason for this action…" maxlength="1000"></textarea>
                    </div>

                    <div id="action-warning" class="hidden flex items-start gap-2 rounded-lg border px-3 py-2.5 text-xs" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                        <span>This action takes effect immediately: the seller is signed out and can't use their account.</span>
                    </div>

                    <button type="submit" id="action-btn" class="pl-btn w-full"
                            onclick="return confirm('Apply this action to the seller?')">
                        Apply action
                    </button>
                </form>
            </section>

            {{-- Violation history (timeline) --}}
            <section class="pl-card overflow-hidden">
                <div class="pl-head">
                    <h2>Violation history <span class="font-normal pl-muted">({{ $violationCount }})</span></h2>
                </div>
                @forelse ($violations as $v)
                    @php $tag = $actionTag($v->action); @endphp
                    <div class="relative flex gap-3 px-5 py-3.5" data-violation>
                        <div class="relative flex flex-col items-center">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" style="background:{{ $dot($v->action) }};box-shadow:0 0 0 3px #fff, 0 0 0 4px {{ $dot($v->action) }}33;"></span>
                            @unless ($loop->last)<span class="mt-1 w-px flex-1" style="background:#ECE7E3;"></span>@endunless
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 text-sm">
                                    <span class="font-semibold" style="color:#222;">{{ $v->typeLabel() }}</span>
                                    @if ($v->product)<span class="pl-muted"> · {{ $v->product->title }}</span>@endif
                                </p>
                                @if ($tag)
                                    <span class="pl-tag {{ $tag }} flex-shrink-0">{{ $v->actionLabel() }}</span>
                                @else
                                    <span class="pl-tag flex-shrink-0" style="{{ $v->action === 'account_suspended' ? 'background:#FEF2F2;color:#B91C1C;' : 'background:#F5F5F4;color:#57534E;' }}">{{ $v->actionLabel() }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs leading-relaxed" style="color:#44403C;">{{ $v->note }}</p>
                            <p class="mt-1 text-xs" style="color:#a39890;">by {{ $v->admin->name ?? 'Admin' }} · {{ $v->created_at->tz(config('app.business_timezone'))->format('M j, Y g:i A') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center">
                        <span class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-full" style="background:#ECFDF5;color:#047857;">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <p class="text-sm font-semibold" style="color:#222;">Clean record</p>
                        <p class="text-xs pl-muted">No violations recorded for this seller.</p>
                    </div>
                @endforelse
            </section>
        </div>
    </div>
</div>

<script src="{{ asset('js/admin-compliance.js') }}"></script>

</x-admin-layout>
