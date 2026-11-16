{{--
    Every photo and the video of a product, for an admin checking a report (any status: draft, rejected,
    archived too). A small button opens them in a dialog. Needs $product with images loaded.
--}}
@php
    $photos = collect([$product->image])
        ->merge($product->relationLoaded('images') ? $product->images->sortBy('sort_order')->pluck('path') : collect())
        ->filter()->unique()->values();
    $mediaId = 'product-media-' . $product->id;
@endphp
<button type="button" onclick="document.getElementById('{{ $mediaId }}').showModal()" data-product-media="{{ $product->id }}"
        class="mt-1 inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-semibold transition hover:bg-stone-50"
        style="border-color:#E7E5E4;color:#57534E;" title="See every photo and the video">
    📷 {{ $photos->count() }}@if ($product->video_path) <span style="color:#fa4e1c;">· ▶ Video</span>@endif
</button>

<dialog id="{{ $mediaId }}" onclose="this.querySelectorAll('video').forEach(function (v) { v.pause(); })"
        style="width:min(920px,94vw);max-height:90vh;border:none;border-radius:16px;padding:0;box-shadow:0 30px 80px -20px rgba(0,0,0,.45);">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;border-bottom:1px solid #F0E4DC;position:sticky;top:0;background:#fff;">
        <div style="min-width:0;">
            <p style="font-weight:800;color:#002b4d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $product->title }}</p>
            <p style="font-size:12px;color:#6b90aa;">{{ $product->product_code ?: 'No code' }} · {{ $photos->count() }} {{ \Illuminate\Support\Str::plural('photo', $photos->count()) }}{{ $product->video_path ? ' · 1 video' : ' · no video' }}</p>
        </div>
        <form method="dialog"><button style="font-size:22px;line-height:1;color:#57534E;padding:4px 8px;" aria-label="Close">&times;</button></form>
    </div>
    <div style="padding:16px 18px;overflow:auto;max-height:calc(90vh - 70px);">
        @if ($product->video_path)
            <p style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#8a7f78;margin-bottom:6px;">Video</p>
            <video src="{{ asset('storage/' . $product->video_path) }}" controls playsinline preload="none"
                   style="width:100%;max-height:420px;border-radius:12px;background:#000;margin-bottom:16px;" data-admin-video></video>
        @endif
        <p style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#8a7f78;margin-bottom:6px;">Photos (tap to open full size)</p>
        @if ($photos->isEmpty())
            <p style="font-size:13px;color:#6b90aa;">No photos.</p>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;">
                @foreach ($photos as $path)
                    <a href="{{ asset('storage/' . $path) }}" target="_blank" rel="noopener" style="display:block;border:1px solid #ECE7E3;border-radius:10px;overflow:hidden;background:#FAFAF9;" data-admin-photo>
                        <img src="{{ asset('storage/' . $path) }}" alt="" loading="lazy" style="width:100%;aspect-ratio:1;object-fit:contain;">
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</dialog>
