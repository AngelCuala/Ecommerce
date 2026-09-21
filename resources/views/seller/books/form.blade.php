<x-seller-layout :title="isset($book) ? 'Edit: '.$book->title : 'Add Product'" active="products">
@php
    $isEdit       = isset($book);
    $selectedCat  = old('category_id', $book->category_id ?? '');
    $selectedName = optional($categories->firstWhere('id', (int) $selectedCat))->name;
    $currentSpecs = old('specs', $book->specs ?? []);
    $currentSub   = old('subcategory', $book->subcategory ?? '');
    $currentStatus= old('status', $book->status ?? 'active');
    $existingVars = old('variations', $isEdit ? $book->variations->map(fn($v) => [
        'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock, 'sku' => $v->sku,
    ])->toArray() : []);
@endphp

<div x-data="productForm()" x-init="init()">

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <span class="text-xs font-semibold uppercase tracking-wide" style="color:#fa4e1c;">Seller Panel</span>
            <h1 class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">
                {{ $isEdit ? 'Edit Product' : 'List a New Product' }}
            </h1>
            @if ($isEdit && $book->product_code)
                <p class="mt-1 text-sm" style="color:#6b90aa;">Product ID: <span class="font-semibold">{{ $book->product_code }}</span></p>
            @endif
        </div>
        <a href="{{ route('seller.books.index') }}" class="text-sm font-semibold" style="color:#6b90aa;">&larr; Back to products</a>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border p-4 text-sm" style="background:rgba(217,61,14,.07);border-color:rgba(217,61,14,.25);color:#d93d0e;">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $isEdit ? route('seller.books.update', $book->id) : route('seller.books.store') }}"
          method="POST" enctype="multipart/form-data" class="space-y-6" id="product-form">
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <input type="hidden" name="action" x-model="action">

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">

                {{-- ════════ IMAGES ════════ --}}
                <section class="card p-6 space-y-4">
                    <div>
                        <h2 class="font-display text-lg font-semibold" style="color:#002b4d;">Product Photos</h2>
                        <p class="mt-1 text-sm" style="color:#6b90aa;">
                            Upload up to 9 photos. The <strong>first photo is the main image</strong>. Drag to reorder.
                            JPG, PNG or WebP · max 5&nbsp;MB each.
                        </p>
                    </div>

                    {{-- Existing images (edit) — reorderable + removable --}}
                    @if ($isEdit && $book->images->isNotEmpty())
                        <div>
                            <p class="mb-2 text-xs font-semibold" style="color:#6b90aa;">Current photos</p>
                            <div id="existing-images" class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5">
                                @foreach ($book->images as $img)
                                    <div class="ex-img relative aspect-square overflow-hidden rounded-xl border" data-id="{{ $img->id }}"
                                         style="border-color:#cfdce8;cursor:grab;">
                                        <img src="{{ $img->url() }}" class="h-full w-full object-cover">
                                        <input type="hidden" name="image_order[]" value="{{ $img->id }}">
                                        <span class="main-badge absolute bottom-1 left-1 hidden rounded px-1 py-0.5 text-[9px] font-bold text-white" style="background:#fa4e1c;">MAIN</span>
                                        <button type="button" onclick="removeExisting(this, {{ $img->id }})"
                                                class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full text-white" style="background:rgba(0,0,0,.6);">&times;</button>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-2 text-[11px]" style="color:#6b90aa;">The first photo is marked MAIN. Drag to change order; click &times; to remove.</p>
                        </div>
                    @endif

                    <div id="removed-container"></div>

                    {{-- New uploads --}}
                    <div>
                        <p class="mb-2 text-xs font-semibold" style="color:#6b90aa;">{{ $isEdit ? 'Add more photos' : 'Upload photos' }}</p>
                        <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed py-8 transition"
                               style="border-color:#fa4e1c;background:#fff1ee;">
                            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="previewNew($event)">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:#fa4e1c;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span class="mt-1 text-sm font-semibold" style="color:#fa4e1c;">Click to upload photos</span>
                        </label>
                        <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5" id="new-previews"></div>
                    </div>

                    {{-- Optional video --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Product Video <span style="font-weight:400;">(optional · MP4/MOV/WebM · max 20&nbsp;MB)</span></label>
                        <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm" class="input mt-1">
                        @if ($isEdit && $book->video_path)
                            <p class="mt-1 text-[11px]" style="color:#6b90aa;">Current: {{ basename($book->video_path) }}</p>
                        @endif
                    </div>
                </section>

                {{-- ════════ BASIC INFO ════════ --}}
                <section class="card p-6 space-y-5">
                    <h2 class="font-display text-lg font-semibold" style="color:#002b4d;">Basic Information</h2>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Product Name *</label>
                            <input type="text" name="title" value="{{ old('title', $book->title ?? '') }}" class="input mt-1" placeholder="e.g. Cotton Oversized T-Shirt">
                        </div>

                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Category *</label>
                            <select name="category_id" class="input mt-1" x-model="categoryId" @change="onCategoryChange()">
                                <option value="">Select category</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" data-name="{{ $cat->name }}"
                                        {{ (string) $selectedCat === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Subcategory</label>
                            <select name="subcategory" class="input mt-1" x-model="subcategory">
                                <option value="">Select subcategory</option>
                                <template x-for="sub in subs" :key="sub">
                                    <option :value="sub" x-text="sub"></option>
                                </template>
                            </select>
                            <p class="mt-1 text-[11px]" style="color:#6b90aa;" x-show="subs.length === 0">No subcategories for this category.</p>
                        </div>

                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Brand</label>
                            <input type="text" name="brand" value="{{ old('brand', $book->brand ?? '') }}" class="input mt-1" placeholder="Brand name" x-ref="brand" :disabled="noBrand">
                            <label class="mt-2 inline-flex items-center gap-2 text-[11px]" style="color:#6b90aa;">
                                <input type="checkbox" x-model="noBrand" @change="if(noBrand) $refs.brand.value=''"> No Brand
                            </label>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Description</label>
                            <textarea name="description" rows="4" class="input mt-1" maxlength="5000"
                                      placeholder="Describe your product — features, materials, condition…">{{ old('description', $book->description ?? '') }}</textarea>
                        </div>
                    </div>
                </section>

                {{-- ════════ PRICING & INVENTORY ════════ --}}
                <section class="card p-6 space-y-5">
                    <h2 class="font-display text-lg font-semibold" style="color:#002b4d;">Pricing &amp; Inventory</h2>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Price (₱) *</label>
                            <input type="number" step="0.01" min="0" name="price" x-model="price" class="input mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Sale Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="sale_price" x-model="salePrice" class="input mt-1" placeholder="optional">
                            <p class="mt-1 text-[11px]" x-show="saleError" style="color:#d93d0e;">Sale price must be ≤ original price.</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Stock *</label>
                            <input type="number" min="0" name="stock" x-model="stock" class="input mt-1" placeholder="0" :disabled="variations.length > 0">
                            <p class="mt-1 text-[11px]" style="color:#6b90aa;" x-show="variations.length > 0">Auto-summed from variations (<span x-text="totalVarStock"></span>).</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">SKU <span style="font-weight:400;">(optional)</span></label>
                            <input type="text" name="sku" value="{{ old('sku', $book->sku ?? '') }}" class="input mt-1" placeholder="Auto ID if blank">
                            <p class="mt-1 text-[11px]" style="color:#6b90aa;">Leave blank to auto-generate a Product ID.</p>
                        </div>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Discount % <span style="font-weight:400;">(optional)</span></label>
                            <input type="number" step="0.01" min="0" max="99" name="discount_percent" value="{{ old('discount_percent', $book->discount_percent ?? '') }}" class="input mt-1" placeholder="e.g. 10">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Voucher Code <span style="font-weight:400;">(optional)</span></label>
                            <input type="text" name="voucher_code" maxlength="50" value="{{ old('voucher_code', $book->voucher_code ?? '') }}" class="input mt-1" placeholder="e.g. SAVE20">
                        </div>
                    </div>
                </section>

                {{-- ════════ VARIATIONS ════════ --}}
                <section class="card p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-display text-lg font-semibold" style="color:#002b4d;">Variations</h2>
                            <p class="mt-1 text-sm" style="color:#6b90aa;">Add options like color or size, each with its own stock &amp; price. Leave empty for a single-stock product.</p>
                        </div>
                        <button type="button" @click="addVariation()" class="rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background:#fa4e1c;">+ Add</button>
                    </div>

                    <template x-if="variations.length > 0">
                        <div class="space-y-3">
                            <template x-for="(v, i) in variations" :key="i">
                                <div class="grid grid-cols-12 items-end gap-2 rounded-xl border p-3" style="border-color:#cfdce8;">
                                    <div class="col-span-12 sm:col-span-4">
                                        <label class="text-[11px] font-semibold" style="color:#6b90aa;">Variation (e.g. Black / S)</label>
                                        <input type="text" :name="`variations[${i}][name]`" x-model="v.name" class="input mt-1" placeholder="Black / S">
                                    </div>
                                    <div class="col-span-4 sm:col-span-3">
                                        <label class="text-[11px] font-semibold" style="color:#6b90aa;">Price (₱)</label>
                                        <input type="number" step="0.01" min="0" :name="`variations[${i}][price]`" x-model="v.price" class="input mt-1" placeholder="base">
                                    </div>
                                    <div class="col-span-4 sm:col-span-2">
                                        <label class="text-[11px] font-semibold" style="color:#6b90aa;">Stock</label>
                                        <input type="number" min="0" :name="`variations[${i}][stock]`" x-model="v.stock" class="input mt-1" placeholder="0">
                                    </div>
                                    <div class="col-span-3 sm:col-span-2">
                                        <label class="text-[11px] font-semibold" style="color:#6b90aa;">SKU</label>
                                        <input type="text" :name="`variations[${i}][sku]`" x-model="v.sku" class="input mt-1" placeholder="opt.">
                                    </div>
                                    <div class="col-span-1 flex justify-center">
                                        <button type="button" @click="removeVariation(i)" class="mb-1 flex h-9 w-9 items-center justify-center rounded-lg" style="background:#fff1ee;color:#d93d0e;">&times;</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </section>

                {{-- ════════ SPECIFICATIONS ════════ --}}
                <section class="card p-6 space-y-4" x-show="specFields.length > 0" x-cloak>
                    <div>
                        <h2 class="font-display text-lg font-semibold" style="color:#002b4d;">Specifications</h2>
                        <p class="mt-1 text-sm" style="color:#6b90aa;">Category-specific details buyers look for.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <template x-for="field in specFields" :key="field">
                            <div>
                                <label class="text-xs font-semibold" style="color:#6b90aa;" x-text="field"></label>
                                <input type="text" :name="`specs[${field}]`" class="input mt-1"
                                       :value="specValues[field] ?? ''" @input="specValues[field] = $event.target.value">
                            </div>
                        </template>
                    </div>
                </section>

                {{-- ════════ SHIPPING ════════ --}}
                <section class="card p-6 space-y-5">
                    <h2 class="font-display text-lg font-semibold" style="color:#002b4d;">Shipping</h2>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Weight (kg)</label>
                            <input type="number" step="0.01" min="0" name="weight_kg" value="{{ old('weight_kg', $book->weight_kg ?? '') }}" class="input mt-1" placeholder="0.00">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Length (cm)</label>
                            <input type="number" step="0.01" min="0" name="length_cm" value="{{ old('length_cm', $book->length_cm ?? '') }}" class="input mt-1" placeholder="0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Width (cm)</label>
                            <input type="number" step="0.01" min="0" name="width_cm" value="{{ old('width_cm', $book->width_cm ?? '') }}" class="input mt-1" placeholder="0">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Height (cm)</label>
                            <input type="number" step="0.01" min="0" name="height_cm" value="{{ old('height_cm', $book->height_cm ?? '') }}" class="input mt-1" placeholder="0">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Pickup Address</label>
                        <select name="address_id" class="input mt-1">
                            <option value="">Select a saved address</option>
                            @foreach ($addresses as $addr)
                                <option value="{{ $addr->id }}" {{ (string) old('address_id', $book->address_id ?? '') === (string) $addr->id ? 'selected' : '' }}>
                                    {{ $addr->label ? $addr->label.' — ' : '' }}{{ $addr->address_line }}, {{ $addr->city }}{{ $addr->is_default ? ' (Default)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($addresses->isEmpty())
                            <p class="mt-1 text-[11px]" style="color:#6b90aa;">No saved addresses yet. Add one in your account settings.</p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- ════════ SIDEBAR: STATUS + ACTIONS ════════ --}}
            <div class="space-y-6">
                <section class="card p-6 space-y-4 lg:sticky lg:top-6">
                    <h2 class="font-display text-lg font-semibold" style="color:#002b4d;">Publish</h2>

                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Status</label>
                        <select name="status" class="input mt-1" x-model="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="out_of_stock">Out of Stock</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button type="submit" @click="action='publish'"
                                class="w-full rounded-xl py-3 text-sm font-semibold text-white transition hover:opacity-90" style="background:#002b4d;">
                            {{ $isEdit ? 'Save Changes' : 'Publish Product' }}
                        </button>
                        <button type="button" @click="openPreview()"
                                class="w-full rounded-xl border py-3 text-sm font-semibold transition"
                                style="border-color:#fa4e1c;color:#fa4e1c;">Preview</button>
                        <button type="submit" @click="action='draft'"
                                class="w-full rounded-xl border py-3 text-sm font-semibold transition"
                                style="border-color:#cfdce8;color:#6b90aa;">Save as Draft</button>
                    </div>
                    <p class="text-[11px]" style="color:#6b90aa;">A unique Product ID (ALVY-000000) is generated automatically on publish.</p>
                </section>
            </div>
        </div>
    </form>

    {{-- ════════ PREVIEW MODAL ════════ --}}
    <div x-show="preview" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,43,77,.55);" @click.self="preview=false">
        <div class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b px-5 py-3" style="border-color:#cfdce8;">
                <h3 class="font-display font-semibold" style="color:#002b4d;">Product Preview</h3>
                <button type="button" @click="preview=false" class="text-2xl leading-none" style="color:#6b90aa;">&times;</button>
            </div>
            <div class="grid gap-6 p-6 sm:grid-cols-2">
                <div>
                    <div class="aspect-square overflow-hidden rounded-xl border" style="border-color:#cfdce8;">
                        <img :src="previewImage" x-show="previewImage" class="h-full w-full object-cover">
                        <div x-show="!previewImage" class="flex h-full items-center justify-center text-sm" style="color:#6b90aa;">No image</div>
                    </div>
                </div>
                <div class="space-y-3">
                    <p class="text-xs font-semibold uppercase" style="color:#fa4e1c;" x-text="categoryName || 'Category'"></p>
                    <h4 class="font-display text-xl font-bold" style="color:#002b4d;" x-text="title || 'Product name'"></h4>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl font-bold" style="color:#fa4e1c;" x-text="'₱' + displayPrice"></span>
                        <span x-show="salePrice && Number(salePrice) < Number(price)" class="text-sm line-through" style="color:#6b90aa;" x-text="'₱' + Number(price).toFixed(2)"></span>
                    </div>
                    <p class="text-sm" style="color:#6b90aa;" x-text="brandLabel"></p>
                    <p class="text-sm" style="color:#374151;" x-text="description || 'No description yet.'"></p>
                    <p class="text-xs" style="color:#6b90aa;">Stock: <span x-text="variations.length ? totalVarStock : (stock || 0)"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important;}</style>
<script src="//unpkg.com/alpinejs" defer></script>
<script>
    // Reorder existing images (native drag) + keep hidden image_order inputs & MAIN badge in sync.
    function initReorder() {
        const grid = document.getElementById('existing-images');
        if (!grid) return;
        let dragEl = null;
        grid.querySelectorAll('.ex-img').forEach(el => {
            el.setAttribute('draggable', 'true');
            el.addEventListener('dragstart', () => { dragEl = el; el.style.opacity = '.4'; });
            el.addEventListener('dragend',   () => { el.style.opacity = '1'; syncMain(); });
            el.addEventListener('dragover', e => {
                e.preventDefault();
                const after = [...grid.querySelectorAll('.ex-img:not([style*="opacity: 0.4"])')]
                    .find(c => e.clientY <= c.getBoundingClientRect().top + c.offsetHeight / 2
                            && e.clientX <= c.getBoundingClientRect().left + c.offsetWidth / 2);
                if (dragEl && dragEl !== el) grid.insertBefore(dragEl, after || null);
            });
        });
        syncMain();
    }
    function syncMain() {
        const grid = document.getElementById('existing-images');
        if (!grid) return;
        const items = [...grid.querySelectorAll('.ex-img')];
        items.forEach((el, idx) => {
            const badge = el.querySelector('.main-badge');
            if (badge) badge.classList.toggle('hidden', idx !== 0);
            const hidden = el.querySelector('input[name="image_order[]"]');
            if (hidden) el.appendChild(hidden); // keep DOM order == input order
        });
    }
    function removeExisting(btn, id) {
        const card = btn.closest('.ex-img');
        const cont = document.getElementById('removed-container');
        const inp  = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'removed_images[]'; inp.value = id;
        cont.appendChild(inp);
        card.remove();
        syncMain();
    }
    document.addEventListener('DOMContentLoaded', initReorder);

    function productForm() {
        const subMap     = @json($subcategoryMap);
        const specSchema = @json($specSchemas);
        return {
            action: 'publish',
            preview: false,
            categoryId: @json((string) $selectedCat),
            categoryName: @json($selectedName ?? ''),
            subcategory: @json($currentSub),
            subs: [],
            noBrand: @json(old('brand', $book->brand ?? null) === 'No Brand'),
            price: @json((string) old('price', $book->price ?? '')),
            salePrice: @json((string) old('sale_price', $book->sale_price ?? '')),
            stock: @json((string) old('stock', $book->stock ?? '')),
            status: @json($currentStatus),
            variations: @json(array_values($existingVars ?: [])),
            specFields: [],
            specValues: @json((object) $currentSpecs),
            previewImage: @json($isEdit && $book->image ? asset('storage/'.$book->image) : ''),
            title: @json(old('title', $book->title ?? '')),
            brand: @json(old('brand', $book->brand ?? '')),
            description: @json(old('description', $book->description ?? '')),

            init() {
                this.refreshCategory();
                // Keep preview/title/brand/description live from inputs.
                const form = document.getElementById('product-form');
                form.querySelector('[name=title]').addEventListener('input', e => this.title = e.target.value);
                form.querySelector('[name=description]').addEventListener('input', e => this.description = e.target.value);
                form.querySelector('[name=brand]').addEventListener('input', e => this.brand = e.target.value);
            },
            get totalVarStock() {
                return this.variations.reduce((s, v) => s + (parseInt(v.stock) || 0), 0);
            },
            get saleError() {
                return this.salePrice !== '' && this.price !== '' && Number(this.salePrice) > Number(this.price);
            },
            get displayPrice() {
                const p = Number(this.price) || 0;
                const s = Number(this.salePrice) || 0;
                return (s > 0 && s < p ? s : p).toFixed(2);
            },
            get brandLabel() {
                return this.noBrand ? 'No Brand' : (this.brand || '');
            },
            refreshCategory() {
                const sel = document.querySelector('[name=category_id]');
                const opt = sel.options[sel.selectedIndex];
                this.categoryName = opt ? (opt.dataset.name || '') : '';
                this.subs = subMap[this.categoryName] || [];
                this.specFields = this.resolveSpecFields(this.categoryName);
            },
            onCategoryChange() {
                this.subcategory = '';
                this.refreshCategory();
            },
            resolveSpecFields(name) {
                if (!name) return [];
                const lower = name.toLowerCase();
                for (const key in specSchema) {
                    if (lower.includes(key)) return specSchema[key];
                }
                return [];
            },
            addVariation() {
                this.variations.push({ name: '', price: '', stock: '', sku: '' });
                this.status = this.status; // no-op keep reactive
                this.$nextTick(() => { this.stock = String(this.totalVarStock); });
            },
            removeVariation(i) {
                this.variations.splice(i, 1);
                this.stock = this.variations.length ? String(this.totalVarStock) : this.stock;
            },
            previewNew(e) {
                const wrap = document.getElementById('new-previews');
                wrap.innerHTML = '';
                [...e.target.files].forEach((file, idx) => {
                    const reader = new FileReader();
                    reader.onload = ev => {
                        const d = document.createElement('div');
                        d.className = 'relative aspect-square overflow-hidden rounded-xl border';
                        d.style.borderColor = '#cfdce8';
                        d.innerHTML = `<img src="${ev.target.result}" class="h-full w-full object-cover">` +
                            (idx === 0 && !this.hasExisting() ? '<span class="absolute bottom-1 left-1 rounded px-1 py-0.5 text-[9px] font-bold text-white" style="background:#fa4e1c;">MAIN</span>' : '');
                        wrap.appendChild(d);
                    };
                    reader.readAsDataURL(file);
                    if (idx === 0 && !this.hasExisting()) {
                        const r2 = new FileReader();
                        r2.onload = ev => this.previewImage = ev.target.result;
                        r2.readAsDataURL(file);
                    }
                });
            },
            hasExisting() {
                const g = document.getElementById('existing-images');
                return g && g.querySelectorAll('.ex-img').length > 0;
            },
            openPreview() {
                if (this.variations.length) this.stock = String(this.totalVarStock);
                this.preview = true;
            },
        };
    }
</script>
</x-seller-layout>
