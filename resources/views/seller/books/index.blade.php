<x-seller-layout title="Products" active="products">
<div>

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <span class="section-eyebrow" style="color:#fa4e1c;">Seller Panel</span>
            <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222222;">Inventory Management</h1>
        </div>
        <a href="{{ route('seller.books.create') }}"
           class="rounded-xl px-5 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
           style="background:#002b4d;">+ Add Product</a>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#d93d0e;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
            <ul class="list-inside list-disc">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Status tabs + search --}}
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-2">
            @php
                $statuses = [
                    'active'       => ['label'=>'Active',        'count'=>$counts['active'],       'color'=>'#059669'],
                    'low_stock'    => ['label'=>'Low Stock',      'count'=>$counts['low_stock'],    'color'=>'#D97706'],
                    'out_of_stock' => ['label'=>'Out of Stock',   'count'=>$counts['out_of_stock'],'color'=>'#DC2626'],
                    'archived'     => ['label'=>'Archived',       'count'=>$counts['archived'],     'color'=>'#6B7280'],
                ];
            @endphp
            @foreach ($statuses as $key => $tab)
                <a href="{{ route('seller.books.index', ['status'=>$key, 'search'=>request('search')]) }}"
                   class="rounded-full px-4 py-1.5 text-xs font-bold transition"
                   style="{{ $status === $key
                       ? 'background:#fa4e1c;color:#fff;'
                       : 'background:#e8f0f6;color:#fa4e1c;' }}">
                    {{ $tab['label'] }}
                    <span class="ml-1 opacity-75">({{ $tab['count'] }})</span>
                </a>
            @endforeach
        </div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or SKU…"
                   class="rounded-xl border px-3 py-1.5 text-sm focus:outline-none"
                   style="border-color:#cfdce8;width:220px;"
                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            <button type="submit" class="rounded-xl px-4 py-1.5 text-xs font-bold text-white" style="background:#002b4d;">Search</button>
            @if(request('search'))
                <a href="{{ route('seller.books.index', ['status'=>$status]) }}"
                   class="rounded-xl border px-4 py-1.5 text-xs font-semibold"
                   style="border-color:#cfdce8;color:#6b90aa;">Clear</a>
            @endif
        </form>
    </div>

    {{-- Products table --}}
    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background:#e8f0f6;">
                <tr>
                    <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Product</th>
                    <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Category</th>
                    <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Price</th>
                    <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Stock</th>
                    <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Discount</th>
                    <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr style="border-top:1px solid #fff1ee;"
                        onmouseover="this.style.background='#FFF9F5';" onmouseout="this.style.background='';">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $book->image ? asset('storage/'.$book->image) : 'https://placehold.co/40x54/FF6300/fff?text=P' }}"
                                     class="h-11 w-8 rounded object-cover flex-shrink-0" alt="{{ $book->title }}">
                                <div class="min-w-0">
                                    <p class="font-semibold truncate max-w-[180px]" style="color:#222222;">{{ $book->title }}</p>
                                    <p class="text-xs" style="color:#6b90aa;">
                                        {{ $book->author }}
                                        @if ($book->sku) · SKU: {{ $book->sku }} @endif
                                    </p>
                                    @if ($book->voucher_code)
                                        <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold mt-0.5"
                                              style="background:#fff1ee;color:#fa4e1c;">
                                            🏷 {{ $book->voucher_code }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-xs" style="color:#7A7A7A;">{{ $book->category->name ?? '—' }}</td>
                        <td class="px-3 py-3">
                            <p class="font-semibold text-sm" style="color:#fa4e1c;">₱{{ number_format($book->effective_price, 2) }}</p>
                            @if ($book->hasDiscount())
                                <p class="text-xs line-through" style="color:#6b90aa;">₱{{ number_format($book->price, 2) }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            {{-- Inline stock update --}}
                            <form action="{{ route('seller.books.stock', $book->id) }}" method="POST" class="flex items-center gap-1.5">
                                @csrf @method('PATCH')
                                <input type="number" name="stock" value="{{ $book->stock }}" min="0"
                                       class="w-16 rounded-lg border px-2 py-1 text-xs text-center focus:outline-none"
                                       style="border-color:{{ $book->stock === 0 ? '#FECACA' : ($book->stock <= 5 ? '#FDE68A' : '#E0E0E0') }};color:#222;">
                                <button type="submit" class="rounded-lg px-2 py-1 text-[10px] font-bold text-white transition hover:opacity-80"
                                        style="background:#002b4d;">✓</button>
                            </form>
                            @if ($book->stock === 0)
                                <p class="text-[10px] mt-0.5" style="color:#DC2626;">Out of stock</p>
                            @elseif ($book->stock <= 5)
                                <p class="text-[10px] mt-0.5" style="color:#D97706;">Low stock</p>
                            @endif
                        </td>
                        <td class="px-3 py-3">
                            @if ($book->discount_percent > 0)
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold"
                                      style="background:#ECFDF5;color:#059669;">
                                    {{ $book->discount_percent }}% off
                                </span>
                            @else
                                <span class="text-xs" style="color:#6b90aa;">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2 flex-wrap">
                                <a href="{{ route('seller.books.edit', $book->id) }}"
                                   class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                   style="background:#e8f0f6;color:#fa4e1c;border:1px solid #cfdce8;"
                                   onmouseover="this.style.background='#fa4e1c';this.style.color='#fff';"
                                   onmouseout="this.style.background='#e8f0f6';this.style.color='#fa4e1c';">
                                    ✏️ Edit
                                </a>

                                @if ($book->is_archived)
                                    <form action="{{ route('seller.books.unarchive', $book->id) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                                style="background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;"
                                                onmouseover="this.style.background='#059669';this.style.color='#fff';"
                                                onmouseout="this.style.background='#ECFDF5';this.style.color='#059669';">
                                            ↩ Restore
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('seller.books.archive', $book->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Archive this product?')">
                                        @csrf @method('PATCH')
                                        <button class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                                style="background:#FFFBEB;color:#D97706;border:1px solid #FDE68A;"
                                                onmouseover="this.style.background='#D97706';this.style.color='#fff';"
                                                onmouseout="this.style.background='#FFFBEB';this.style.color='#D97706';">
                                            📦 Archive
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('seller.books.destroy', $book->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Permanently delete this product?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg px-3 py-1.5 text-xs font-bold transition"
                                            style="background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;"
                                            onmouseover="this.style.background='#DC2626';this.style.color='#fff';"
                                            onmouseout="this.style.background='#FEF2F2';this.style.color='#DC2626';">
                                        🗑
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-14 text-center">
                            <span class="text-3xl">
                                @if ($status === 'archived') 📦
                                @elseif ($status === 'low_stock') ⚠️
                                @elseif ($status === 'out_of_stock') ❌
                                @else 📦 @endif
                            </span>
                            <p class="mt-3 font-bold" style="color:#222222;">
                                @if ($status === 'archived') No archived products
                                @elseif ($status === 'low_stock') No low-stock products
                                @elseif ($status === 'out_of_stock') All products are in stock
                                @else No products listed yet @endif
                            </p>
                            @if ($status === 'active')
                                <a href="{{ route('seller.books.create') }}"
                                   class="mt-4 inline-flex rounded-xl px-5 py-2.5 text-sm font-bold text-white"
                                   style="background:#002b4d;">+ Add First Product</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</x-seller-layout>
