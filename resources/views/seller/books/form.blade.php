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
    $maxPhotos    = \App\Models\Product::MAX_IMAGES;
    $existingImgs = $isEdit ? $book->images : collect();
@endphp
<style>
    [x-cloak] { display: none !important; }
    .pf-section { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; padding: 1.25rem 1.5rem; }
    .pf-section h2 { font-size: 1rem; font-weight: 600; color: #222; }
    .pf-hint { font-size: .8125rem; color: #8a7f78; }
    .pf-label { display: block; margin-bottom: .3rem; font-size: .8125rem; font-weight: 500; color: #44403C; }
    .pf-label .opt { font-weight: 400; color: #a39890; }
    .pf-note { margin-top: .3rem; font-size: .75rem; color: #8a7f78; }

    .photo-tile { position: relative; aspect-ratio: 1; overflow: hidden; border-radius: .5rem; border: 1px solid #E7E5E4; background: #FAFAF9; }
    .photo-tile img { width: 100%; height: 100%; object-fit: cover; }
    .photo-tile .cover-tag { position: absolute; left: .35rem; bottom: .35rem; padding: .05rem .4rem; border-radius: .25rem; font-size: .65rem; font-weight: 600; color: #fff; background: rgba(0, 0, 0, .65); }
    .photo-tile .remove-btn { position: absolute; right: .3rem; top: .3rem; display: flex; height: 1.4rem; width: 1.4rem; align-items: center; justify-content: center; border-radius: 9999px; background: rgba(0, 0, 0, .55); color: #fff; font-size: .9rem; line-height: 1; opacity: 0; transition: opacity .15s; }
    .photo-tile:hover .remove-btn, .photo-tile .remove-btn:focus { opacity: 1; }
    .photo-tile.ex-img { cursor: grab; }
    .photo-add { display: flex; aspect-ratio: 1; flex-direction: column; align-items: center; justify-content: center; gap: .25rem; border: 1.5px dashed #D6D3D1; border-radius: .5rem; color: #78716C; font-size: .75rem; font-weight: 500; cursor: pointer; transition: border-color .15s, color .15s; }
    .photo-add:hover { border-color: #fa4e1c; color: #fa4e1c; }

    .file-input { width: 100%; font-size: .8125rem; color: #57534E; }
    .file-input::file-selector-button { margin-right: .75rem; border: 1px solid #E7E5E4; border-radius: .375rem; background: #fff; padding: .4rem .8rem; font-size: .8125rem; font-weight: 500; color: #44403C; cursor: pointer; }
    .file-input::file-selector-button:hover { background: #FAFAF9; }

    .btn-solid { display: block; width: 100%; border-radius: .375rem; padding: .6rem 1rem; font-size: .875rem; font-weight: 600; color: #fff; background: #fa4e1c; transition: opacity .15s; }
    .btn-solid:hover { opacity: .9; }
    .btn-line { display: block; width: 100%; border: 1px solid #E7E5E4; border-radius: .375rem; padding: .55rem 1rem; font-size: .875rem; font-weight: 500; color: #44403C; background: #fff; transition: background .15s; }
    .btn-line:hover { background: #FAFAF9; }
</style>

<div x-data="productForm()" x-init="init()" class="mx-auto max-w-6xl" @main-photo.window="previewImage = $event.detail">

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('seller.books.index') }}" class="text-sm hover:underline" style="color:#8a7f78;">&larr; Inventory</a>
            <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222;">{{ $isEdit ? 'Edit product' : 'Add a product' }}</h1>
            @if ($isEdit && $book->product_code)
                <p class="mt-0.5 text-sm" style="color:#8a7f78;">Product ID {{ $book->product_code }}</p>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
            <p class="font-medium">Please fix the following:</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $isEdit ? route('seller.books.update', $book->id) : route('seller.books.store') }}"
          method="POST" enctype="multipart/form-data" id="product-form">
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <input type="hidden" name="action" x-model="action">

        <div class="grid gap-5 lg:grid-cols-[1fr_18rem]">
            <div class="space-y-5">

                {{-- ════════ PHOTOS ════════ --}}
                <section class="pf-section">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2>Photos</h2>
                        <span class="pf-hint"><span id="photo-count">{{ $existingImgs->count() }}</span> of {{ $maxPhotos }}</span>
                    </div>
                    <p class="pf-hint mb-4">
                        The first photo is the cover buyers see first. Drag photos to reorder.
                        JPG, PNG or WebP, up to 5 MB each.
                    </p>

                    <div id="photo-grid" class="grid grid-cols-3 gap-3 sm:grid-cols-5">
                        @foreach ($existingImgs as $img)
                            <div class="photo-tile ex-img" data-id="{{ $img->id }}">
                                <img src="{{ $img->url() }}" alt="">
                                <input type="hidden" name="image_order[]" value="{{ $img->id }}">
                                <span class="cover-tag hidden">Cover</span>
                                <button type="button" class="remove-btn" aria-label="Remove photo" onclick="removeExisting(this, {{ $img->id }})">&times;</button>
                            </div>
                        @endforeach
                        <label id="photo-add" class="photo-add" for="photo-picker">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                            Add photos
                        </label>
                    </div>
                    <input type="file" id="photo-picker" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                    <input type="file" id="photo-files" name="images[]" multiple class="hidden">
                    <div id="removed-container"></div>
                    <p id="photo-error" class="mt-2 hidden text-sm" style="color:#B91C1C;"></p>
                    @if ($existingImgs->isNotEmpty())
                        <p class="pf-note">New photos are added after your current ones.</p>
                    @endif

                    <div class="mt-5 border-t pt-4" style="border-color:#F2EEEA;">
                        <label class="pf-label" for="video">Video <span class="opt">— optional, MP4, MOV or WebM up to 20 MB</span></label>
                        <input type="file" id="video" name="video" accept="video/mp4,video/quicktime,video/webm" class="file-input">
                        @if ($isEdit && $book->video_path)
                            <p class="pf-note">Current video: {{ basename($book->video_path) }}</p>
                        @endif
                    </div>
                </section>

                {{-- ════════ BASIC INFO ════════ --}}
                <section class="pf-section">
                    <h2 class="mb-4">Details</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="pf-label" for="title">Product name</label>
                            <input type="text" id="title" name="title" value="{{ old('title', $book->title ?? '') }}" class="input" placeholder="e.g. Cotton Oversized T-Shirt">
                        </div>

                        <div>
                            <label class="pf-label" for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="input" x-model="categoryId" @change="onCategoryChange()">
                                <option value="">Select category</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}" data-name="{{ $cat->name }}"
                                        {{ (string) $selectedCat === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="pf-label" for="subcategory">Subcategory <span class="opt">— optional</span></label>
                            <select id="subcategory" name="subcategory" class="input" x-model="subcategory" :disabled="subs.length === 0">
                                <option value="" x-text="subs.length ? 'Select subcategory' : 'None for this category'"></option>
                                <template x-for="sub in subs" :key="sub">
                                    <option :value="sub" x-text="sub"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="pf-label" for="brand">Brand</label>
                            <input type="text" id="brand" name="brand" value="{{ old('brand', $book->brand ?? '') }}" class="input" placeholder="Brand name" x-ref="brand" :disabled="noBrand">
                            <label class="mt-1.5 inline-flex items-center gap-2 text-sm" style="color:#57534E;">
                                <input type="checkbox" x-model="noBrand" @change="if(noBrand) $refs.brand.value=''" style="accent-color:#fa4e1c;"> No brand
                            </label>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="pf-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="5" class="input" maxlength="5000"
                                      placeholder="Features, materials, size, condition…">{{ old('description', $book->description ?? '') }}</textarea>
                        </div>
                    </div>
                </section>

                {{-- ════════ PRICING & INVENTORY ════════ --}}
                <section class="pf-section">
                    <h2 class="mb-4">Price and stock</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="pf-label" for="price">Price (₱)</label>
                            <input type="number" id="price" step="0.01" min="0" name="price" x-model="price" class="input" placeholder="0.00">
                        </div>
                        <div>
                            <label class="pf-label" for="sale_price">Sale price (₱) <span class="opt">— optional</span></label>
                            <input type="number" id="sale_price" step="0.01" min="0" name="sale_price" x-model="salePrice" class="input" placeholder="—">
                            <p class="pf-note" x-show="saleError" style="color:#B91C1C;">Must be less than or equal to the price.</p>
                        </div>
                        <div>
                            <label class="pf-label" for="stock">Stock</label>
                            <input type="number" id="stock" min="0" name="stock" x-model="stock" class="input" placeholder="0" :disabled="variations.length > 0">
                            <p class="pf-note" x-show="variations.length > 0">Total of variations: <span x-text="totalVarStock"></span></p>
                        </div>
                        <div>
                            <label class="pf-label" for="sku">SKU <span class="opt">— optional</span></label>
                            <input type="text" id="sku" name="sku" value="{{ old('sku', $book->sku ?? '') }}" class="input" placeholder="Your own code">
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="pf-label" for="discount_percent">Discount % <span class="opt">— optional</span></label>
                            <input type="number" id="discount_percent" step="0.01" min="0" max="99" name="discount_percent" value="{{ old('discount_percent', $book->discount_percent ?? '') }}" class="input" placeholder="e.g. 10">
                        </div>
                        <div>
                            <span class="pf-label">Vouchers</span>
                            <p class="pf-note" style="margin-top:0;">
                                Store vouchers (e.g. ₱150 off a ₱2,000 spend) apply to all your products.
                                <a href="{{ route('seller.vouchers.index') }}" class="font-semibold" style="color:#fa4e1c;">Manage vouchers →</a>
                            </p>
                        </div>
                    </div>
                </section>

                {{-- ════════ VARIATIONS ════════ --}}
                <section class="pf-section">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2>Variations</h2>
                            <p class="pf-hint mt-1">Options like colour or size, each with its own price and stock. Skip this for a single product.</p>
                        </div>
                        <button type="button" @click="addVariation()" class="rounded-md border px-3 py-1.5 text-sm font-medium transition hover:bg-stone-50" style="border-color:#E7E5E4;color:#44403C;">Add variation</button>
                    </div>

                    <template x-if="variations.length > 0">
                        <div class="mt-4 overflow-hidden rounded-md border" style="border-color:#ECE7E3;">
                            <div class="hidden grid-cols-12 gap-2 px-3 py-2 text-xs font-medium sm:grid" style="background:#FAFAF9;color:#78716C;">
                                <span class="col-span-4">Option</span><span class="col-span-3">Price (₱)</span><span class="col-span-2">Stock</span><span class="col-span-2">SKU</span><span class="col-span-1"></span>
                            </div>
                            <template x-for="(v, i) in variations" :key="i">
                                <div class="grid grid-cols-12 items-center gap-2 border-t px-3 py-2" style="border-color:#F2EEEA;">
                                    <input type="text" :name="`variations[${i}][name]`" x-model="v.name" class="input col-span-12 sm:col-span-4" placeholder="e.g. Black / S" aria-label="Option">
                                    <input type="number" step="0.01" min="0" :name="`variations[${i}][price]`" x-model="v.price" class="input col-span-4 sm:col-span-3" placeholder="Same as base" aria-label="Price">
                                    <input type="number" min="0" :name="`variations[${i}][stock]`" x-model="v.stock" class="input col-span-4 sm:col-span-2" placeholder="0" aria-label="Stock">
                                    <input type="text" :name="`variations[${i}][sku]`" x-model="v.sku" class="input col-span-3 sm:col-span-2" placeholder="—" aria-label="SKU">
                                    <button type="button" @click="removeVariation(i)" class="col-span-1 justify-self-center text-sm hover:underline" style="color:#B91C1C;" aria-label="Remove variation">Remove</button>
                                </div>
                            </template>
                        </div>
                    </template>
                </section>

                {{-- ════════ SPECIFICATIONS ════════ --}}
                <section class="pf-section" x-show="specFields.length > 0" x-cloak>
                    <h2>Specifications</h2>
                    <p class="pf-hint mt-1 mb-4">Details buyers look for in this category.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <template x-for="field in specFields" :key="field">
                            <div>
                                <label class="pf-label" x-text="field"></label>
                                <input type="text" :name="`specs[${field}]`" class="input"
                                       :value="specValues[field] ?? ''" @input="specValues[field] = $event.target.value">
                            </div>
                        </template>
                    </div>
                </section>

                {{-- ════════ SHIPPING ════════ --}}
                <section class="pf-section">
                    <h2 class="mb-4">Shipping</h2>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="pf-label" for="weight_kg">Weight (kg)</label>
                            <input type="number" id="weight_kg" step="0.01" min="0" name="weight_kg" value="{{ old('weight_kg', $book->weight_kg ?? '') }}" class="input" placeholder="0.00">
                        </div>
                        <div>
                            <label class="pf-label" for="length_cm">Length (cm)</label>
                            <input type="number" id="length_cm" step="0.01" min="0" name="length_cm" value="{{ old('length_cm', $book->length_cm ?? '') }}" class="input" placeholder="0">
                        </div>
                        <div>
                            <label class="pf-label" for="width_cm">Width (cm)</label>
                            <input type="number" id="width_cm" step="0.01" min="0" name="width_cm" value="{{ old('width_cm', $book->width_cm ?? '') }}" class="input" placeholder="0">
                        </div>
                        <div>
                            <label class="pf-label" for="height_cm">Height (cm)</label>
                            <input type="number" id="height_cm" step="0.01" min="0" name="height_cm" value="{{ old('height_cm', $book->height_cm ?? '') }}" class="input" placeholder="0">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="pf-label" for="address_id">Pickup address</label>
                        <select id="address_id" name="address_id" class="input">
                            <option value="">Select a saved address</option>
                            @foreach ($addresses as $addr)
                                <option value="{{ $addr->id }}" {{ (string) old('address_id', $book->address_id ?? '') === (string) $addr->id ? 'selected' : '' }}>
                                    {{ $addr->label ? $addr->label.' — ' : '' }}{{ $addr->address_line }}, {{ $addr->city }}{{ $addr->is_default ? ' (Default)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @if ($addresses->isEmpty())
                            <p class="pf-note">No saved addresses yet. Add one in your account settings.</p>
                        @endif
                    </div>
                </section>
            </div>

            {{-- ════════ SIDE: STATUS + ACTIONS ════════ --}}
            <aside>
                <section class="pf-section lg:sticky lg:top-6">
                    <label class="pf-label" for="status">Status</label>
                    <select id="status" name="status" class="input" x-model="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="out_of_stock">Out of stock</option>
                        <option value="draft">Draft</option>
                    </select>
                    <p class="pf-note">Only active products are shown to buyers.</p>

                    <div class="mt-5 space-y-2">
                        <button type="submit" @click="action='publish'" class="btn-solid">
                            {{ $isEdit ? 'Save changes' : 'Publish product' }}
                        </button>
                        <button type="submit" @click="action='draft'" class="btn-line">Save as draft</button>
                        <button type="button" @click="openPreview()" class="w-full py-1.5 text-sm font-medium hover:underline" style="color:#57534E;">Preview</button>
                    </div>
                </section>
            </aside>
        </div>
    </form>

    {{-- ════════ PREVIEW MODAL ════════ --}}
    <div x-show="preview" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(28,25,23,.5);" @click.self="preview=false" @keydown.escape.window="preview=false">
        <div class="w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b px-5 py-3" style="border-color:#ECE7E3;">
                <h3 class="font-semibold" style="color:#222;">Preview</h3>
                <button type="button" @click="preview=false" class="text-2xl leading-none" style="color:#8a7f78;" aria-label="Close">&times;</button>
            </div>
            <div class="grid gap-6 p-6 sm:grid-cols-2">
                <div class="aspect-square overflow-hidden rounded-lg border" style="border-color:#ECE7E3;">
                    <img :src="previewImage" x-show="previewImage" class="h-full w-full object-cover" alt="">
                    <div x-show="!previewImage" class="flex h-full items-center justify-center text-sm" style="color:#8a7f78;">No photo</div>
                </div>
                <div class="space-y-3">
                    <p class="text-sm" style="color:#8a7f78;" x-text="categoryName || 'Category'"></p>
                    <h4 class="font-display text-xl font-bold" style="color:#222;" x-text="title || 'Product name'"></h4>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold" style="color:#fa4e1c;" x-text="'₱' + displayPrice"></span>
                        <span x-show="salePrice && Number(salePrice) < Number(price)" class="text-sm line-through" style="color:#8a7f78;" x-text="'₱' + Number(price).toFixed(2)"></span>
                    </div>
                    <p class="text-sm" style="color:#57534E;" x-text="brandLabel"></p>
                    <p class="whitespace-pre-line text-sm" style="color:#44403C;" x-text="description || 'No description yet.'"></p>
                    <p class="text-sm" style="color:#8a7f78;">Stock: <span x-text="variations.length ? totalVarStock : (stock || 0)"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="//unpkg.com/alpinejs" defer></script>
<script>
// ── Photos: existing (reorder/remove) + new uploads, max {{ $maxPhotos }} in total ──────────
(function () {
    const MAX     = {{ $maxPhotos }};
    const MAX_MB  = 5;
    const TYPES   = ['image/jpeg', 'image/png', 'image/webp'];
    const grid    = document.getElementById('photo-grid');
    const addTile = document.getElementById('photo-add');
    const picker  = document.getElementById('photo-picker');
    const field   = document.getElementById('photo-files');   // the input that is actually submitted
    const counter = document.getElementById('photo-count');
    const errorEl = document.getElementById('photo-error');
    let newFiles  = [];

    const existingTiles = () => [...grid.querySelectorAll('.ex-img')];

    function showError(msg) {
        errorEl.textContent = msg || '';
        errorEl.classList.toggle('hidden', !msg);
    }

    function syncField() {
        const dt = new DataTransfer();
        newFiles.forEach(f => dt.items.add(f.file));
        field.files = dt.files;
    }

    function refresh() {
        const tiles = [...grid.querySelectorAll('.photo-tile')];
        tiles.forEach((t, i) => t.querySelector('.cover-tag')?.classList.toggle('hidden', i !== 0));
        // Keep hidden order inputs in DOM order.
        existingTiles().forEach(t => { const h = t.querySelector('input[name="image_order[]"]'); if (h) t.appendChild(h); });
        const total = tiles.length;
        counter.textContent = total;
        addTile.classList.toggle('hidden', total >= MAX);
        const first = tiles[0]?.querySelector('img')?.src || '';
        window.dispatchEvent(new CustomEvent('main-photo', { detail: first }));
    }

    function newTile(entry) {
        const d = document.createElement('div');
        d.className = 'photo-tile new-img';
        d.innerHTML = `<img src="${entry.url}" alt=""><span class="cover-tag hidden">Cover</span>` +
                      `<button type="button" class="remove-btn" aria-label="Remove photo">&times;</button>`;
        d.querySelector('.remove-btn').addEventListener('click', () => {
            URL.revokeObjectURL(entry.url);
            newFiles = newFiles.filter(f => f !== entry);
            d.remove();
            syncField();
            showError('');
            refresh();
        });
        grid.insertBefore(d, addTile);
    }

    picker.addEventListener('change', () => {
        const room = MAX - grid.querySelectorAll('.photo-tile').length;
        const chosen = [...picker.files];
        const problems = [];
        let added = 0;

        chosen.forEach(file => {
            if (!TYPES.includes(file.type))          { problems.push(`${file.name} isn't a JPG, PNG or WebP image`); return; }
            if (file.size > MAX_MB * 1024 * 1024)    { problems.push(`${file.name} is larger than ${MAX_MB} MB`); return; }
            if (added >= room)                       { return; }
            const entry = { file, url: URL.createObjectURL(file) };
            newFiles.push(entry);
            newTile(entry);
            added++;
        });

        const skipped = chosen.length - added - problems.length;
        if (skipped > 0) problems.push(`Only ${MAX} photos are allowed — ${skipped} ${skipped === 1 ? 'photo was' : 'photos were'} not added`);

        picker.value = '';
        syncField();
        showError(problems.join('. ') + (problems.length ? '.' : ''));
        refresh();
    });

    // Drag to reorder saved photos.
    let dragEl = null;
    existingTiles().forEach(el => {
        el.setAttribute('draggable', 'true');
        el.addEventListener('dragstart', () => { dragEl = el; el.style.opacity = '.4'; });
        el.addEventListener('dragend',   () => { el.style.opacity = '1'; dragEl = null; refresh(); });
        el.addEventListener('dragover', e => {
            e.preventDefault();
            if (!dragEl || dragEl === el) return;
            const r = el.getBoundingClientRect();
            const before = (e.clientX - r.left) < r.width / 2;
            grid.insertBefore(dragEl, before ? el : el.nextSibling);
        });
    });

    window.removeExisting = function (btn, id) {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'removed_images[]'; inp.value = id;
        document.getElementById('removed-container').appendChild(inp);
        btn.closest('.ex-img').remove();
        showError('');
        refresh();
    };

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('alpine:initialized', refresh);
})();

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
            this.$nextTick(() => { this.stock = String(this.totalVarStock); });
        },
        removeVariation(i) {
            this.variations.splice(i, 1);
            this.stock = this.variations.length ? String(this.totalVarStock) : this.stock;
        },
        openPreview() {
            if (this.variations.length) this.stock = String(this.totalVarStock);
            this.preview = true;
        },
    };
}
</script>
</x-seller-layout>
