<x-seller-layout :title="isset($book) ? 'Edit: '.$book->title : 'Add Product'" active="products">
@php
    $isEdit       = isset($book);
    $selectedCat  = old('category_id', $book->category_id ?? '');
    $selectedName = optional($categories->firstWhere('id', (int) $selectedCat))->name;
    $currentSpecs = old('specs', $book->specs ?? []);
    $currentSub   = old('subcategory', $book->subcategory ?? '');
    $currentStatus= old('status', $book->status ?? 'active');
    $existingVars = collect(old('variations', $isEdit ? $book->variations->map(fn($v) => [
        'id' => $v->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock, 'sku' => $v->sku,
        'image' => $v->image_path ? asset('storage/' . $v->image_path) : null,
    ])->toArray() : []))->map(fn ($v) => array_merge(['id' => null, 'name' => '', 'price' => '', 'stock' => '', 'sku' => '', 'image' => null], (array) $v))->values()->all();
    // Variation types ("Color: Red, Blue" / "Size: S, M, L") rebuilt from the saved option names.
    if (old('variation_types')) {
        $existingTypes = collect(old('variation_types'))->map(fn ($t) => ['name' => $t['name'] ?? '', 'options' => array_values((array) ($t['options'] ?? []))])->values()->all();
    } elseif ($isEdit && $book->variations->isNotEmpty()) {
        $labels = $book->variationGroupLabels();
        $existingTypes = collect($book->variationGroups())->map(fn ($opts, $i) => ['name' => $labels[$i] ?? '', 'options' => $opts])->values()->all();
    } else {
        $existingTypes = [];
    }
    $canPublish     = auth()->user()->isApproved() || auth()->user()->isAdmin();
    $selectedStoreCats = array_map('intval', (array) old('store_category_ids', $isEdit ? $book->storeCategories()->pluck('store_categories.id')->all() : []));
    $discountValue  = old('discount_percent', ($book->discount_percent ?? 0) > 0 ? rtrim(rtrim(number_format($book->discount_percent, 2, '.', ''), '0'), '.') : '');
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
        @unless ($isEdit)<input type="hidden" name="form_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">@endunless

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
                        1 to {{ $maxPhotos }} photos, JPG or PNG, up to 5 MB each.
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
                    <input type="file" id="photo-picker" accept="image/jpeg,image/png" multiple class="hidden">
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
                            <input type="text" id="title" name="title" minlength="5" maxlength="120" value="{{ old('title', $book->title ?? '') }}" class="input" placeholder="e.g. Cotton Oversized T-Shirt">
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

                        <div>
                            <label class="pf-label" for="condition">Condition</label>
                            <select id="condition" name="condition" class="input">
                                @foreach ($conditions as $c)
                                    <option value="{{ $c }}" @selected(old('condition', $book->condition ?? 'New') === $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <span class="pf-label">Store categories <span class="opt">— optional, up to 3; separate from the platform category</span></span>
                            <input type="hidden" name="store_categories_present" value="1">
                            @if ($storeCategories->isEmpty())
                                <p class="pf-note" style="margin-top:0;">No store categories yet. <a href="{{ route('seller.store-categories.index') }}" class="font-semibold" style="color:#fa4e1c;">Create one →</a></p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($storeCategories as $sc)
                                        @foreach (collect([$sc])->merge($sc->children) as $opt)
                                            <label class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-sm" style="border-color:#E7E5E4;">
                                                <input type="checkbox" name="store_category_ids[]" value="{{ $opt->id }}" @checked(in_array($opt->id, $selectedStoreCats, true)) style="accent-color:#fa4e1c;">
                                                {{ $opt->parent_id ? $sc->name . ' › ' : '' }}{{ $opt->name }}
                                            </label>
                                        @endforeach
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="sm:col-span-2">
                            <label class="pf-label" for="description">Description</label>
                            <textarea id="description" name="description" rows="5" class="input" minlength="20" maxlength="3000"
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
                            <input type="number" id="price" step="0.01" min="1" max="1000000" name="price" x-model="price" class="input" placeholder="1.00 – 1,000,000.00">
                        </div>
                        <div>
                            <label class="pf-label" for="sale_price">Sale price (₱) <span class="opt">— optional</span></label>
                            <input type="number" id="sale_price" step="0.01" min="1" name="sale_price" x-model="salePrice" class="input" placeholder="—">
                            <p class="pf-note" x-show="saleError" style="color:#B91C1C;">Must be lower than the price.</p>
                        </div>
                        <div>
                            <label class="pf-label" for="stock">Stock</label>
                            <input type="number" id="stock" min="0" max="99999" step="1" name="stock" x-model="stock" class="input" placeholder="0" :disabled="variations.length > 0">
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
                            <input type="number" id="discount_percent" step="0.01" min="1" max="99" name="discount_percent" value="{{ $discountValue }}" class="input" placeholder="1 – 99">
                            <p class="pf-note">Applies to every variant's price.</p>
                        </div>
                        <div>
                            <label class="pf-label" for="sale_starts_at">Sale starts <span class="opt">— optional, blank = now</span></label>
                            <input type="datetime-local" id="sale_starts_at" name="sale_starts_at" value="{{ old('sale_starts_at', optional($book->sale_starts_at ?? null)->format('Y-m-d\TH:i')) }}" class="input">
                        </div>
                        <div>
                            <label class="pf-label" for="sale_ends_at">Sale ends <span class="opt">— optional</span></label>
                            <input type="datetime-local" id="sale_ends_at" name="sale_ends_at" value="{{ old('sale_ends_at', optional($book->sale_ends_at ?? null)->format('Y-m-d\TH:i')) }}" class="input">
                            <p class="pf-note">Buyers see a countdown; the sale price / discount stops at this time.</p>
                            @if ($isEdit && in_array($book->saleState(), ['Upcoming', 'Ongoing'], true))
                                <p class="pf-note"><span class="font-semibold">Sale: {{ $book->saleState() }}.</span>
                                    <button type="submit" form="end-sale-form" class="font-semibold underline" style="color:#B91C1C;">End sale now</button></p>
                            @endif
                        </div>
                        <div>
                            <label class="pf-label" for="purchase_limit">Limit per buyer <span class="opt">— optional</span></label>
                            <input type="number" id="purchase_limit" min="1" max="99999" name="purchase_limit" value="{{ old('purchase_limit', $book->purchase_limit ?? '') }}" class="input" placeholder="No limit">
                            <p class="pf-note">Most units one buyer can buy in total (all their orders).</p>
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
                            <p class="pf-hint mt-1">Up to {{ \App\Models\ProductVariation::MAX_TYPES }} types (e.g. Color and Size) with up to {{ \App\Models\ProductVariation::MAX_OPTIONS }} options each.
                                Every combination gets its own price, stock, SKU and photo. Skip this for a single product.</p>
                        </div>
                        <button type="button" @click="addType()" :disabled="types.length >= {{ \App\Models\ProductVariation::MAX_TYPES }}" class="rounded-md border px-3 py-1.5 text-sm font-medium transition hover:bg-stone-50 disabled:opacity-50" style="border-color:#E7E5E4;color:#44403C;">Add variation type</button>
                    </div>
                    <p class="pf-note" x-show="types.length >= {{ \App\Models\ProductVariation::MAX_TYPES }}" style="color:#B45309;">The maximum is {{ \App\Models\ProductVariation::MAX_TYPES }} variation types.</p>

                    <template x-for="(t, ti) in types" :key="ti">
                        <div class="mt-3 rounded-md border p-3" style="border-color:#ECE7E3;">
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="text" :name="`variation_types[${ti}][name]`" x-model="t.name" maxlength="30" class="input max-w-[12rem]" placeholder="Type, e.g. Color" aria-label="Variation type">
                                <button type="button" @click="removeType(ti)" class="text-sm hover:underline" style="color:#B91C1C;">Remove type</button>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="(o, oi) in t.options" :key="oi">
                                    <span class="inline-flex items-center gap-1">
                                        <input type="text" :name="`variation_types[${ti}][options][${oi}]`" x-model="t.options[oi]" maxlength="40" class="input w-28" style="padding:.4rem .6rem;" placeholder="Option" aria-label="Option">
                                        <button type="button" @click="t.options.splice(oi, 1)" class="text-sm" style="color:#a39890;" aria-label="Remove option">&times;</button>
                                    </span>
                                </template>
                                <button type="button" @click="addOption(t)" :disabled="t.options.length >= {{ \App\Models\ProductVariation::MAX_OPTIONS }}" class="rounded-md border border-dashed px-2.5 py-1 text-sm disabled:opacity-50" style="border-color:#D6D3D1;color:#57534E;">+ Option</button>
                            </div>
                            <p class="pf-note" x-show="t.options.length >= {{ \App\Models\ProductVariation::MAX_OPTIONS }}" style="color:#B45309;">The maximum is {{ \App\Models\ProductVariation::MAX_OPTIONS }} options per type.</p>
                            <p class="pf-note" x-show="typeProblem(t)" x-text="typeProblem(t)" style="color:#B91C1C;"></p>
                        </div>
                    </template>
                    <div class="mt-3" x-show="types.length > 0">
                        <button type="button" @click="generate()" class="btn-primary" style="padding:.45rem 1rem;">Generate variant rows</button>
                        <span class="pf-note" x-text="'Makes ' + comboCount + ' combinations.'"></span>
                    </div>

                    <template x-if="variations.length > 0">
                        <div class="mt-4 overflow-hidden rounded-md border" style="border-color:#ECE7E3;">
                            <div class="hidden grid-cols-12 gap-2 px-3 py-2 text-xs font-medium sm:grid" style="background:#FAFAF9;color:#78716C;">
                                <span class="col-span-3">Variant</span><span class="col-span-2">Price (₱)</span><span class="col-span-2">Stock</span><span class="col-span-2">SKU</span><span class="col-span-2">Photo</span><span class="col-span-1"></span>
                            </div>
                            <template x-for="(v, i) in variations" :key="i">
                                <div class="grid grid-cols-12 items-center gap-2 border-t px-3 py-2" style="border-color:#F2EEEA;" :style="rowMissing(v) ? 'background:#FEF2F2' : ''" :data-variant-row="v.name">
                                    <input type="hidden" :name="`variations[${i}][id]`" :value="v.id || ''">
                                    <input type="text" :name="`variations[${i}][name]`" x-model="v.name" class="input col-span-12 sm:col-span-3" placeholder="e.g. Red / S" aria-label="Variant" :readonly="types.length > 0">
                                    <input type="number" step="0.01" min="1" max="1000000" :name="`variations[${i}][price]`" x-model="v.price" class="input col-span-4 sm:col-span-2" placeholder="Price" aria-label="Price">
                                    <input type="number" min="0" max="99999" step="1" :name="`variations[${i}][stock]`" x-model="v.stock" class="input col-span-4 sm:col-span-2" placeholder="0" aria-label="Stock">
                                    <input type="text" :name="`variations[${i}][sku]`" x-model="v.sku" class="input col-span-4 sm:col-span-2" placeholder="—" aria-label="SKU">
                                    <span class="col-span-10 flex items-center gap-1 sm:col-span-2">
                                        <img x-show="v.image" :src="v.image" alt="" class="h-8 w-8 rounded object-cover">
                                        <input type="file" accept="image/jpeg,image/png" :name="`variations[${i}][image]`" class="w-full text-[11px]" aria-label="Variant photo">
                                    </span>
                                    <button type="button" @click="removeVariation(i)" class="col-span-2 justify-self-center text-sm hover:underline sm:col-span-1" style="color:#B91C1C;" aria-label="Remove variant">Remove</button>
                                </div>
                            </template>
                        </div>
                    </template>
                    <button type="button" x-show="types.length === 0" @click="addVariation()" class="mt-3 text-sm font-medium hover:underline" style="color:#57534E;">+ Add a single variant row</button>
                    <input type="hidden" name="variation_labels" :value="types.map(t => t.name).filter(Boolean).join(', ')" x-show="false">
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
                            <label class="pf-label" for="weight_kg">Weight (kg) *</label>
                            <input type="number" id="weight_kg" step="0.01" min="0.01" name="weight_kg" value="{{ old('weight_kg', $book->weight_kg ?? '') }}" class="input" placeholder="0.00">
                        </div>
                        <div>
                            <label class="pf-label" for="length_cm">Length (cm)</label>
                            <input type="number" id="length_cm" step="0.01" min="0.01" name="length_cm" value="{{ old('length_cm', $book->length_cm ?? '') }}" class="input" placeholder="0">
                        </div>
                        <div>
                            <label class="pf-label" for="width_cm">Width (cm)</label>
                            <input type="number" id="width_cm" step="0.01" min="0.01" name="width_cm" value="{{ old('width_cm', $book->width_cm ?? '') }}" class="input" placeholder="0">
                        </div>
                        <div>
                            <label class="pf-label" for="height_cm">Height (cm)</label>
                            <input type="number" id="height_cm" step="0.01" min="0.01" name="height_cm" value="{{ old('height_cm', $book->height_cm ?? '') }}" class="input" placeholder="0">
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
                        <option value="draft">Draft</option>
                    </select>
                    <p class="pf-note">Only active products are shown to buyers.</p>

                    <div class="mt-5 space-y-2">
                        @if ($canPublish)
                            <button type="submit" @click="action='publish'" class="btn-solid">
                                {{ $isEdit ? 'Save changes' : 'Publish product' }}
                            </button>
                        @else
                            <button type="button" disabled class="btn-solid" style="opacity:.5;cursor:not-allowed;" aria-describedby="verify-note">Publish product</button>
                            <p id="verify-note" class="pf-note" style="color:#B45309;">Your store must be verified before you can publish. Save as a draft for now.</p>
                        @endif
                        <button type="submit" @click="action='draft'" class="btn-line">Save as draft</button>
                        <button type="button" @click="openPreview()" class="w-full py-1.5 text-sm font-medium hover:underline" style="color:#57534E;">Preview</button>
                    </div>
                </section>
            </aside>
        </div>
    </form>
    @if ($isEdit)
        <form id="end-sale-form" method="POST" action="{{ route('seller.books.end-sale', $book) }}" class="hidden">@csrf @method('PATCH')</form>
    @endif

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
    const TYPES   = ['image/jpeg', 'image/png'];
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
            if (!TYPES.includes(file.type))          { problems.push(`${file.name} isn't a JPG or PNG image`); return; }
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
        variations: @json($existingVars),
        types: @json($existingTypes),
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
        get comboCount() {
            const lists = this.types.map(t => t.options.filter(o => o.trim() !== ''));
            return lists.length ? lists.reduce((n, l) => n * l.length, 1) : 0;
        },
        addType() {
            if (this.types.length >= {{ \App\Models\ProductVariation::MAX_TYPES }}) return;
            this.types.push({ name: '', options: [''] });
        },
        removeType(i) { this.types.splice(i, 1); },
        addOption(t) {
            if (t.options.length >= {{ \App\Models\ProductVariation::MAX_OPTIONS }}) return;
            t.options.push('');
        },
        typeProblem(t) {
            const seen = new Set();
            for (const o of t.options) {
                const k = o.trim().toLowerCase();
                if (k === '') return 'Option names cannot be empty.';
                if (seen.has(k)) return 'Each option must be different ("' + o.trim() + '" is listed twice).';
                seen.add(k);
            }
            return '';
        },
        rowMissing(v) { return this.action === 'publish' && v.name && (v.price === '' || v.price === null); },
        /** Every combination of the types' options, keeping what was typed for rows that still exist. */
        generate() {
            const lists = this.types.map(t => t.options.map(o => o.trim()).filter(Boolean));
            if (!lists.length || lists.some(l => !l.length)) return;
            let combos = [[]];
            lists.forEach(l => { combos = combos.flatMap(c => l.map(o => [...c, o])); });
            const old = Object.fromEntries(this.variations.map(v => [v.name.toLowerCase(), v]));
            const before = this.variations;
            this.variations = combos.map((c, i) => {
                const name = c.join(' / ');
                if (old[name.toLowerCase()]) return old[name.toLowerCase()];
                // A renamed option (same position): keep that row, so its orders and stock stay with it.
                if (before.length === combos.length && before[i] && !combos.some(x => x.join(' / ').toLowerCase() === before[i].name.toLowerCase())) {
                    return Object.assign({}, before[i], { name });
                }
                return { id: null, name, price: this.price || '', stock: '', sku: '', image: null };
            });
            this.stock = String(this.totalVarStock);
        },
        addVariation() {
            this.variations.push({ name: '', price: '', stock: '', sku: '', image: null });
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
