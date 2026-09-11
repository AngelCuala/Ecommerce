<x-seller-layout :title="isset($book) ? 'Edit: '.$book->title : 'Add Product'" active="products">
<div>

    <div class="mb-8">
        <span class="section-eyebrow" style="color:#fa4e1c;">Seller Panel</span>
        <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222222;">
            {{ isset($book) ? 'Edit Product' : 'List a New Product' }}
        </h1>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border p-4 text-sm" style="background:rgba(155,58,46,.07);border-color:rgba(155,58,46,.2);color:#d93d0e;">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ isset($book) ? route('seller.books.update', $book->id) : route('seller.books.store') }}"
          method="POST" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @if (isset($book)) @method('PUT') @endif

        {{-- ═══════════════════ BASIC INFO ═══════════════════ --}}
        <div class="card p-6 space-y-5">
            <h2 class="font-display text-lg font-semibold" style="color:#222222;">Basic Information</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Product Name *</label>
                    <input type="text" name="title" value="{{ old('title', $book->title ?? '') }}"
                           class="input mt-1" required>
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Brand / Author</label>
                    <input type="text" name="author" value="{{ old('author', $book->author ?? '') }}"
                           class="input mt-1">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Category *</label>
                    <select name="category_id" class="input mt-1" required>
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
                            <option value="{{ $cat->id }}"
                                {{ old('category_id', $book->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                                {{ $catalogOrder[$cat->name] ?? '' }} {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Description</label>
                    <textarea name="description" rows="4" class="input mt-1" maxlength="3000"
                              placeholder="Describe your product — features, condition, notes…">{{ old('description', $book->description ?? '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- ═══════════════════ PRODUCT DETAILS ═══════════════════ --}}
        <div class="card p-6 space-y-5">
            <h2 class="font-display text-lg font-semibold" style="color:#222222;">Product Details</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Model / ISBN / Reference</label>
                    <input type="text" name="isbn" value="{{ old('isbn', $book->isbn ?? '') }}"
                           class="input mt-1" placeholder="e.g. MODEL-001 or ISBN">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Manufacturer / Publisher</label>
                    <input type="text" name="publisher" value="{{ old('publisher', $book->publisher ?? '') }}"
                           class="input mt-1">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Year / Release Year</label>
                    <input type="number" name="publication_year"
                           value="{{ old('publication_year', $book->publication_year ?? '') }}"
                           class="input mt-1" min="1000" max="{{ date('Y') + 1 }}" placeholder="{{ date('Y') }}">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Variant / Edition</label>
                    <input type="text" name="edition" value="{{ old('edition', $book->edition ?? '') }}"
                           class="input mt-1" placeholder="e.g. Version 2, Color: Red">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Language</label>
                    <input type="text" name="language" value="{{ old('language', $book->language ?? '') }}"
                           class="input mt-1" placeholder="e.g. English">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Units per Pack</label>
                    <input type="number" name="pages" value="{{ old('pages', $book->pages ?? '') }}"
                           class="input mt-1" min="1" placeholder="e.g. 1">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Product Type *</label>
                    <select name="format" class="input mt-1" required>
                        @foreach (['Other' => 'Physical', 'Paperback' => 'Sealed / New', 'Hardcover' => 'Premium', 'eBook' => 'Digital'] as $val => $label)
                            <option value="{{ $val }}"
                                {{ old('format', $book->format ?? 'Other') === $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ═══════════════════ PRICING & INVENTORY ═══════════════════ --}}
        <div class="card p-6 space-y-5">
            <h2 class="font-display text-lg font-semibold" style="color:#222222;">Pricing & Inventory</h2>

            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Price ($) *</label>
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm" style="color:#fa4e1c;">$</span>
                        <input type="number" step="0.01" name="price"
                               value="{{ old('price', $book->price ?? '') }}"
                               class="input pl-7" min="0" required>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Stock Quantity *</label>
                    <input type="number" name="stock" value="{{ old('stock', $book->stock ?? 0) }}"
                           class="input mt-1" min="0" required
                           oninput="document.getElementById('avail-display').textContent=this.value>0?'In Stock':'Out of Stock';document.getElementById('avail-display').style.color=this.value>0?'#059669':'#DC2626'">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Availability</label>
                    <p class="input mt-1 cursor-default" id="avail-display"
                       style="color:{{ (old('stock', $book->stock ?? 0) > 0) ? '#059669' : '#DC2626' }};">
                        {{ (old('stock', $book->stock ?? 0) > 0) ? 'In Stock' : 'Out of Stock' }}
                    </p>
                    <p class="mt-1 text-[11px]" style="color:#6b90aa;">Auto-set based on stock quantity</p>
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">SKU <span style="color:#6b90aa;font-weight:400;">(optional)</span></label>
                    <input type="text" name="sku" value="{{ old('sku', $book->sku ?? '') }}"
                           class="input mt-1" placeholder="e.g. BK-001">
                </div>
            </div>
        </div>

        {{-- ═══════════════════ IMAGES ═══════════════════ --}}
        <div class="card p-6 space-y-5">
            <div>
                <h2 class="font-display text-lg font-semibold" style="color:#222222;">Product Images</h2>
                <p class="mt-1 text-sm" style="color:#6b90aa;">Upload up to 5 images. The main image is the primary display photo.</p>
            </div>

            {{-- Main image --}}
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">
                    Main Image {{ isset($book) ? '(upload to replace)' : '*' }}
                </label>
                @if (isset($book) && $book->image)
                    <div class="mt-2 mb-3 flex items-center gap-3">
                        <img src="{{ asset('storage/'.$book->image) }}"
                             class="h-20 w-20 rounded-lg object-cover border" style="border-color:#EFEFEF;"
                             alt="Current image">
                        <p class="text-xs" style="color:#6b90aa;">Current image — upload a new one to replace</p>
                    </div>
                @endif
                <input type="file" name="cover_image" accept="image/*"
                       class="input mt-1 py-2"
                       {{ ! isset($book) ? 'required' : '' }}>
                <p class="mt-1 text-[11px]" style="color:#6b90aa;">JPG, PNG or WebP · max 3 MB</p>
            </div>

            {{-- Gallery images --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($imageLabels as $key => $label)
                    @php $existing = isset($book) ? $book->images->firstWhere('label', $label) : null; @endphp
                    <div class="rounded-xl border p-4" style="border-color:#EFEFEF;">
                        <label class="text-xs font-semibold block mb-2" style="color:#555555;">{{ $label }}</label>
                        @if ($existing)
                            <img src="{{ $existing->url() }}"
                                 class="mb-2 h-24 w-full rounded-lg object-cover" alt="{{ $label }}">
                            <p class="text-[11px] mb-2" style="color:#6b90aa;">Upload to replace</p>
                        @endif
                        <input type="file" name="images[{{ $key }}]" accept="image/*"
                               class="w-full text-xs" style="color:#555555;">
                        <p class="mt-1 text-[11px]" style="color:#6b90aa;">Optional</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Discount & Voucher --}}
        <div class="card p-6 space-y-5">
            <h2 class="font-display text-lg font-semibold" style="color:#222222;">Discounts & Vouchers</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Discount % <span style="font-weight:400;">(optional)</span></label>
                    <div class="relative mt-1">
                        <input type="number" step="0.01" name="discount_percent" min="0" max="99"
                               value="{{ old('discount_percent', $book->discount_percent ?? '') }}"
                               class="input pr-8" placeholder="e.g. 10">
                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold" style="color:#6b90aa;">%</span>
                    </div>
                    <p class="mt-1 text-[11px]" style="color:#6b90aa;">Buyers see the crossed-out original price</p>
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Voucher Code <span style="font-weight:400;">(optional)</span></label>
                    <input type="text" name="voucher_code" maxlength="50"
                           value="{{ old('voucher_code', $book->voucher_code ?? '') }}"
                           class="input mt-1" placeholder="e.g. SAVE20">
                    <p class="mt-1 text-[11px]" style="color:#6b90aa;">Buyers enter this code at checkout</p>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex gap-3">
            <button type="submit"
                    class="flex-1 rounded-xl py-3.5 text-base font-semibold text-white transition hover:opacity-90"
                    style="background:#002b4d;">
                {{ isset($book) ? 'Save Changes' : 'Publish Product' }}
            </button>
            <a href="{{ route('seller.books.index') }}"
               class="rounded-xl py-3.5 px-8 text-sm font-semibold transition"
               style="border:1.5px solid #E0E0E0;color:#555555;"
               onmouseover="this.style.borderColor='#fa4e1c';this.style.color='#fa4e1c';"
               onmouseout="this.style.borderColor='#E0E0E0';this.style.color='#555555';">Cancel</a>
        </div>
    </form>
</div>
</x-seller-layout>

