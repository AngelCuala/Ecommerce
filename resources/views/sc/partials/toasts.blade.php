{{--
    Sorting center pop-up messages (top right). Success and info close by themselves (longer messages stay longer,
    and hovering pauses them); errors stay until closed so they aren't missed.
--}}
@php
    $toasts = [];
    if (session('success')) $toasts[] = ['success', session('success')];
    if (session('info'))    $toasts[] = ['info', session('info')];
    if (session('error'))   $toasts[] = ['error', session('error')];
    if ($errors->any() && ! session('error')) $toasts[] = ['error', $errors->first()];
    $tone = [
        'success' => ['#067647', '#ABEFC6', '#ECFDF3', '<path d="M20 6 9 17l-5-5"/>', 'Done'],
        'info'    => ['#1D4ED8', '#BFDBFE', '#EFF6FF', '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>', 'Note'],
        'error'   => ['#B42318', '#FECDCA', '#FEF3F2', '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>', 'Something needs attention'],
    ];
@endphp
@if ($toasts)
    <div id="sc-toasts" style="position:fixed;top:16px;right:16px;z-index:1000;display:flex;flex-direction:column;gap:10px;width:min(400px,calc(100vw - 32px));" aria-live="polite">
        @foreach ($toasts as [$type, $message])
            @php [$ink, $line, $bg, $icon, $heading] = $tone[$type]; @endphp
            <div class="sc-toast" data-toast="{{ $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}"
                 style="display:flex;gap:12px;align-items:flex-start;padding:14px 14px 14px 16px;background:#fff;border:1px solid {{ $line }};border-left:4px solid {{ $ink }};border-radius:12px;box-shadow:0 12px 32px rgba(15,23,42,.16);transition:opacity .3s ease,transform .3s ease;">
                <span style="display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:{{ $bg }};color:{{ $ink }};flex-shrink:0;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                </span>
                <div style="flex:1;min-width:0;">
                    <p style="margin:0;font-size:13px;font-weight:700;color:{{ $ink }};">{{ $heading }}</p>
                    <p style="margin:2px 0 0;font-size:13px;line-height:1.45;color:#344054;overflow-wrap:anywhere;">{{ $message }}</p>
                </div>
                <button type="button" data-toast-close aria-label="Dismiss" style="border:0;background:none;color:#98A2B3;font-size:20px;line-height:1;cursor:pointer;padding:0 2px;">&times;</button>
            </div>
        @endforeach
    </div>
    <script>
    (function () {
        function hide(t) {
            if (!t.isConnected) return;
            t.style.opacity = '0'; t.style.transform = 'translateX(16px)';
            setTimeout(function () { t.remove(); }, 300);
        }
        document.querySelectorAll('#sc-toasts .sc-toast').forEach(function (t) {
            t.querySelector('[data-toast-close]').addEventListener('click', function () { hide(t); });
            if (t.dataset.toast === 'error') return;   // errors stay until closed
            // Long enough to read: about 60 ms per character, 5–12 seconds; hovering pauses it.
            var ms = Math.min(12000, Math.max(5000, t.textContent.trim().length * 60)), timer = null, left = ms, started = 0;
            function run() { started = Date.now(); timer = setTimeout(function () { hide(t); }, left); }
            t.addEventListener('mouseenter', function () { clearTimeout(timer); left -= Date.now() - started; });
            t.addEventListener('mouseleave', run);
            run();
        });
    })();
    </script>
@endif
