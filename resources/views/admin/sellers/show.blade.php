<x-admin-layout title="Seller Details" active="sellers">

@php
    $app = $seller->sellerApplication;
    $isSuspended = in_array($seller->role, ['suspended', 'deactivated']);
@endphp

<a href="{{ route('admin.sellers.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm font-semibold" style="color:#fa4e1c;">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    Back to sellers
</a>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- Left: identity + actions --}}
    <div class="space-y-6">
        <div class="card p-6 text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full font-display text-2xl font-bold"
                 style="background:#fa4e1c;color:#fff;">
                {{ strtoupper(substr($seller->name ?? 'S', 0, 1)) }}
            </div>
            <h2 class="mt-3 font-display text-lg font-extrabold" style="color:#002b4d;">{{ $seller->name }}</h2>
            <p class="text-sm" style="color:#6b90aa;">{{ $seller->email }}</p>
            <span class="mt-3 inline-block rounded-full px-3 py-1 text-xs font-semibold"
                  style="background:{{ $isSuspended ? '#FEF2F2' : '#F0FDF4' }};color:{{ $isSuspended ? '#DC2626' : '#059669' }};">
                {{ $isSuspended ? 'Suspended / Disabled' : 'Active Seller' }}
            </span>

            <div class="mt-5 border-t pt-4" style="border-color:#dce8f0;">
                @if ($isSuspended)
                    <form action="{{ route('admin.sellers.activate', $seller->id) }}" method="POST">
                        @csrf
                        <button class="btn-gold w-full !py-2 text-sm"
                                onclick="return confirm('Reactivate this seller account?')">Reactivate Seller</button>
                    </form>
                @else
                    <form action="{{ route('admin.sellers.suspend', $seller->id) }}" method="POST">
                        @csrf
                        <button class="w-full rounded-lg py-2 text-sm font-semibold transition"
                                style="background:#FEF2F2;color:#DC2626;border:1px solid #fecaca;"
                                onclick="return confirm('Suspend this seller account?')">Suspend Seller</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="card p-6">
            <p class="text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Products Listed</p>
            <p class="mt-1 font-display text-3xl font-extrabold" style="color:#002b4d;">{{ $seller->books_count }}</p>
        </div>
    </div>

    {{-- Right: business + verification --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="card p-6">
            <h3 class="font-display text-base font-bold mb-4" style="color:#002b4d;">Shop & Business Information</h3>
            @if ($app)
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Shop Name</dt>
                        <dd class="mt-0.5 text-sm font-medium" style="color:#002b4d;">{{ $app->shop_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Business Name</dt>
                        <dd class="mt-0.5 text-sm font-medium" style="color:#002b4d;">{{ $app->business_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Business Category</dt>
                        <dd class="mt-0.5 text-sm font-medium" style="color:#002b4d;">{{ $app->line_of_business ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Owner</dt>
                        <dd class="mt-0.5 text-sm font-medium" style="color:#002b4d;">{{ $app->fullName() ?: $seller->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Phone</dt>
                        <dd class="mt-0.5 text-sm font-medium" style="color:#002b4d;">{{ $app->phone ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Address</dt>
                        <dd class="mt-0.5 text-sm font-medium" style="color:#002b4d;">
                            {{ collect([$app->street, $app->barangay, $app->municipality, $app->province])->filter()->implode(', ') ?: ($app->address ?? '—') }}
                        </dd></div>
                    @if ($app->description)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Description</dt>
                            <dd class="mt-0.5 text-sm" style="color:#555555;">{{ $app->description }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="text-sm" style="color:#6b90aa;">No seller application on file for this account.</p>
            @endif
        </div>

        {{-- Verification --}}
        <div class="card p-6">
            <h3 class="font-display text-base font-bold mb-4" style="color:#002b4d;">Verification</h3>
            @if ($app)
                <div class="mb-4 flex items-center gap-2">
                    @php
                        $vs = match($app->status) {
                            'approved' => ['bg'=>'#F0FDF4','text'=>'#059669','label'=>'Approved'],
                            'rejected' => ['bg'=>'#FEF2F2','text'=>'#DC2626','label'=>'Rejected'],
                            default    => ['bg'=>'#e8f0f6','text'=>'#fa4e1c','label'=>'Pending'],
                        };
                    @endphp
                    <span class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Application Status</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                          style="background:{{ $vs['bg'] }};color:{{ $vs['text'] }};">{{ $vs['label'] }}</span>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Government ID</p>
                        @if ($app->government_id_path)
                            <a href="{{ asset('storage/'.$app->government_id_path) }}" target="_blank"
                               class="inline-flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-semibold"
                               style="border-color:#fa4e1c;color:#fa4e1c;background:#fff;">View document</a>
                        @else
                            <p class="text-sm" style="color:#6b90aa;">Not provided</p>
                        @endif
                    </div>
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Business Permit</p>
                        @if ($app->business_permit_path)
                            <a href="{{ asset('storage/'.$app->business_permit_path) }}" target="_blank"
                               class="inline-flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-semibold"
                               style="border-color:#fa4e1c;color:#fa4e1c;background:#fff;">View document</a>
                        @else
                            <p class="text-sm" style="color:#6b90aa;">Not provided</p>
                        @endif
                    </div>
                </div>
            @else
                <p class="text-sm" style="color:#6b90aa;">No verification records available.</p>
            @endif
        </div>
    </div>
</div>

{{-- ── The seller's products ───────────────────────────────────── --}}
@php
    $tabs = ['all' => 'All', 'live' => 'Live', 'pending' => 'Pending approval', 'rejected' => 'Rejected', 'draft' => 'Drafts', 'archived' => 'Archived'];
@endphp
<div class="card mt-6 p-6" id="seller-products">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="font-display text-xl font-extrabold" style="color:#002b4d;">Products</h3>
            <p class="text-xs" style="color:#6b90aa;">Everything this seller has listed, including drafts and items waiting for approval.</p>
        </div>
        <form method="GET" action="{{ route('admin.sellers.show', $seller->id) }}#seller-products" class="flex gap-2">
            <input type="hidden" name="products" value="{{ $productState }}">
            <input type="search" name="product_search" value="{{ request('product_search') }}" placeholder="Product name or code…"
                   class="input w-56 py-2 text-sm" style="border-color:#FFDCC2;">
            <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">Search</button>
        </form>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.sellers.show', ['id' => $seller->id, 'products' => $key]) }}#seller-products"
               class="rounded-full border px-3 py-1 text-xs font-semibold"
               style="{{ $productState === $key ? 'background:#fa4e1c;border-color:#fa4e1c;color:#fff;' : 'border-color:#FFDCC2;color:#6b5a50;' }}">
                {{ $label }} <span class="opacity-80">({{ $productCounts[$key] }})</span>
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-lg border" style="border-color:#dce8f0;">
        <table class="w-full text-sm">
            <thead style="background:#e8f0f6;">
                <tr style="color:#6b90aa;">
                    <th class="px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider">Product</th>
                    <th class="px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider">Category</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider">Price</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider">Stock</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider">Sold</th>
                    <th class="px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider">Status</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($products as $p)
                @php
                    [$stateLabel, $stateStyle] = match (true) {
                        $p->is_archived            => ['Archived', 'background:#F5F5F4;color:#78716C;'],
                        $p->status === 'draft'     => ['Draft', 'background:#F5F5F4;color:#57534E;'],
                        $p->isPendingApproval()    => ['Pending approval', 'background:#FFFBEB;color:#B45309;'],
                        $p->isRejected()           => ['Rejected', 'background:#FEF2F2;color:#B91C1C;'],
                        $p->stock <= 0             => ['Out of stock', 'background:#FEF2F2;color:#B91C1C;'],
                        default                    => ['Live', 'background:#ECFDF5;color:#047857;'],
                    };
                @endphp
                <tr class="border-t" style="border-color:#eef3f7;">
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-3">
                            <img src="{{ $p->image ? asset('storage/' . $p->image) : 'https://placehold.co/48x48/f5f5f5/999?text=—' }}"
                                 alt="" class="h-11 w-11 shrink-0 rounded-md border object-cover" style="border-color:#eef3f7;" loading="lazy">
                            <div class="min-w-0">
                                <p class="truncate font-semibold" style="color:#002b4d;max-width:280px;">{{ $p->title }}</p>
                                <p class="text-xs" style="color:#6b90aa;">{{ $p->product_code ?: 'No code' }} · added {{ $p->created_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2 text-xs" style="color:#555;">{{ $p->category->name ?? '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        <span class="font-semibold" style="color:#002b4d;">₱{{ number_format($p->effective_price, 2) }}</span>
                        @if ($p->effective_price < $p->price)
                            <span class="block text-xs line-through" style="color:#9db3c4;">₱{{ number_format($p->price, 2) }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right" style="{{ $p->stock <= 5 ? 'color:#B91C1C;font-weight:600;' : '' }}">{{ $p->stock }}</td>
                    <td class="px-3 py-2 text-right">{{ (int) $p->sold }}</td>
                    <td class="px-3 py-2">
                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold" style="{{ $stateStyle }}">{{ $stateLabel }}</span>
                        @if ($p->isRejected() && $p->approval_note)
                            <span class="mt-1 block max-w-[200px] text-xs" style="color:#B91C1C;">{{ $p->approval_note }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-xs font-semibold">
                        @if ($p->isPendingApproval() && $p->status !== 'draft' && ! $p->is_archived)
                            <a href="{{ route('admin.product-approvals.index', ['search' => $p->product_code ?: $p->title]) }}" style="color:#B45309;">Review</a>
                            <span class="mx-1" style="color:#E7E5E4;">|</span>
                        @endif
                        <a href="{{ route('admin.products.edit', $p->id) }}" style="color:#fa4e1c;">Edit</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-3 py-8 text-center text-sm" style="color:#6b90aa;">
                    {{ request('product_search') ? 'No products match "' . request('product_search') . '".' : 'No products here.' }}
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->fragment('seller-products')->links() }}</div>
</div>

</x-admin-layout>
