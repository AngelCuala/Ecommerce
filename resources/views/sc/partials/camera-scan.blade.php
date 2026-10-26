{{-- Camera scanning for a waybill scan form ($form = the form's id). Typing the number keeps working without it. --}}
<div data-camera-scan data-form="{{ $form }}" style="margin-top:8px;max-width:520px;">
    <button type="button" class="btn btn-ghost" data-camera-open style="gap:6px;">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><line x1="7" y1="12" x2="17" y2="12"/></svg>
        Scan with camera
    </button>
    <div data-camera-panel hidden>
        <video playsinline muted style="display:block;width:100%;max-height:320px;background:#111;border-radius:10px;object-fit:cover;"></video>
        <button type="button" class="btn btn-ghost btn-sm" data-camera-close style="margin-top:6px;">Close camera</button>
    </div>
    <p data-camera-status class="text-muted" style="font-size:12px;margin-top:6px;" aria-live="polite"></p>
</div>
@once
    <script src="{{ asset('js/sc-scanner.js') }}?v={{ filemtime(public_path('js/sc-scanner.js')) }}" data-zxing="{{ asset('js/vendor/zxing.min.js') }}" defer></script>
@endonce
