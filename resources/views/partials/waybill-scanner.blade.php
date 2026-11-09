{{--
    "Scan QR" button + pop-up QR scanner (sorting center Shipment Board). Scans the QR code on the waybill with
    the camera (the waybill's barcode still reads too, for labels printed before the QR code), or takes a typed
    number; the server finds the parcel and the pop-up opens its page.
    @include('partials.waybill-scanner', ['lookup' => route('sc.parcels.scan')])
    Optional: 'label' (button text), 'style' (button look: 'solid' default, or 'ghost').
--}}
@php
    $id = 'wbs-' . substr(md5($lookup), 0, 8);
    $ghost = ($style ?? 'solid') === 'ghost';
    // Several buttons may open the same pop-up (top bar + page); the pop-up itself is written once per page.
    $renderDialog = ! request()->attributes->get('waybill-scanner.' . $id);
    request()->attributes->set('waybill-scanner.' . $id, true);
@endphp
<button type="button" class="wbs-trigger {{ $ghost ? 'is-ghost' : '' }}" data-waybill-scanner-open="{{ $id }}" aria-haspopup="dialog">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM17 17h4v4h-4"/></svg>
    {{ $label ?? 'Scan QR' }}
</button>

@once
<style>
    .wbs-trigger { display: inline-flex; align-items: center; gap: 7px; border: 0; border-radius: 9px; padding: 8px 14px; background: #fa4e1c; color: #fff; font: 600 13.5px/1.2 Inter, system-ui, sans-serif; cursor: pointer; box-shadow: 0 6px 14px -8px rgba(250,78,28,.9); transition: background .15s, transform .1s; white-space: nowrap; }
    .wbs-trigger:hover { background: #e2400f; }
    .wbs-trigger:active { transform: scale(.98); }
    .wbs-trigger.is-ghost { background: #fff; color: #d93d0e; border: 1px solid #FAD7C8; box-shadow: none; }
    .wbs-trigger.is-ghost:hover { background: #FFF1EB; }

    .wbs { position: fixed; inset: 0; margin: auto; height: fit-content; width: min(560px, calc(100vw - 20px)); max-height: calc(100dvh - 20px); padding: 0; border: 0; border-radius: 18px; background: #fff; color: #1f2937; box-shadow: 0 30px 70px -20px rgba(0,0,0,.5); overflow: auto; font: 14px/1.45 Inter, system-ui, sans-serif; }
    .wbs[open] { animation: wbs-in .18s ease-out; }
    .wbs.is-closing { animation: wbs-out .14s ease-in forwards; }
    .wbs::backdrop { background: rgba(10,15,25,.55); backdrop-filter: blur(2px); }
    @keyframes wbs-in { from { opacity: 0; transform: translateY(10px) scale(.98); } to { opacity: 1; transform: none; } }
    @keyframes wbs-out { to { opacity: 0; transform: translateY(8px) scale(.98); } }
    .wbs-head { display: flex; align-items: flex-start; gap: 12px; padding: 18px 20px 12px; }
    .wbs-head h2 { margin: 0; font-size: 18px; font-weight: 800; color: #111827; }
    .wbs-head p { margin: 2px 0 0; font-size: 13px; color: #6b7280; }
    .wbs-x { margin-left: auto; border: 0; background: #F3F4F6; color: #4b5563; width: 32px; height: 32px; border-radius: 999px; font-size: 20px; line-height: 1; cursor: pointer; flex-shrink: 0; }
    .wbs-x:hover { background: #E5E7EB; }
    .wbs-body { padding: 0 20px 18px; }

    .wbs-view { position: relative; aspect-ratio: 4 / 3; max-height: 46vh; width: 100%; overflow: hidden; border-radius: 14px; background: #0f172a; }
    .wbs-view video { display: block; width: 100%; height: 100%; object-fit: cover; }
    .wbs-frame { position: absolute; left: 50%; top: 50%; height: 72%; aspect-ratio: 1 / 1; transform: translate(-50%, -50%); border-radius: 16px; box-shadow: 0 0 0 9999px rgba(0,0,0,.35); pointer-events: none; }
    .wbs-frame i { position: absolute; width: 22px; height: 22px; border: 3px solid #fff; }
    .wbs-frame i:nth-child(1) { left: -2px; top: -2px; border-right: 0; border-bottom: 0; border-radius: 10px 0 0 0; }
    .wbs-frame i:nth-child(2) { right: -2px; top: -2px; border-left: 0; border-bottom: 0; border-radius: 0 10px 0 0; }
    .wbs-frame i:nth-child(3) { left: -2px; bottom: -2px; border-right: 0; border-top: 0; border-radius: 0 0 0 10px; }
    .wbs-frame i:nth-child(4) { right: -2px; bottom: -2px; border-left: 0; border-top: 0; border-radius: 0 0 10px 0; }
    .wbs-laser { position: absolute; left: 6%; right: 6%; top: 50%; height: 2px; background: #fa4e1c; box-shadow: 0 0 10px #fa4e1c; animation: wbs-laser 1.6s ease-in-out infinite alternate; }
    @keyframes wbs-laser { from { top: 15%; } to { top: 85%; } }
    .wbs-view:not(.is-live) .wbs-frame, .wbs-view.is-busy .wbs-laser { display: none; }
    .wbs-cover { position: absolute; inset: 0; display: none; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 18px; text-align: center; color: #e5e7eb; font-size: 13.5px; background: rgba(15,23,42,.88); }
    .wbs-cover.is-on { display: flex; }
    .wbs-cover b { font-size: 15px; color: #fff; }
    .wbs-spin { width: 34px; height: 34px; border-radius: 999px; border: 3px solid rgba(255,255,255,.25); border-top-color: #fff; animation: wbs-spin .8s linear infinite; }
    @keyframes wbs-spin { to { transform: rotate(360deg); } }
    .wbs-ok { display: flex; width: 48px; height: 48px; align-items: center; justify-content: center; border-radius: 999px; background: #10B981; color: #fff; }

    .wbs-hint { margin: 10px 0 0; font-size: 12.5px; color: #6b7280; text-align: center; }
    .wbs-status { margin: 10px 0 0; min-height: 1.3em; border-radius: 10px; font-size: 13px; font-weight: 600; text-align: center; }
    .wbs-status.is-error { padding: 8px 10px; background: #FEF2F2; color: #B91C1C; }
    .wbs-status.is-ok { padding: 8px 10px; background: #ECFDF5; color: #047857; }
    .wbs-status.is-info { color: #4b5563; font-weight: 500; }
    .wbs-tools { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px; margin-top: 12px; }
    .wbs-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border-radius: 9px; border: 1px solid #E5E7EB; background: #fff; color: #374151; padding: 8px 13px; font: 600 13px/1.2 Inter, system-ui, sans-serif; cursor: pointer; }
    .wbs-btn:hover { background: #F9FAFB; }
    .wbs-btn.is-primary { border-color: #fa4e1c; background: #fa4e1c; color: #fff; }
    .wbs-btn.is-primary:hover { background: #e2400f; }
    .wbs-btn.is-stop { border-color: #FECACA; color: #B91C1C; }
    .wbs-btn[hidden], .wbs-tools [hidden] { display: none !important; }
    .wbs-btn:disabled { opacity: .55; cursor: not-allowed; }
    .wbs-select { border: 1px solid #E5E7EB; border-radius: 9px; padding: 7px 9px; font-size: 12.5px; max-width: 220px; background: #fff; }

    .wbs-or { display: flex; align-items: center; gap: 10px; margin: 16px 0 10px; font-size: 12px; color: #9ca3af; }
    .wbs-or::before, .wbs-or::after { content: ""; flex: 1; height: 1px; background: #E5E7EB; }
    .wbs-manual { display: flex; gap: 8px; }
    .wbs-manual input { flex: 1; min-width: 0; border: 1px solid #D1D5DB; border-radius: 10px; padding: 10px 12px; font: 600 15px/1.2 'SF Mono', Consolas, monospace; letter-spacing: .04em; text-transform: uppercase; }
    .wbs-manual input::placeholder { text-transform: none; letter-spacing: 0; font-weight: 500; }
    .wbs-manual input:focus { outline: 2px solid #fa4e1c; outline-offset: 1px; border-color: transparent; }
    .wbs-foot { display: flex; justify-content: flex-end; padding: 12px 20px; border-top: 1px solid #F3F4F6; background: #FAFAFA; }
    @media (max-width: 480px) { .wbs-head, .wbs-body { padding-left: 14px; padding-right: 14px; } .wbs-view { aspect-ratio: 3 / 4; max-height: 52vh; } }
</style>
<script src="{{ asset('js/waybill-scanner.js') }}?v={{ filemtime(public_path('js/waybill-scanner.js')) }}" data-zxing="{{ asset('js/vendor/zxing.min.js') }}" defer></script>
@endonce

@if ($renderDialog)
<dialog class="wbs" id="{{ $id }}" data-waybill-scanner data-lookup="{{ $lookup }}" aria-labelledby="{{ $id }}-title">
    <div class="wbs-head">
        <div>
            <h2 id="{{ $id }}-title">QR Scanner</h2>
            <p>Scan the QR code on a parcel's waybill to open its information.</p>
        </div>
        <button type="button" class="wbs-x" data-wbs-close aria-label="Close">&times;</button>
    </div>

    <div class="wbs-body">
        {{-- The camera area keeps its real colors in dark mode (picture, frame shade and laser together). --}}
        <div class="wbs-view" data-wbs-view data-keep-colors>
            <video playsinline muted autoplay></video>
            <div class="wbs-frame" aria-hidden="true"><i></i><i></i><i></i><i></i><span class="wbs-laser"></span></div>
            {{-- Before the camera starts, while searching, after a find --}}
            <div class="wbs-cover is-on" data-wbs-cover="idle">
                <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                <b data-wbs-idle-title>Camera is off</b>
                <span data-wbs-idle-text>Press Start Scanner to use the camera, or type the number below.</span>
            </div>
            <div class="wbs-cover" data-wbs-cover="busy"><span class="wbs-spin" aria-hidden="true"></span><b>Searching for the parcel…</b><span data-wbs-busy-code></span></div>
            <div class="wbs-cover" data-wbs-cover="ok">
                <span class="wbs-ok" aria-hidden="true"><svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>
                <b>Parcel found</b><span>Opening its details…</span>
            </div>
        </div>
        <p class="wbs-hint">Position the waybill QR code inside the scanning frame.</p>
        <p class="wbs-status is-info" data-wbs-status role="status" aria-live="polite"></p>

        <div class="wbs-tools">
            <button type="button" class="wbs-btn is-primary" data-wbs-start>
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v16l13-8z"/></svg> Start Scanner
            </button>
            <button type="button" class="wbs-btn is-stop" data-wbs-stop hidden>
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="1"/></svg> Stop Scanner
            </button>
            <select class="wbs-select" data-wbs-camera aria-label="Camera" hidden></select>
            <button type="button" class="wbs-btn" data-wbs-torch hidden>Flashlight on</button>
            <label class="wbs-btn" style="cursor:pointer;">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                Scan from a photo
                <input type="file" accept="image/*" data-wbs-photo hidden>
            </label>
        </div>

        <div class="wbs-or">or type the number</div>
        <form class="wbs-manual" data-wbs-manual novalidate>
            @csrf
            <input type="text" name="tracking_number" placeholder="e.g. PH2610264060134 or ALVY-…" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="50" aria-label="Waybill or tracking number">
            <button type="submit" class="wbs-btn is-primary">Find</button>
        </form>
    </div>

    <div class="wbs-foot"><button type="button" class="wbs-btn" data-wbs-close>Close</button></div>
</dialog>
@endif
