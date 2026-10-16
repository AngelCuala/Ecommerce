<x-seller-layout title="Bundles & Promos" active="promotions">
@php $type = old('type', 'bundle'); $names = $products->pluck('title', 'id'); @endphp
<div class="mx-auto max-w-4xl">
    <div class="mb-5">
        <span class="section-eyebrow">Marketing</span>
        <h1 class="text-2xl">Bundles &amp; Promos</h1>
        <p class="text-sm" style="color:#8a7f78;">Applied automatically at checkout. You pay for these discounts (they come out of your sale), like store vouchers.</p>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">@foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach</div>@endif

    <form method="POST" action="{{ route('seller.promotions.store') }}" class="card mb-6 space-y-4 p-5" id="promo-form">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold" for="p-name">Name</label>
                <input id="p-name" name="name" value="{{ old('name') }}" maxlength="120" class="input" placeholder="e.g. Tote trio deal">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" for="p-type">Type</label>
                <select id="p-type" name="type" class="input">
                    <option value="bundle" @selected($type === 'bundle')>Bundle deal — buy any N, get % off</option>
                    <option value="bxgy" @selected($type === 'bxgy')>Buy X, get Y free</option>
                </select>
            </div>
        </div>
        <div>
            <span class="mb-1 block text-xs font-semibold">Products to buy</span>
            <div class="grid max-h-48 gap-1 overflow-y-auto rounded-lg border p-2 sm:grid-cols-2" style="border-color:#E7E5E4;">
                @foreach ($products as $p)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, (array) old('product_ids', []))) style="accent-color:#fa4e1c;"> <span class="truncate">{{ $p->title }}</span></label>
                @endforeach
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <label class="mb-1 block text-xs font-semibold" for="p-min">Buy (items)</label>
                <input id="p-min" type="number" name="min_qty" min="1" value="{{ old('min_qty', 3) }}" class="input">
            </div>
            <div data-for="bundle">
                <label class="mb-1 block text-xs font-semibold" for="p-pct">Discount %</label>
                <input id="p-pct" type="number" name="discount_percent" min="1" max="99" step="0.01" value="{{ old('discount_percent', 15) }}" class="input">
            </div>
            <div data-for="bxgy">
                <label class="mb-1 block text-xs font-semibold" for="p-free">Free product</label>
                <select id="p-free" name="free_product_id" class="input">
                    <option value="">—</option>
                    @foreach ($products as $p)<option value="{{ $p->id }}" @selected((string) old('free_product_id') === (string) $p->id)>{{ $p->title }}</option>@endforeach
                </select>
            </div>
            <div data-for="bxgy">
                <label class="mb-1 block text-xs font-semibold" for="p-fq">Free (items)</label>
                <input id="p-fq" type="number" name="free_qty" min="1" value="{{ old('free_qty', 1) }}" class="input">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label class="mb-1 block text-xs font-semibold" for="p-start">Starts <span class="font-normal">(blank = now)</span></label><input id="p-start" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="input"></div>
            <div><label class="mb-1 block text-xs font-semibold" for="p-end">Ends <span class="font-normal">(blank = no end)</span></label><input id="p-end" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="input"></div>
        </div>
        <div class="flex justify-end"><button class="btn-primary">Save promotion</button></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs" style="color:#78716C;background:#FAFAF9;"><th class="px-4 py-2">Promotion</th><th class="px-4 py-2">Products</th><th class="px-4 py-2">Dates</th><th class="px-4 py-2">Status</th><th class="px-4 py-2"></th></tr></thead>
            <tbody>
            @forelse ($promotions as $promo)
                <tr class="border-t" style="border-color:#F2EEEA;">
                    <td class="px-4 py-3"><p class="font-semibold">{{ $promo->name }}</p><p class="text-xs" style="color:#8a7f78;">{{ $promo->label() }}@if ($promo->freeProduct) · free: {{ $promo->freeProduct->title }}@endif</p></td>
                    <td class="px-4 py-3 text-xs">{{ collect($promo->product_ids)->map(fn ($id) => $names[$id] ?? '#' . $id)->join(', ') }}</td>
                    <td class="px-4 py-3 text-xs">{{ $promo->starts_at?->format('M d, g:i A') ?? 'Now' }} – {{ $promo->ends_at?->format('M d, g:i A') ?? 'No end' }}</td>
                    <td class="px-4 py-3 text-xs">{{ ! $promo->is_active ? 'Off' : ($promo->ends_at && $promo->ends_at->isPast() ? 'Ended' : ($promo->starts_at && $promo->starts_at->isFuture() ? 'Upcoming' : 'Ongoing')) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <form method="POST" action="{{ route('seller.promotions.toggle', $promo) }}" class="inline">@csrf @method('PATCH')<button class="text-xs font-semibold" style="color:#57534E;">{{ $promo->is_active ? 'Turn off' : 'Turn on' }}</button></form>
                        <form method="POST" action="{{ route('seller.promotions.destroy', $promo) }}" class="ml-2 inline" onsubmit="return confirm('Delete this promotion?')">@csrf @method('DELETE')<button class="text-xs font-semibold" style="color:#B91C1C;">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center" style="color:#8a7f78;">No promotions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var sel = document.getElementById('p-type');
    function sync() { document.querySelectorAll('[data-for]').forEach(function (el) { el.style.display = el.dataset.for === sel.value ? '' : 'none'; }); }
    sel.addEventListener('change', sync); sync();
})();
</script>
</x-seller-layout>
