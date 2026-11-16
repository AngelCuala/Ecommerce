<x-seller-layout title="Products" active="products">

@include('partials.page-tabs', ['tabs' => [['Products', 'seller.books.index', 'seller.books.index'], ['Store categories', 'seller.store-categories.index', 'seller.store-categories.*']]])
@php
    $statuses = [
        'active'       => ['label' => 'Active',       'count' => $counts['active']],
        'draft'        => ['label' => 'Drafts',       'count' => $counts['draft'] ?? 0],
        'low_stock'    => ['label' => 'Low stock',    'count' => $counts['low_stock']],
        'out_of_stock' => ['label' => 'Out of stock', 'count' => $counts['out_of_stock']],
        'review'       => ['label' => 'For approval', 'count' => $counts['review']],
        'archived'     => ['label' => 'Inactive',     'count' => $counts['archived']],
    ];
    $listingStyle = [
        'active'   => ['Active',   'background:#ECFDF5;color:#047857;'],
        'draft'    => ['Draft',    'background:#F5F5F4;color:#57534E;'],
        'inactive' => ['Inactive', 'background:#F5F5F4;color:#57534E;'],
    ];
@endphp
<style>
    .inv-tab { position: relative; padding: .6rem .25rem; margin-right: 1.25rem; font-size: .875rem; font-weight: 600; color: #8a7f78; transition: color .15s; white-space: nowrap; }
    .inv-tab:hover { color: #222; }
    .inv-tab.is-active { color: #222; }
    .inv-tab.is-active::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: #fa4e1c; border-radius: 2px; }
    .inv-tab .tab-count { margin-left: .35rem; font-size: .75rem; color: #b3a79f; }
    .inv-tab.is-active .tab-count { color: #fa4e1c; }
    .inv-table th { padding: .65rem 1rem; text-align: left; font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; border-bottom: 1px solid #ECE7E3; background: #FCFBFA; }
    .inv-table td { padding: .8rem 1rem; border-bottom: 1px solid #F2EEEA; vertical-align: middle; }
    .inv-table tbody tr:last-child td { border-bottom: 0; }
    .inv-table tbody tr:hover { background: #FCFBFA; }
    .inv-action { font-size: .8125rem; font-weight: 600; color: #57534E; }
    .inv-action:hover { color: #222; text-decoration: underline; }

    /* The table fits the page: no sideways scrolling. */
    .inv-table { table-layout: auto; }
    .inv-table th, .inv-table td { padding-left: .75rem; padding-right: .75rem; }
    .inv-cat { max-width: 9.5rem; color: #57534E; }
    .inv-edit { display: inline-flex; align-items: center; border: 1px solid #E7E5E4; border-radius: .5rem; padding: .3rem .7rem; font-size: .8125rem; font-weight: 600; color: #44403C; background: #fff; }
    .inv-edit:hover { border-color: #fa4e1c; color: #fa4e1c; }
    .inv-more { position: relative; display: inline-block; }
    .inv-more > summary { display: inline-flex; align-items: center; gap: .2rem; cursor: pointer; list-style: none; border: 1px solid #E7E5E4; border-radius: .5rem; padding: .3rem .55rem; font-size: .8125rem; font-weight: 600; color: #57534E; background: #fff; }
    .inv-more > summary::-webkit-details-marker { display: none; }
    .inv-more > summary:hover, .inv-more[open] > summary { border-color: #D6CFC9; color: #222; }
    .inv-menu { position: absolute; right: 0; top: calc(100% + 4px); z-index: 30; min-width: 9rem; padding: .3rem; border-radius: .7rem; background: #fff; border: 1px solid #ECE7E3; box-shadow: 0 14px 30px -12px rgba(0,0,0,.3); text-align: left; }
    .inv-menu form { display: block; }
    .inv-menu button { display: block; width: 100%; border-radius: .45rem; padding: .45rem .6rem; text-align: left; font-size: .8125rem; font-weight: 600; color: #44403C; }
    .inv-menu button:hover { background: #F5F2EF; }
    .inv-menu button.danger { color: #B91C1C; }
    .inv-menu button.danger:hover { background: #FEF2F2; }

    /* Narrower screens: each product is a card with labelled lines. */
    @media (max-width: 1100px) {
        .inv-table thead { display: none; }
        .inv-table, .inv-table tbody, .inv-table tr, .inv-table td { display: block; width: 100%; }
        .inv-table tr { position: relative; padding: .9rem 1rem .9rem 2.6rem; border-bottom: 1px solid #F2EEEA; }
        .inv-table tbody tr:last-child { border-bottom: 0; }
        .inv-table td { padding: .2rem 0; border: 0 !important; text-align: left !important; }
        .inv-table td.inv-check { position: absolute; left: 1rem; top: 1.1rem; width: auto; padding: 0; }
        .inv-table td[data-label] { display: flex; align-items: center; gap: .75rem; font-size: .8125rem; }
        .inv-table td[data-label]::before { content: attr(data-label); flex: none; width: 4.5rem; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #a39890; }
        .inv-table td[data-label] > * { min-width: 0; }
        .inv-cat { max-width: none; }
        .inv-table td.inv-actions { padding-top: .6rem; }
        .inv-table td.inv-actions > div { justify-content: flex-start; }
        .inv-menu { left: 0; right: auto; }
        .inv-table td[colspan] { padding: 2rem 0; }
    }
</style>

<div class="mx-auto max-w-6xl">

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222;">Inventory</h1>
            <p class="mt-0.5 text-sm" style="color:#8a7f78;">Manage your listings, prices and stock.</p>
        </div>
        <a href="{{ route('seller.books.create') }}"
           class="inline-flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
           style="background:#fa4e1c;">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Add product
        </a>
    </div>

    @if (session('warning'))
        <div class="mb-4 rounded-md border px-4 py-3 text-sm" style="background:#FFFBEB;border-color:#FDE68A;color:#92400E;" role="alert">{{ session('warning') }}</div>
    @endif
    @unless (auth()->user()->isApproved() || auth()->user()->isAdmin())
        <div class="mb-4 rounded-md border px-4 py-3 text-sm" style="background:#FFFBEB;border-color:#FDE68A;color:#92400E;">Your store is waiting for verification. You can prepare products as drafts; publishing opens once ALVY approves your store.</div>
    @endunless
    @if ($errors->any())
        <div class="mb-4 rounded-md border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Tabs + search --}}
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3 border-b" style="border-color:#ECE7E3;">
        <nav class="flex flex-wrap">
            @foreach ($statuses as $key => $tab)
                <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
                   class="inv-tab {{ $status === $key ? 'is-active' : '' }}">
                    {{ $tab['label'] }}<span class="tab-count">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- Filters: all combine; Reset keeps only the tab --}}
    @php $filtered = request()->hasAny(['search', 'category_id', 'min_price', 'max_price']) || request('sort', 'newest') !== 'newest'; @endphp
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border bg-white px-4 py-3 text-sm" style="border-color:#ECE7E3;" data-inventory-filters>
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="relative">
            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or SKU" aria-label="Search"
                   class="input !py-1.5 !pl-8 text-sm" style="width:14rem;">
        </div>
        <select name="category_id" class="input !py-1.5 text-sm" style="width:auto;" aria-label="Category">
            <option value="">All categories</option>
            @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected((string) request('category_id') === (string) $id)>{{ $name }}</option>@endforeach
        </select>
        <input type="number" name="min_price" value="{{ request('min_price') }}" min="0" step="0.01" placeholder="₱ Min" aria-label="Minimum price" class="input !py-1.5 text-sm" style="width:6.5rem;">
        <input type="number" name="max_price" value="{{ request('max_price') }}" min="0" step="0.01" placeholder="₱ Max" aria-label="Maximum price" class="input !py-1.5 text-sm" style="width:6.5rem;">
        <select name="sort" class="input !py-1.5 text-sm" style="width:auto;" aria-label="Sort">
            @foreach (\App\Http\Controllers\Seller\BookController::SORTS as $key => $label)<option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>@endforeach
        </select>
        <button class="rounded-md px-3 py-1.5 font-semibold text-white" style="background:#fa4e1c;">Apply</button>
        @if ($filtered)
            <a href="{{ route('seller.books.index', ['status' => $status]) }}" class="rounded-md border px-3 py-1.5 font-semibold" style="border-color:#E7E5E4;color:#57534E;">Reset</a>
        @endif
        <span class="ml-auto self-center text-xs" style="color:#8a7f78;">{{ $books->total() }} {{ \Illuminate\Support\Str::plural('product', $books->total()) }}</span>
    </form>

    {{-- Bulk actions on the ticked products --}}
    <form method="POST" action="{{ route('seller.books.bulk') }}" id="bulk-form" class="mb-2 flex flex-wrap items-center gap-2 text-sm"
          onsubmit="return bulkCheck(this);">
        @csrf
        <select name="action" class="rounded-md border px-2 py-1.5 text-sm" style="border-color:#E7E5E4;" aria-label="Bulk action">
            <option value="">Bulk action…</option>
            <option value="deactivate">Deactivate</option>
            <option value="activate">Reactivate</option>
            <option value="delete">Delete</option>
        </select>
        <button class="rounded-md border px-3 py-1.5 font-semibold" style="border-color:#E7E5E4;color:#44403C;">Apply to selected</button>
        <span class="hidden text-xs font-semibold" style="color:#B91C1C;" data-bulk-msg></span>
    </form>
    <script>
    // Say what's missing instead of doing nothing: an action and at least one ticked product.
    function bulkCheck(form) {
        var msg = form.querySelector('[data-bulk-msg]');
        var action = form.querySelector('select').value;
        var ticked = document.querySelectorAll('[data-bulk]:checked').length;
        var problem = !action ? 'Choose an action first (Deactivate, Reactivate or Delete).'
            : !ticked ? 'Tick at least one product.' : '';
        msg.textContent = problem;
        msg.classList.toggle('hidden', !problem);
        if (problem) return false;
        return action !== 'delete' || confirm('Delete the ' + ticked + ' selected product(s)? Products with unfinished orders are skipped.');
    }
    </script>

    {{-- Products --}}
    <div class="overflow-hidden rounded-xl border bg-white" style="border-color:#ECE7E3;">
        <div>
            <table class="inv-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="!pr-0"><input type="checkbox" aria-label="Select all" onclick="document.querySelectorAll('[data-bulk]').forEach(c => c.checked = this.checked)"></th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Listing</th>
                        <th class="!text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($books as $book)
                        <tr>
                            <td class="inv-check !pr-0"><input type="checkbox" name="ids[]" value="{{ $book->id }}" form="bulk-form" data-bulk aria-label="Select {{ $book->title }}"></td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($book->image)
                                        <img src="{{ asset('storage/' . $book->image) }}" alt=""
                                             class="h-11 w-11 shrink-0 rounded-md border object-cover" style="border-color:#ECE7E3;">
                                    @else
                                        <span class="h-11 w-11 shrink-0 rounded-md" style="background:#F5F2EF;"></span>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('seller.books.edit', $book->id) }}" class="block max-w-[16rem] truncate font-medium hover:underline" style="color:#222;">{{ $book->title }}</a>
                                        <p class="max-w-[16rem] truncate text-xs" style="color:#8a7f78;">
                                            {{ collect([
                                                $book->product_code,
                                                $book->brand ?: $book->author,
                                                $book->sku ? 'SKU ' . $book->sku : null,
                                                $book->voucher_code ? 'Voucher ' . $book->voucher_code : null,
                                            ])->filter()->implode(' · ') ?: '—' }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="inv-cat" data-label="Category">{{ $book->category->name ?? '—' }}</td>
                            <td class="whitespace-nowrap" data-label="Price">
                                <span class="font-medium" style="color:#222;">₱{{ number_format($book->effective_price, 2) }}</span>
                                @if ($book->hasDiscount())
                                    <span class="block text-xs" style="color:#8a7f78;">
                                        <span class="line-through">₱{{ number_format($book->price, 2) }}</span>
                                        @if ($book->discount_percent > 0) · {{ rtrim(rtrim(number_format($book->discount_percent, 2), '0'), '.') }}% off @endif
                                    </span>
                                @endif
                                @if ($sale = $book->saleState())
                                    <span class="mt-0.5 block text-[11px] font-semibold" style="color:{{ $sale === 'Ongoing' ? '#C2410C' : '#8a7f78' }};">Sale: {{ $sale }}</span>
                                    @if (in_array($sale, ['Upcoming', 'Ongoing'], true))
                                        <form action="{{ route('seller.books.end-sale', $book->id) }}" method="POST" class="inline">@csrf @method('PATCH')
                                            <button class="text-[11px] font-semibold underline" style="color:#B91C1C;">End now</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                            <td data-label="Stock">
                                <div>
                                <form action="{{ route('seller.books.stock', $book->id) }}" method="POST" class="flex items-center gap-1.5">
                                    @csrf @method('PATCH')
                                    <input type="number" name="stock" value="{{ $book->stock }}" min="0" max="99999" step="1" @disabled($book->variations()->exists()) aria-label="Stock for {{ $book->title }}"
                                           class="w-16 rounded-md border px-2 py-1 text-center text-sm focus:outline-none focus:ring-2 focus:ring-orange-200"
                                           style="border-color:{{ $book->stock === 0 ? '#FCA5A5' : ($book->isLowStock() ? '#FCD34D' : '#E7E5E4') }};color:#222;">
                                    <button type="submit" class="rounded-md border px-2 py-1 text-xs font-semibold transition hover:bg-stone-50" style="border-color:#E7E5E4;color:#57534E;">Save</button>
                                </form>
                                @if ($book->stock === 0)
                                    <p class="mt-1 text-xs font-semibold" style="color:#B91C1C;" data-out-of-stock>Out of Stock</p>
                                @elseif ($book->isLowStock())
                                    <p class="mt-1 text-xs" style="color:#B45309;">Low stock (alert at {{ $book->lowStockThreshold() }})</p>
                                @endif
                                </div>
                            </td>
                            <td data-label="Listing">
                                <div>
                                @php [$listLabel, $listCss] = $book->is_archived
                                    ? ['Inactive', 'background:#F5F5F4;color:#78716C;']
                                    : ($listingStyle[$book->status] ?? [ucfirst((string) $book->status ?: 'Active'), 'background:#ECFDF5;color:#047857;']); @endphp
                                <span class="rounded-md px-2 py-0.5 text-xs font-semibold" style="{{ $listCss }}">{{ $listLabel }}</span>
                                @if (! $book->is_archived && $book->status !== 'draft')
                                    @if ($book->isPendingApproval())
                                        <span class="mt-1 block w-max rounded-md px-2 py-0.5 text-xs font-semibold" style="background:#FFFBEB;color:#B45309;">Pending approval</span>
                                    @elseif ($book->isRejected())
                                        <span class="mt-1 block w-max rounded-md px-2 py-0.5 text-xs font-semibold" style="background:#FEF2F2;color:#B91C1C;">Rejected</span>
                                        @if ($book->approval_note)
                                            <p class="mt-1 max-w-[220px] text-xs" style="color:#B91C1C;">{{ $book->approval_note }} — edit and save to resubmit.</p>
                                        @endif
                                    @endif
                                @endif
                                </div>
                            </td>
                            <td class="inv-actions text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                <a href="{{ route('seller.books.edit', $book->id) }}" class="inv-edit">Edit</a>
                                <details class="inv-more" data-inv-more>
                                    <summary aria-label="More actions for {{ $book->title }}">More
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                                    </summary>
                                    <div class="inv-menu">
                                        @if ($book->is_archived)
                                            <form action="{{ route('seller.books.unarchive', $book->id) }}" method="POST">
                                                @csrf @method('PATCH')
                                                <button>Reactivate</button>
                                            </form>
                                        @else
                                            <form action="{{ route('seller.books.archive', $book->id) }}" method="POST"
                                                  onsubmit="return confirm('Deactivate this product? Buyers won\'t see it until you reactivate it.')">
                                                @csrf @method('PATCH')
                                                <button>Deactivate</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('seller.books.duplicate', $book->id) }}" method="POST">
                                            @csrf
                                            <button>Duplicate</button>
                                        </form>
                                        <form action="{{ route('seller.books.destroy', $book->id) }}" method="POST"
                                              onsubmit="return confirm('Permanently delete this product? This can\'t be undone.')">
                                            @csrf @method('DELETE')
                                            <button class="danger">Delete</button>
                                        </form>
                                    </div>
                                </details>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-14 text-center">
                                <p class="font-medium" style="color:#222;">
                                    @if (request('search')) No products match "{{ request('search') }}"
                                    @elseif ($filtered) No products match these filters
                                    @elseif ($status === 'archived') No inactive products
                                    @elseif ($status === 'review') Nothing waiting for approval
                                    @elseif ($status === 'draft') No drafts
                                    @elseif ($status === 'low_stock') Nothing is running low
                                    @elseif ($status === 'out_of_stock') Everything is in stock
                                    @else No products yet @endif
                                </p>
                                @if ($status === 'active' && ! request('search'))
                                    <p class="mt-1 text-sm" style="color:#8a7f78;">Add your first product to start selling.</p>
                                    <a href="{{ route('seller.books.create') }}" class="mt-4 inline-block rounded-md px-4 py-2 text-sm font-semibold text-white" style="background:#fa4e1c;">Add product</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($books->hasPages())
            <div class="border-t px-4 py-3" style="border-color:#F2EEEA;">{{ $books->links() }}</div>
        @endif
    </div>
</div>
<script>
(function () {
    var menus = document.querySelectorAll('[data-inv-more]');
    menus.forEach(function (d) { d.addEventListener('toggle', function () { if (d.open) menus.forEach(function (o) { if (o !== d) o.open = false; }); }); });
    document.addEventListener('click', function (e) { menus.forEach(function (d) { if (d.open && !d.contains(e.target)) d.open = false; }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') menus.forEach(function (d) { if (d.open) { d.open = false; d.querySelector('summary').focus(); } }); });
})();
</script>
</x-seller-layout>
