<x-admin-layout :title="$banner->exists ? 'Edit banner' : 'New home banner'" active="banners">
@php
    $f = fn ($k, $d = null) => old($k, $banner->{$k} ?? $d);
    $focusPos = \App\Models\HomeBanner::FOCUS;
@endphp

<a href="{{ route('admin.banners.index') }}" class="mb-4 inline-block text-sm font-semibold" style="color:#fa4e1c;">← Home banners</a>

<div class="grid gap-6 xl:grid-cols-[1fr_440px]">
<div class="card p-6">
    @if ($errors->any())
        <div class="mb-4 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">
            @foreach ($errors->all() as $e)<p>{{ $e }}</p>@endforeach
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" id="bn-form"
          action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" class="space-y-4">
        @csrf
        @if ($banner->exists) @method('PUT') @endif

        <div>
            <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Photo {{ $banner->exists ? '(leave empty to keep the current one)' : '' }}</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="input text-sm" id="bn-image" @if (! $banner->exists) required @endif>
            <p class="mt-1 text-xs" style="color:#6b90aa;">A wide photo looks best (about 1600 × 640 pixels). JPG, PNG or WebP, up to 5 MB. The left side is darkened for the text.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Small label (optional)</label>
                <input type="text" name="eyebrow" maxlength="40" value="{{ $f('eyebrow') }}" class="input text-sm" placeholder="e.g. 11.11 Sale" data-bn="eyebrow">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Keep in view</label>
                <select name="focus" class="input text-sm" data-bn="focus">
                    @foreach (['top' => 'Top of the photo', 'center' => 'Middle of the photo', 'bottom' => 'Bottom of the photo'] as $k => $l)
                        <option value="{{ $k }}" @selected($f('focus', 'center') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_200px]">
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Headline</label>
                <input type="text" name="title" maxlength="80" required value="{{ $f('title') }}" class="input text-sm" placeholder="e.g. Big savings this" data-bn="title">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Underlined words (optional)</label>
                <input type="text" name="highlight" maxlength="40" value="{{ $f('highlight') }}" class="input text-sm" placeholder="e.g. 11.11" data-bn="highlight">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Line under the headline (optional)</label>
            <input type="text" name="subtitle" maxlength="160" value="{{ $f('subtitle') }}" class="input text-sm" placeholder="e.g. Up to 50% off on gadgets, home and fashion." data-bn="subtitle">
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Button text</label>
                <input type="text" name="button_label" maxlength="30" value="{{ $f('button_label', 'Shop now') }}" class="input text-sm" data-bn="button_label">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Button opens</label>
                <select name="category_slug" class="input text-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $slug => $name)
                        <option value="{{ $slug }}" @selected($f('category_slug') === $slug)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Starts (optional)</label>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', \App\Support\VoucherRules::forInput($banner->starts_at)) }}" class="input text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Ends (optional)</label>
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', \App\Support\VoucherRules::forInput($banner->ends_at)) }}" class="input text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#6b90aa;">Order (lower shows first)</label>
                <input type="number" name="sort_order" min="0" max="999" value="{{ $f('sort_order', 0) }}" class="input text-sm">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm" style="color:#002b4d;">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true)) class="h-4 w-4 accent-[#fa4e1c]">
            On (shows on the home page during its dates)
        </label>

        <div class="flex gap-3 pt-2">
            <button class="btn-gold px-6 py-2.5 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">{{ $banner->exists ? 'Save changes' : 'Add banner' }}</button>
            <a href="{{ route('admin.banners.index') }}" class="btn-outline px-6 py-2.5 text-sm">Cancel</a>
        </div>
    </form>
</div>

{{-- Live preview, as the home page shows it --}}
<div>
    <p class="mb-2 text-xs font-bold uppercase tracking-widest" style="color:#6b90aa;">Preview</p>
    <div class="relative h-60 overflow-hidden rounded-xl" style="background:#1E1B17;">
        <img id="bn-prev-img" src="{{ $banner->exists ? $banner->imageUrl() : '' }}" alt="" class="absolute inset-0 h-full w-full object-cover {{ $banner->exists ? '' : 'hidden' }}"
             style="object-position:{{ $focusPos[$banner->focus ?? 'center'] ?? 'center' }};">
        <div class="absolute inset-0" style="background:linear-gradient(90deg,rgba(24,16,10,.7) 0%,rgba(24,16,10,.35) 42%,rgba(24,16,10,0) 72%);"></div>
        <div class="absolute bottom-8 left-6 max-w-[17rem]">
            <p class="text-xs font-semibold uppercase tracking-wider" style="color:#ffcbb8;" data-prev="eyebrow">{{ $f('eyebrow') }}</p>
            <p class="mt-1 font-display text-2xl font-extrabold leading-tight" style="color:#fff;">
                <span data-prev="title">{{ $f('title', 'Your headline') }}</span>
                <span data-prev="highlight" style="border-bottom:3px solid #fa4e1c;">{{ $f('highlight') }}</span>
            </p>
            <p class="mt-2 text-sm" style="color:rgba(255,255,255,.88);" data-prev="subtitle">{{ $f('subtitle') }}</p>
            <span class="mt-3 inline-block rounded-md px-4 py-2 text-sm font-semibold text-white" style="background:#fa4e1c;" data-prev="button_label">{{ $f('button_label', 'Shop now') }}</span>
        </div>
    </div>
    <p class="mt-2 text-xs" style="color:#6b90aa;">The app shows the same slide, with the text at the bottom.</p>
</div>
</div>

<script>
(function () {
    var pos = @json($focusPos);
    document.querySelectorAll('[data-bn]').forEach(function (el) {
        el.addEventListener('input', update);
        el.addEventListener('change', update);
    });
    function update(e) {
        var key = e.target.dataset.bn;
        if (key === 'focus') { document.getElementById('bn-prev-img').style.objectPosition = pos[e.target.value] || 'center'; return; }
        var out = document.querySelector('[data-prev="' + key + '"]');
        if (out) out.textContent = e.target.value || (key === 'title' ? 'Your headline' : (key === 'button_label' ? 'Shop now' : ''));
    }
    document.getElementById('bn-image').addEventListener('change', function () {
        var f = this.files && this.files[0], img = document.getElementById('bn-prev-img');
        if (!f) return;
        img.src = URL.createObjectURL(f);
        img.classList.remove('hidden');
    });
})();
</script>

</x-admin-layout>
