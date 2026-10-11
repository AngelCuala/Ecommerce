<x-seller-layout title="Products" active="products">
@php
    $statuses = [
        'active'       => ['label' => 'Active',       'count' => $counts['active']],
        'draft'        => ['label' => 'Drafts',       'count' => $counts['draft'] ?? 0],
        'low_stock'    => ['label' => 'Low stock',    'count' => $counts['low_stock']],
        'out_of_stock' => ['label' => 'Out of stock', 'count' => $counts['out_of_stock']],
        'archived'     => ['label' => 'Archived',     'count' => $counts['archived']],
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

    @if ($errors->any())
        <div class="mb-4 rounded-md border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
            <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Tabs + search --}}
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3 border-b" style="border-color:#ECE7E3;">
        <nav class="flex flex-wrap">
            @foreach ($statuses as $key => $tab)
                <a href="{{ route('seller.books.index', ['status' => $key, 'search' => request('search')]) }}"
                   class="inv-tab {{ $status === $key ? 'is-active' : '' }}">
                    {{ $tab['label'] }}<span class="tab-count">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </nav>
        <form method="GET" class="mb-2 flex items-center gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2" style="color:#b3a79f;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or SKU"
                       class="input !py-1.5 !pl-8 text-sm" style="width:15rem;">
            </div>
            @if (request('search'))
                <a href="{{ route('seller.books.index', ['status' => $status]) }}" class="text-sm font-medium hover:underline" style="color:#8a7f78;">Clear</a>
            @endif
        </form>
    </div>

    {{-- Products --}}
    <div class="overflow-hidden rounded-xl border bg-white" style="border-color:#ECE7E3;">
        <div class="overflow-x-auto">
            <table class="inv-table w-full text-sm">
                <thead>
                    <tr>
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
                            <td style="color:#57534E;">{{ $book->category->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">
                                <span class="font-medium" style="color:#222;">₱{{ number_format($book->effective_price, 2) }}</span>
                                @if ($book->hasDiscount())
                                    <span class="block text-xs" style="color:#8a7f78;">
                                        <span class="line-through">₱{{ number_format($book->price, 2) }}</span>
                                        @if ($book->discount_percent > 0) · {{ $book->discount_percent }}% off @endif
                                    </span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('seller.books.stock', $book->id) }}" method="POST" class="flex items-center gap-1.5">
                                    @csrf @method('PATCH')
                                    <input type="number" name="stock" value="{{ $book->stock }}" min="0" aria-label="Stock for {{ $book->title }}"
                                           class="w-16 rounded-md border px-2 py-1 text-center text-sm focus:outline-none focus:ring-2 focus:ring-orange-200"
                                           style="border-color:{{ $book->stock === 0 ? '#FCA5A5' : ($book->stock <= 5 ? '#FCD34D' : '#E7E5E4') }};color:#222;">
                                    <button type="submit" class="rounded-md border px-2 py-1 text-xs font-semibold transition hover:bg-stone-50" style="border-color:#E7E5E4;color:#57534E;">Save</button>
                                </form>
                                @if ($book->stock === 0)
                                    <p class="mt-1 text-xs" style="color:#B91C1C;">Out of stock</p>
                                @elseif ($book->stock <= 5)
                                    <p class="mt-1 text-xs" style="color:#B45309;">Low stock</p>
                                @endif
                            </td>
                            <td>
                                @php [$listLabel, $listCss] = $book->is_archived
                                    ? ['Archived', 'background:#F5F5F4;color:#78716C;']
                                    : ($listingStyle[$book->status] ?? [ucfirst((string) $book->status ?: 'Active'), 'background:#ECFDF5;color:#047857;']); @endphp
                                <span class="rounded-md px-2 py-0.5 text-xs font-semibold" style="{{ $listCss }}">{{ $listLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('seller.books.edit', $book->id) }}" class="inv-action">Edit</a>
                                <span class="mx-1.5" style="color:#E7E5E4;">|</span>
                                @if ($book->is_archived)
                                    <form action="{{ route('seller.books.unarchive', $book->id) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="inv-action">Restore</button>
                                    </form>
                                @else
                                    <form action="{{ route('seller.books.archive', $book->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Archive this product? Buyers won\'t see it until you restore it.')">
                                        @csrf @method('PATCH')
                                        <button class="inv-action">Archive</button>
                                    </form>
                                @endif
                                <span class="mx-1.5" style="color:#E7E5E4;">|</span>
                                <form action="{{ route('seller.books.destroy', $book->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Permanently delete this product? This can\'t be undone.')">
                                    @csrf @method('DELETE')
                                    <button class="inv-action !text-[#B91C1C]">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center">
                                <p class="font-medium" style="color:#222;">
                                    @if (request('search')) No products match "{{ request('search') }}"
                                    @elseif ($status === 'archived') No archived products
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
    </div>
</div>
</x-seller-layout>
