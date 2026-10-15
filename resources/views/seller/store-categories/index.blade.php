<x-seller-layout title="Store Categories" active="store-categories">
<style>
    .sc-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; }
    .sc-row { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; padding:.7rem 1rem; border-top:1px solid #F2EEEA; }
    .sc-row.is-sub { padding-left:2.5rem; background:#FCFBFA; }
    .sc-name { min-width:0; flex:1 1 12rem; overflow-wrap:anywhere; font-weight:600; font-size:.875rem; }
    .sc-btn { border:1px solid #E7E5E4; border-radius:.375rem; padding:.3rem .6rem; font-size:.75rem; font-weight:600; color:#44403C; background:#fff; }
    .sc-btn:hover { background:#FAFAF9; }
    .sc-handle { cursor:grab; color:#a39890; user-select:none; }
    .sc-row.dragging { opacity:.4; }
</style>

<div class="mx-auto max-w-3xl">
    <div class="mb-5">
        <span class="section-eyebrow">Store</span>
        <h1 class="text-2xl">Store Categories</h1>
        <p class="text-sm" style="color:#8a7f78;">Group products on your store page. These are separate from the ALVY platform categories, and a product can be in several. Two levels at most.</p>
    </div>

    @if (session('warning'))<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FFFBEB;color:#B45309;" role="alert">{{ session('warning') }}</div>@endif
    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ route('seller.store-categories.store') }}" class="sc-card mb-5 grid gap-3 p-4 sm:grid-cols-[1fr_14rem_auto]">
        @csrf
        <div>
            <label class="mb-1 block text-xs font-semibold" for="new-name">Category name</label>
            <input id="new-name" name="name" maxlength="{{ \App\Models\StoreCategory::MAX_NAME }}" value="{{ old('name') }}" class="input" placeholder="e.g. Pasalubong Picks">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold" for="new-parent">Under</label>
            <select id="new-parent" name="parent_id" class="input">
                <option value="">— Top level —</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected((string) old('parent_id') === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end"><button class="btn-primary w-full" style="padding:.62rem 1.2rem;">Add Category</button></div>
    </form>

    <div class="sc-card overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3">
            <p class="text-sm font-bold">{{ $categories->count() }} {{ \Illuminate\Support\Str::plural('category', $categories->count()) }}</p>
            <form method="POST" action="{{ route('seller.store-categories.reorder') }}" id="order-form" class="hidden">
                @csrf
                <div id="order-ids"></div>
                <button class="btn-primary" style="padding:.4rem 1rem;">Save order</button>
            </form>
        </div>

        @forelse ($categories as $cat)
            <div data-cat-block="{{ $cat->id }}">
                @foreach (collect([$cat])->merge($cat->children) as $c)
                    @php $sub = (bool) $c->parent_id; @endphp
                    <div class="sc-row {{ $sub ? 'is-sub' : '' }}" @unless($sub) draggable="true" data-top="{{ $c->id }}" @endunless>
                        @unless ($sub)<span class="sc-handle" title="Drag to reorder" aria-hidden="true">⠿</span>@endunless
                        <span class="sc-name">{{ $c->name }}
                            <span class="ml-1 text-xs font-normal" style="color:#a39890;">{{ $c->products_count }} {{ \Illuminate\Support\Str::plural('product', $c->products_count) }}</span>
                            @if ($c->is_hidden)<span class="ml-1 rounded px-1.5 py-0.5 text-[11px] font-semibold" style="background:#F5F5F4;color:#78716C;">Hidden</span>@endif
                        </span>
                        @unless ($sub)
                            <button type="button" class="sc-btn" data-move="up" aria-label="Move {{ $c->name }} up">↑</button>
                            <button type="button" class="sc-btn" data-move="down" aria-label="Move {{ $c->name }} down">↓</button>
                        @endunless
                        <details class="relative">
                            <summary class="sc-btn list-none cursor-pointer">Rename</summary>
                            <form method="POST" action="{{ route('seller.store-categories.update', $c) }}" class="absolute right-0 z-10 mt-1 flex w-72 gap-2 rounded-lg border bg-white p-2 shadow" style="border-color:#E7E5E4;">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $c->name }}" maxlength="{{ \App\Models\StoreCategory::MAX_NAME }}" class="input" style="padding:.4rem .6rem;" aria-label="New name">
                                <button class="btn-primary" style="padding:.4rem .8rem;">Save</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('seller.store-categories.toggle', $c) }}">@csrf @method('PATCH')
                            <button class="sc-btn">{{ $c->is_hidden ? 'Show' : 'Hide' }}</button>
                        </form>
                        <form method="POST" action="{{ route('seller.store-categories.destroy', $c) }}">@csrf @method('DELETE')
                            @if (session('confirm_delete') === $c->id)<input type="hidden" name="confirm" value="1">@endif
                            <button class="sc-btn" style="color:#B91C1C;{{ session('confirm_delete') === $c->id ? 'border-color:#B91C1C;' : '' }}">{{ session('confirm_delete') === $c->id ? 'Confirm delete' : 'Delete' }}</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="border-t px-4 py-10 text-center text-sm" style="border-color:#F2EEEA;color:#8a7f78;">No store categories yet. Products without one show under "All products".</p>
        @endforelse
    </div>
</div>

<script>
(function () {
    const list = document.querySelector('.sc-card.overflow-hidden');
    const form = document.getElementById('order-form');
    const blocks = () => [...list.querySelectorAll('[data-cat-block]')];
    function changed() {
        form.classList.remove('hidden');
        const box = document.getElementById('order-ids');
        box.innerHTML = '';
        blocks().forEach(b => { const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = b.dataset.catBlock; box.appendChild(i); });
    }
    list.addEventListener('click', e => {
        const btn = e.target.closest('[data-move]');
        if (!btn) return;
        const block = btn.closest('[data-cat-block]');
        const sib = btn.dataset.move === 'up' ? block.previousElementSibling : block.nextElementSibling;
        if (!sib || !sib.dataset.catBlock) return;
        btn.dataset.move === 'up' ? list.insertBefore(block, sib) : list.insertBefore(sib, block);
        changed();
    });
    let drag = null;
    list.addEventListener('dragstart', e => { const r = e.target.closest('[data-top]'); if (!r) return; drag = r.closest('[data-cat-block]'); r.classList.add('dragging'); });
    list.addEventListener('dragend', e => { e.target.closest('[data-top]')?.classList.remove('dragging'); if (drag) changed(); drag = null; });
    list.addEventListener('dragover', e => {
        const over = e.target.closest('[data-cat-block]');
        if (!drag || !over || over === drag) return;
        e.preventDefault();
        const r = over.getBoundingClientRect();
        list.insertBefore(drag, e.clientY - r.top < r.height / 2 ? over : over.nextSibling);
    });
})();
</script>
</x-seller-layout>
