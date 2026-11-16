<x-seller-layout :title="isset($book) ? 'Edit: '.$book->title : 'Add Product'" active="products">
@php
    $isEdit       = isset($book);
    $selectedCat  = old('category_id', $book->category_id ?? ($businessCategoryId ?? ''));
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
    $discountValue  = old('discount_percent', ($book->discount_percent ?? 0) > 0 ? rtrim(rtrim(number_format($book->discount_percent, 2, '.', ''), '0'), '.') : '');
    $maxPhotos    = \App\Models\Product::MAX_IMAGES;
    $existingImgs = $isEdit ? $book->images : collect();
@endphp
<style>
    [x-cloak] { display: none !important; }
    .pf-section { background: #fff; border: 1px solid #ECE7E3; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 2px rgba(34, 34, 34, .04); }
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
    .photo-add:hover { border-color: #fa4e1c; color: #fa4e1c; background: #FFF7F4; }
    .photo-tile:first-child { border-color: #fa4e1c; box-shadow: 0 0 0 1px #fa4e1c; }
    @media (hover: none) { .photo-tile .remove-btn { opacity: 1; } }
    .pf-count { padding: .1rem .6rem; border-radius: 9999px; background: #F5F2EF; font-size: .75rem; font-weight: 600; color: #57534E; }

    .video-pick { display: flex; align-items: center; gap: .875rem; padding: .875rem 1rem; border: 1.5px dashed #D6D3D1; border-radius: .75rem; cursor: pointer; transition: border-color .15s, background .15s; }
    .video-pick:hover, #video:focus-visible + .video-pick { border-color: #fa4e1c; background: #FFF7F4; }
    .video-pick-icon { display: flex; flex: none; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: .625rem; background: #FFF1EC; color: #fa4e1c; }

    .pf-invalid { border-color: #DC2626 !important; box-shadow: 0 0 0 3px rgba(220, 38, 38, .12) !important; }
    .pf-field-error { margin-top: .3rem; font-size: .75rem; font-weight: 500; color: #B91C1C; }
    .pf-alert { display: flex; gap: .875rem; margin-bottom: 1.25rem; padding: 1rem 1.25rem; border: 1px solid #FECACA; border-radius: 1rem; background: #FEF2F2; }
    .pf-alert-icon { display: flex; flex: none; height: 2.25rem; width: 2.25rem; align-items: center; justify-content: center; border-radius: 9999px; background: #FEE2E2; color: #B91C1C; }
    .pf-alert-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .25rem .75rem; padding: .3rem 0; font-size: .875rem; color: #991B1B; }
    .pf-alert-go { flex: none; font-size: .8125rem; font-weight: 600; color: #B91C1C; text-decoration: underline; text-underline-offset: 2px; }
    .pf-alert-go:hover { color: #7F1D1D; }

    .pf-check { display: flex; width: 100%; align-items: center; gap: .5rem; padding: .3rem .25rem; border-radius: .375rem; font-size: .8125rem; text-align: left; color: #44403C; }
    .pf-check:hover { background: #FAFAF9; }
    .pf-check.done { color: #8a7f78; }
    .pf-check-mark { display: flex; flex: none; height: 1.125rem; width: 1.125rem; align-items: center; justify-content: center; border: 1.5px solid #D6D3D1; border-radius: 9999px; }
    .pf-check.done .pf-check-mark { border-color: #16A34A; background: #16A34A; color: #fff; }
    .pf-meter { height: .375rem; overflow: hidden; border-radius: 9999px; background: #F5F2EF; }
    .pf-meter span { display: block; height: 100%; border-radius: 9999px; background: #16A34A; transition: width .2s; }

    .file-input { width: 100%; font-size: .8125rem; color: #57534E; }
    .file-input::file-selector-button { margin-right: .75rem; border: 1px solid #E7E5E4; border-radius: .375rem; background: #fff; padding: .4rem .8rem; font-size: .8125rem; font-weight: 500; color: #44403C; cursor: pointer; }
    .file-input::file-selector-button:hover { background: #FAFAF9; }

    .btn-solid { display: block; width: 100%; border-radius: .375rem; padding: .6rem 1rem; font-size: .875rem; font-weight: 600; color: #fff; background: #fa4e1c; transition: opacity .15s; }
    .btn-solid:hover { opacity: .9; }
    .btn-solid:focus-visible, .btn-line:focus-visible { outline: 2px solid #fa4e1c; outline-offset: 2px; }
    .btn-solid:disabled, .btn-line:disabled { cursor: wait; }
    .btn-line { display: block; width: 100%; border: 1px solid #E7E5E4; border-radius: .375rem; padding: .55rem 1rem; font-size: .875rem; font-weight: 500; color: #44403C; background: #fff; transition: background .15s; }
    .btn-line:hover { background: #FAFAF9; }
</style>

<div x-data="productForm()" x-init="init()" class="mx-auto max-w-6xl" @main-photo.window="previewImage = $event.detail">

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <a href="{{ route('seller.books.index', session('seller_inventory_query', [])) }}" class="text-sm hover:underline" style="color:#8a7f78;">&larr; Inventory</a>
            <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222;">{{ $isEdit ? 'Edit product' : 'Add a product' }}</h1>
            @if ($isEdit && $book->product_code)
                <p class="mt-0.5 text-sm" style="color:#8a7f78;">Product ID {{ $book->product_code }}</p>
            @endif
        </div>
    </div>

    <div x-ref="errors" x-show="formErrors.length > 0" x-cloak role="alert" class="pf-alert">
        <span class="pf-alert-icon" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5m0 3.5h.01M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold" style="color:#7F1D1D;" x-text="formErrors.length === 1 ? '1 thing to fix before saving' : formErrors.length + ' things to fix before saving'"></p>
            <p class="mb-1 text-xs" style="color:#991B1B;">Everything you entered is still here.</p>
            <template x-for="e in formErrors" :key="e.message">
                <div class="pf-alert-row">
                    <span x-text="e.message"></span>
                    <button type="button" class="pf-alert-go" x-show="fieldFor(e.key)" @click="goTo(e.key)">Go to field</button>
                </div>
            </template>
        </div>
    </div>

    <form action="{{ $isEdit ? route('seller.books.update', $book->id) : route('seller.books.store') }}"
          method="POST" enctype="multipart/form-data" id="product-form" @submit.prevent="save($event.target)" @input="clearError($event.target)" @change="clearError($event.target)">
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
                        <span class="pf-count"><span id="photo-count">{{ $existingImgs->count() }}</span> of {{ $maxPhotos }}</span>
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
                        <p class="pf-label">Video <span class="opt">— optional</span></p>
                        <input type="file" id="video" name="video" accept="video/mp4,video/quicktime,video/webm" class="sr-only" data-video-input>
                        <label for="video" class="video-pick" id="video-pick">
                            <span class="video-pick-icon" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.55-2.28A1 1 0 0 1 21 8.62v6.76a1 1 0 0 1-1.45.9L15 14M5 18h8a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2Z"/></svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold" style="color:#222;" data-video-title>{{ $isEdit && $book->video_path ? 'Replace video' : 'Add a video' }}</span>
                                <span class="block text-xs" style="color:#8a7f78;" data-video-sub>MP4, MOV or WebM, up to {{ \App\Http\Controllers\Seller\BookController::videoLimitMb() }} MB</span>
                            </span>
                        </label>
                        @error('video')<p class="pf-error" style="color:#B91C1C;font-size:.8rem;margin-top:.25rem;">{{ $message }}</p>@enderror
                        <p class="pf-note" data-video-status style="display:none;"></p>
                        <button type="button" data-video-clear class="mt-2 text-xs font-semibold hover:underline" style="display:none;color:#B91C1C;">Remove this video</button>
                        {{-- Preview: the saved video, or the one just chosen (replaces it on Save). --}}
                        <video data-video-preview controls playsinline preload="metadata" class="mt-2 w-full max-w-sm rounded-lg border bg-black"
                               style="border-color:#E7E5E4;max-height:240px;{{ $isEdit && $book->video_path ? '' : 'display:none;' }}"
                               @if ($isEdit && $book->video_path) src="{{ asset('storage/' . $book->video_path) }}" @endif></video>
                        @if ($isEdit && $book->video_path)
                            <label class="mt-2 flex items-center gap-2 text-sm" style="color:#57534E;" data-video-remove>
                                <input type="checkbox" name="remove_video" value="1" class="h-4 w-4"> Remove this video
                            </label>
                        @endif
                        <script>
                        (function () {
                            // Check the size before uploading (the server's limit) and preview the chosen video.
                            var limitMb = {{ \App\Http\Controllers\Seller\BookController::videoLimitMb() }};
                            var input = document.querySelector('[data-video-input]'), preview = document.querySelector('[data-video-preview]'),
                                status = document.querySelector('[data-video-status]'), saved = preview.getAttribute('src'),
                                title = document.querySelector('[data-video-title]'), sub = document.querySelector('[data-video-sub]'),
                                clear = document.querySelector('[data-video-clear]'), emptyTitle = title.textContent, emptySub = sub.textContent;
                            function reset() {
                                title.textContent = emptyTitle; sub.textContent = emptySub; clear.style.display = 'none';
                                if (saved) { preview.src = saved; } else { preview.removeAttribute('src'); preview.style.display = 'none'; }
                            }
                            clear.addEventListener('click', function () { input.value = ''; status.style.display = 'none'; reset(); });
                            input.addEventListener('change', function () {
                                var f = input.files && input.files[0];
                                status.style.display = 'none';
                                if (!f) { reset(); return; }
                                if (f.size > limitMb * 1024 * 1024) {
                                    input.value = '';
                                    status.textContent = 'This video is ' + (f.size / 1048576).toFixed(1) + ' MB. The limit is ' + limitMb + ' MB: trim or compress it first.';
                                    status.style.color = '#B91C1C'; status.style.display = '';
                                    reset();
                                    return;
                                }
                                preview.src = URL.createObjectURL(f); preview.style.display = '';
                                title.textContent = f.name;
                                sub.textContent = (f.size / 1048576).toFixed(1) + ' MB. Uploads when you save. Click to replace.';
                                clear.style.display = '';
                            });
                        })();
                        </script>
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
                            @php
                                $mine   = collect($myCategoryIds)->map(fn ($id) => $categories->firstWhere('id', $id))->filter();
                                $others = $categories->reject(fn ($c) => in_array($c->id, $myCategoryIds, true));
                            @endphp
                            <select id="category_id" name="category_id" class="input" x-model="categoryId" @change="onCategoryChange()">
                                <option value="">Select category</option>
                                @if ($mine->isNotEmpty())
                                    <optgroup label="Your store categories">
                                        @foreach ($mine as $cat)
                                            <option value="{{ $cat->id }}" data-name="{{ $cat->name }}" {{ (string) $selectedCat === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                <optgroup label="{{ $mine->isNotEmpty() ? 'Other ALVY categories (adds it to your store)' : 'ALVY categories (adds it to your store)' }}">
                                    @foreach ($others as $cat)
                                        <option value="{{ $cat->id }}" data-name="{{ $cat->name }}" {{ (string) $selectedCat === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            <p class="pf-note">The product shows under this category on your store page. <a href="{{ route('seller.store-categories.index') }}" class="font-semibold" style="color:#fa4e1c;">Your categories →</a></p>
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
                            <label class="pf-label" for="stock">Stock</label>
                            <input type="number" id="stock" min="0" max="99999" step="1" name="stock" x-model="stock" class="input" placeholder="0" :disabled="variations.length > 0" x-effect="if (variations.length > 0) stock = String(totalVarStock)">
                            <p class="pf-note" x-show="variations.length > 0">Total of variations: <span x-text="totalVarStock"></span>. Set each variation's stock under Variations.</p>
                        </div>
                        <div>
                            <label class="pf-label" for="sku">SKU <span class="opt">— optional</span></label>
                            <input type="text" id="sku" name="sku" value="{{ old('sku', $book->sku ?? '') }}" class="input" placeholder="Your own code">
                        </div>
                    </div>
                    <div class="mt-5 rounded-lg border p-4" style="border-color:#ECE7E3;">
                        <span class="pf-label">Sale <span class="opt">— optional</span></span>
                        <input type="hidden" name="sale_type" :value="saleType">
                        <div class="mb-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Sale">
                            @foreach (['none' => 'No sale', 'price' => 'Sale price (₱)', 'percent' => 'Discount %'] as $value => $label)
                                <button type="button" role="radio" :aria-checked="saleType === '{{ $value }}'" @click="saleType = '{{ $value }}'"
                                        class="rounded-full border px-3.5 py-1.5 text-sm font-semibold"
                                        :style="saleType === '{{ $value }}' ? 'border-color:#fa4e1c;background:#FFF1EB;color:#C2410C;' : 'border-color:#E7E5E4;color:#57534E;'">{{ $label }}</button>
                            @endforeach
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3" x-show="saleType !== 'none'" x-cloak>
                            <div x-show="saleType === 'price'">
                                <label class="pf-label" for="sale_price">Sale price (₱)</label>
                                <input type="number" id="sale_price" step="0.01" min="1" name="sale_price" x-model="salePrice" :disabled="saleType !== 'price'" class="input" placeholder="Lower than the price">
                                <p class="pf-note" x-show="saleError" style="color:#B91C1C;">Must be lower than the price.</p>
                            </div>
                            <div x-show="saleType === 'percent'">
                                <label class="pf-label" for="discount_percent">Discount %</label>
                                <input type="number" id="discount_percent" step="1" min="1" max="99" name="discount_percent" value="{{ $discountValue }}" :disabled="saleType !== 'percent'" class="input" placeholder="1 – 99">
                                <p class="pf-note">Applies to every variant's price.</p>
                            </div>
                            <div>
                                <label class="pf-label" for="sale_starts_at">Sale starts <span class="opt">— blank = now</span></label>
                                <input type="datetime-local" id="sale_starts_at" name="sale_starts_at" :disabled="saleType === 'none'" value="{{ old('sale_starts_at', \App\Support\VoucherRules::forInput($book->sale_starts_at ?? null)) }}" class="input">
                            </div>
                            <div>
                                <label class="pf-label" for="sale_ends_at">Sale ends <span class="opt">— optional</span></label>
                                <input type="datetime-local" id="sale_ends_at" name="sale_ends_at" :disabled="saleType === 'none'" value="{{ old('sale_ends_at', \App\Support\VoucherRules::forInput($book->sale_ends_at ?? null)) }}" class="input">
                                <p class="pf-note">Buyers see a countdown; the sale stops at this time.</p>
                            </div>
                        </div>
                        @if ($isEdit && in_array($book->saleState(), ['Upcoming', 'Ongoing'], true))
                            <p class="pf-note"><span class="font-semibold">Sale: {{ $book->saleState() }}.</span>
                                <button type="submit" form="end-sale-form" class="font-semibold underline" style="color:#B91C1C;">End sale now</button></p>
                        @endif
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="pf-label" for="low_stock_threshold">Low-stock alert at <span class="opt">— optional</span></label>
                            <input type="number" id="low_stock_threshold" min="0" max="99999" step="1" name="low_stock_threshold" value="{{ old('low_stock_threshold', $book->low_stock_threshold ?? '') }}" class="input" placeholder="{{ config('marketplace.low_stock_threshold', 5) }} (default)">
                            <p class="pf-note">We notify you when stock drops to this or fewer.</p>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
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
                            <p class="pf-hint mt-1">The choices a buyer picks for this product: Color and Size, or Material, Style, Volume, any name you like.
                                Up to {{ \App\Models\ProductVariation::MAX_TYPES }} types with up to {{ \App\Models\ProductVariation::MAX_OPTIONS }} options each.
                                Every combination gets its own price, stock, SKU and photo. Skip this for a single product.</p>
                        </div>
                        <button type="button" @click="addType()" :disabled="types.length >= {{ \App\Models\ProductVariation::MAX_TYPES }}" class="rounded-md border px-3 py-1.5 text-sm font-medium transition hover:bg-stone-50 disabled:opacity-50" style="border-color:#E7E5E4;color:#44403C;">Add variation type</button>
                    </div>
                    <p class="pf-note" x-show="types.length >= {{ \App\Models\ProductVariation::MAX_TYPES }}" style="color:#B45309;">The maximum is {{ \App\Models\ProductVariation::MAX_TYPES }} variation types.</p>

                    <template x-for="(t, ti) in types" :key="ti">
                        <div class="mt-3 rounded-md border p-3" style="border-color:#ECE7E3;">
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="text" :name="`variation_types[${ti}][name]`" x-model="t.name" maxlength="30" class="input max-w-[12rem]" placeholder="Type, e.g. Color" aria-label="Variation type" list="variation-type-names">
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

                <datalist id="variation-type-names">
                    <option value="Color"></option><option value="Size"></option><option value="Material"></option>
                    <option value="Style"></option><option value="Design"></option><option value="Volume"></option>
                    <option value="Capacity"></option><option value="Length"></option><option value="Flavor"></option><option value="Pattern"></option>
                </datalist>

                {{-- ════════ SPECIFICATIONS ════════ --}}
                <section class="pf-section" x-show="specFields.length > 0" x-cloak>
                    <h2>Specifications</h2>
                    <p class="pf-hint mt-1 mb-4">Fixed details buyers look for in this category. Choices the buyer picks, like color or size, go in <strong>Variations</strong> above.</p>
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
                            <input type="number" id="weight_kg" step="0.01" min="0.01" name="weight_kg" x-model="weight" class="input" placeholder="0.00">
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
                            <button type="submit" @click="action='publish'" :disabled="saving" :style="saving ? 'opacity:.6' : ''" class="btn-solid">
                                <span x-text="saving && action === 'publish' ? 'Saving…' : @js($isEdit ? 'Save changes' : 'Publish product')">{{ $isEdit ? 'Save changes' : 'Publish product' }}</span>
                            </button>
                        @else
                            <button type="button" disabled class="btn-solid" style="opacity:.5;cursor:not-allowed;" aria-describedby="verify-note">Publish product</button>
                            <p id="verify-note" class="pf-note" style="color:#B45309;">Your store must be verified before you can publish. Save as a draft for now.</p>
                        @endif
                        <button type="submit" @click="action='draft'" :disabled="saving" :style="saving ? 'opacity:.6' : ''" class="btn-line"><span x-text="saving && action === 'draft' ? 'Saving…' : 'Save as draft'">Save as draft</span></button>
                        <button type="button" @click="openPreview()" class="w-full py-1.5 text-sm font-medium hover:underline" style="color:#57534E;">Preview</button>
                    </div>

                    <div class="mt-5 border-t pt-4" style="border-color:#F2EEEA;">
                        <div class="mb-2 flex items-baseline justify-between gap-2">
                            <h3 class="text-sm font-semibold" style="color:#222;">Ready to publish</h3>
                            <span class="pf-hint" x-text="checklistDone + ' of ' + checklist.length"></span>
                        </div>
                        <div class="pf-meter mb-2" aria-hidden="true"><span :style="'width:' + (checklistDone / checklist.length * 100) + '%'"></span></div>
                        <template x-for="item in checklist" :key="item.label">
                            <button type="button" class="pf-check" :class="item.done && 'done'" @click="goTo(item.key)">
                                <span class="pf-check-mark" aria-hidden="true">
                                    <svg x-show="item.done" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                </span>
                                <span x-text="item.label"></span>
                                <span class="sr-only" x-text="item.done ? '(done)' : '(missing)'"></span>
                            </button>
                        </template>
                        <p class="pf-note">Drafts can be saved at any time.</p>
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
        saving: false,
        formErrors: [],
        weight: @json((string) old('weight_kg', $book->weight_kg ?? '')),
        categoryId: @json((string) $selectedCat),
        categoryName: @json($selectedName ?? ''),
        subcategory: @json($currentSub),
        subs: [],
        noBrand: @json(old('brand', $book->brand ?? null) === 'No Brand'),
        price: @json((string) old('price', $book->price ?? '')),
        salePrice: @json((string) old('sale_price', $book->sale_price ?? '')),
        saleType: @json(old('sale_type', ($book->sale_price ?? null) ? 'price' : ((($book->discount_percent ?? 0) > 0) ? 'percent' : 'none'))),
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
            this.showErrors(@json($errors->getMessages()));
            const form = document.getElementById('product-form');
            form.querySelector('[name=title]').addEventListener('input', e => this.title = e.target.value);
            form.querySelector('[name=description]').addEventListener('input', e => this.description = e.target.value);
            form.querySelector('[name=brand]').addEventListener('input', e => this.brand = e.target.value);
        },
        /** Sends the form without leaving the page, so a form that needs fixing keeps everything entered, photos included. */
        async save(form) {
            if (this.saving) return;
            this.saving = true;
            this.formErrors = [];
            window.UploadOverlay?.show(form);
            try {
                // getAttribute: the "action" field hides form.action.
                const res = await fetch(form.getAttribute('action'), {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const body = await res.json().catch(() => null);
                if (res.ok && body?.redirect) {
                    window.location.href = body.redirect;
                    return;
                }
                this.showErrors(body?.errors || { _: ['The product could not be saved (error ' + res.status + '). Please try again.'] });
            } catch (e) {
                this.showErrors({ _: ['Could not reach the server. Please check your connection and try again.'] });
            }
            window.UploadOverlay?.hide();
            this.saving = false;
            this.$nextTick(() => this.$refs.errors.scrollIntoView({ behavior: 'smooth', block: 'center' }));
        },
        /** The element a server error key points at ("variations.0.stock" is the input variations[0][stock]). */
        fieldFor(key) {
            const base = String(key).split('.')[0];
            if (base === 'images') return document.getElementById('photo-add');
            if (base === 'video') return document.getElementById('video-pick');
            const name = String(key).replace(/\.(\w+)/g, '[$1]');
            return document.querySelector('#product-form [name="' + CSS.escape(name) + '"]');
        },
        /** Shows the panel at the top and marks each field. The same message on several fields is listed once. */
        showErrors(errors) {
            document.querySelectorAll('.pf-field-error').forEach(p => p.remove());
            document.querySelectorAll('.pf-invalid').forEach(el => el.classList.remove('pf-invalid'));
            const seen = new Set();
            const list = Object.entries(errors).map(([key, messages]) => ({ key, message: [].concat(messages)[0] }));
            this.formErrors = list.filter(e => !seen.has(e.message) && seen.add(e.message));
            this.$nextTick(() => list.forEach(e => {
                const el = this.fieldFor(e.key);
                if (!el) return;
                el.classList.add('pf-invalid');
                // Variant rows are a tight grid and the photo tile sits in one: the red outline says enough there.
                if (el.closest('[data-variant-row]') || el.id === 'photo-add') return;
                const p = document.createElement('p');
                p.className = 'pf-field-error';
                p.dataset.for = e.key;
                p.textContent = e.message;
                el.after(p);
            }));
        },
        /** Fixing a field takes its error away, here and in the panel. */
        clearError(target) {
            const key = target.id === 'photo-picker' ? 'images' : (target.name || '').replace(/\[(\w+)\]/g, '.$1');
            if (!key) return;
            const hit = e => e === key || e.startsWith(key + '.');
            if (!this.formErrors.some(e => hit(e.key))) return;
            this.formErrors = this.formErrors.filter(e => !hit(e.key));
            this.fieldFor(key)?.classList.remove('pf-invalid');
            document.querySelectorAll('.pf-field-error').forEach(p => { if (hit(p.dataset.for)) p.remove(); });
        },
        goTo(key) {
            const el = this.fieldFor(key);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (el.focus && !el.disabled) el.focus({ preventScroll: true });
        },
        /** A guide to what Publish asks for. The server still decides. */
        get checklist() {
            const unfinished = this.variations.findIndex(v => v.stock === '' || v.stock === null);
            const noPrice = this.variations.findIndex(v => v.price === '' || v.price === null);
            const hasRows = this.variations.length > 0;
            return [
                { key: 'images', label: 'At least 1 photo', done: !!this.previewImage },
                { key: 'title', label: 'Product name', done: this.title.trim().length >= 5 },
                { key: 'category_id', label: 'Category', done: !!this.categoryName },
                { key: 'description', label: 'Description (20+ characters)', done: this.description.trim().length >= 20 },
                { key: noPrice >= 0 ? 'variations.' + noPrice + '.price' : 'price', label: 'Price', done: Number(this.price) >= 1 && noPrice < 0 },
                { key: unfinished >= 0 ? 'variations.' + unfinished + '.stock' : 'stock', label: 'Stock', done: hasRows ? unfinished < 0 : this.stock !== '' && this.stock !== null },
                { key: 'weight_kg', label: 'Weight', done: Number(this.weight) >= 0.01 },
            ];
        },
        get checklistDone() {
            return this.checklist.filter(i => i.done).length;
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
