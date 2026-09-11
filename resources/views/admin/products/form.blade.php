<x-admin-layout :title="isset($product) ? 'Edit: '.$product->title : 'Add Product'" active="products">

<div class="mb-6">
    <a href="{{ route('admin.products.index') }}"
       class="text-sm transition" style="color:#fa4e1c;"
       onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
        ← Back to products
    </a>
</div>

@if ($errors->any())
    <div class="mb-6 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<form action="{{ isset($product) ? route('admin.products.update', $product->id) : route('admin.products.store') }}"
      method="POST" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if (isset($product)) @method('PUT') @endif

    {{-- Basic info --}}
    <div class="card p-6 space-y-5">
        <h2 class="font-display text-base font-bold" style="color:#222222;">Basic Information</h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="text-xs font-semibold" style="color:#6b90aa;">Product Name *</label>
                <input type="text" name="title" value="{{ old('title', $product->title ?? '') }}" class="input mt-1" required style="border-color:#FFDCC2;">
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Brand</label>
                <input type="text" name="brand" value="{{ old('brand', $product->brand ?? '') }}" class="input mt-1" placeholder="e.g. Nike, Samsung…" style="border-color:#FFDCC2;">
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Category *</label>
                <select name="category_id" class="input mt-1" required style="border-color:#cfdce8;">
                    <option value="">Select category</option>
                    @php
                        $catalogOrder = [
                            'Pet Supplies'         => '🐾',
                            'Kids & Baby'          => '🍼',
                            'Electronics & Gadgets'=> '📱',
                            "Women's Apparel"      => '👗',
                            'Sports & Outdoors'    => '⚽',
                            'Home & Garden'        => '🏡',
                            "Men's Apparel"        => '👔',
                            'Health & Beauty'      => '💄',
                            'Books & Media'        => '📚',
                            'Food & Gourmet'       => '🍽️',
                            'Furniture & Office'   => '🪑',
                            'Jewelry & Watches'    => '💍',
                        ];
                        $sortedCats = $categories->sortBy(fn($cat) =>
                            array_search($cat->name, array_keys($catalogOrder)) !== false
                                ? array_search($cat->name, array_keys($catalogOrder))
                                : 99
                        );
                    @endphp
                    @foreach ($sortedCats as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                            {{ $catalogOrder[$cat->name] ?? '' }} {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-semibold" style="color:#6b90aa;">Description</label>
                <textarea name="description" rows="4" class="input mt-1" placeholder="Describe the product…" style="border-color:#FFDCC2;">{{ old('description', $product->description ?? '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- Pricing & Inventory --}}
    <div class="card p-6 space-y-5">
        <h2 class="font-display text-base font-bold" style="color:#222222;">Pricing & Inventory</h2>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Price ($) *</label>
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:#6b90aa;">$</span>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $product->price ?? '') }}" class="input pl-7" min="0" required style="border-color:#FFDCC2;">
                </div>
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Compare-at Price ($)</label>
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:#6b90aa;">$</span>
                    <input type="number" step="0.01" name="compare_price" value="{{ old('compare_price', $product->compare_price ?? '') }}" class="input pl-7" min="0" placeholder="Optional" style="border-color:#FFDCC2;">
                </div>
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Stock *</label>
                <input type="number" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" class="input mt-1" min="0" required style="border-color:#FFDCC2;">
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">SKU</label>
                <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" class="input mt-1" placeholder="Auto-generated if blank" style="border-color:#FFDCC2;">
            </div>
        </div>
    </div>

    {{-- Variations --}}
    <div class="card p-6 space-y-5">
        <div class="flex items-center justify-between">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Variations</h2>
            <p class="text-xs" style="color:#6b90aa;">Fill only what applies to this product</p>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">

            {{-- Color --}}
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Colors</label>
                <input type="text" name="colors" value="{{ old('colors', $product->colors ?? '') }}"
                       class="input mt-1" placeholder="e.g. Red, Blue, Black"
                       style="border-color:#FFDCC2;">
                <p class="mt-1 text-[11px]" style="color:#6b90aa;">Comma-separated values</p>
            </div>

            {{-- Size --}}
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Sizes</label>
                <input type="text" name="sizes" value="{{ old('sizes', $product->sizes ?? '') }}"
                       class="input mt-1" placeholder="e.g. XS, S, M, L, XL  or  6, 7, 8, 9"
                       style="border-color:#FFDCC2;">
                <p class="mt-1 text-[11px]" style="color:#6b90aa;">Comma-separated values</p>
            </div>

            {{-- Storage --}}
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Storage</label>
                <input type="text" name="storage" value="{{ old('storage', $product->storage ?? '') }}"
                       class="input mt-1" placeholder="e.g. 64GB, 128GB, 256GB"
                       style="border-color:#FFDCC2;">
                <p class="mt-1 text-[11px]" style="color:#6b90aa;">Comma-separated values</p>
            </div>

            {{-- Material --}}
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Material</label>
                <input type="text" name="material" value="{{ old('material', $product->material ?? '') }}"
                       class="input mt-1" placeholder="e.g. Cotton, Leather, Plastic"
                       style="border-color:#FFDCC2;">
            </div>

            {{-- Weight --}}
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Weight</label>
                <input type="text" name="weight" value="{{ old('weight', $product->weight ?? '') }}"
                       class="input mt-1" placeholder="e.g. 0.5kg, 1lb"
                       style="border-color:#FFDCC2;">
            </div>

            {{-- Custom variation --}}
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Custom Variation</label>
                <input type="text" name="custom_variation_label" value="{{ old('custom_variation_label', $product->custom_variation_label ?? '') }}"
                       class="input mt-1" placeholder="Label (e.g. Voltage)"
                       style="border-color:#FFDCC2;">
                <input type="text" name="custom_variation_values" value="{{ old('custom_variation_values', $product->custom_variation_values ?? '') }}"
                       class="input mt-1" placeholder="Values (e.g. 110V, 220V)"
                       style="border-color:#FFDCC2;">
            </div>

        </div>
    </div>

    {{-- Images --}}
    <div class="card p-6">
        <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Images</h2>

        @if (isset($product) && $product->image)
            <div class="mb-4 flex items-center gap-3">
                <img src="{{ asset('storage/'.$product->image) }}"
                     class="h-20 w-20 rounded-lg object-cover border"
                     style="border-color:#dce8f0;" alt="Current image">
                <p class="text-xs" style="color:#6b90aa;">Upload new images to replace the current one</p>
            </div>
        @endif

        <input type="file" name="images[]" accept="image/*" multiple class="input py-2" style="border-color:#FFDCC2;">
        <p class="mt-1 text-[11px]" style="color:#6b90aa;">
            JPG, PNG or WebP · max 3 MB each · you can select multiple files
            {{ !isset($product) ? '(at least one required)' : '(optional — replaces existing)' }}
        </p>
    </div>

    {{-- Actions --}}
    <div class="flex gap-3">
        <button type="submit" class="btn-gold flex-1 py-3" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">
            {{ isset($product) ? 'Save Changes' : 'Add Product' }}
        </button>
        <a href="{{ route('admin.products.index') }}" class="btn-outline py-3 px-8" style="border-color:#fa4e1c;color:#fa4e1c;">Cancel</a>
    </div>

</form>

</x-admin-layout>