<x-admin-layout title="Shop Management" active="products">

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search…" class="input w-56 py-2 text-sm" style="border-color:#FFDCC2;">
        <select name="category" class="input w-40 py-2 text-sm" onchange="this.form.submit()" style="border-color:#FFDCC2;">
            <option value="">All categories</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->name }}" {{ request('category') === $cat->name ? 'selected':'' }}>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>
        <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Search</button>
        @if (request()->hasAny(['search','category']))
            <a href="{{ route('admin.products.index') }}" class="text-sm" style="color:#fa4e1c;">Clear</a>
        @endif
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn-gold text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">+ Add Product</a>
</div>

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Book</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Category</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Seller</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Price</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Stock</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr style="border-top:1px solid #dce8f0;" onmouseover="this.style.background='#e8f0f6';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $product->image ? asset('storage/'.$product->image) : 'https://placehold.co/40x54/FF6300/FFFFFF?text=B' }}"
                                 class="h-10 w-7 rounded object-cover flex-shrink-0" alt="{{ $product->title }}">
                            <div>
                                <p class="font-semibold" style="color:#222222;">{{ $product->title }}</p>
                                <p class="text-xs" style="color:#6b90aa;">{{ $product->author }} · {{ $product->format }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $product->category->name ?? '—' }}</td>
                    <td class="px-3 py-3 text-xs" style="color:#555555;">{{ $product->seller->name ?? 'Admin' }}</td>
                    <td class="px-3 py-3 font-semibold" style="color:#222222;">${{ number_format($product->price, 2) }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $product->stock > 0 ? '#ECFDF5' : '#FEF2F2' }};
                                     color:{{ $product->stock > 0 ? '#059669' : '#DC2626' }};">
                            {{ $product->stock }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.products.edit', $product->id) }}"
                           class="text-xs font-semibold mr-3" style="color:#fa4e1c;"
                           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">Edit</a>
                        <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" class="inline"
                              onsubmit="return confirm('Delete this book?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold" style="color:#DC2626;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No products found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $products->links() }}</div>

</x-admin-layout>