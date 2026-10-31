{{--
    Read a policy in a pop-up without leaving the page (sign-up "I agree to the Terms…").
    @include('partials.policy-popup', ['keys' => ['terms_of_service', 'privacy_policy'], 'agree' => 'agreeTerms'])
    A link with data-policy-popup="<key>" opens it (its href still works without JavaScript).
    "I agree" ticks the checkbox whose id is $agree.
--}}
@php $titles = collect(\App\Models\PlatformPolicy::defaultPolicies())->pluck('title', 'key'); @endphp
<style>
    .policy-pop { width: min(760px, calc(100vw - 24px)); max-height: min(86vh, 900px); padding: 0; border: 0; border-radius: 16px; box-shadow: 0 25px 60px -15px rgba(0,0,0,.35); overflow: hidden; }
    .policy-pop[open] { display: flex; flex-direction: column; }
    .policy-pop::backdrop { background: rgba(15,23,42,.5); backdrop-filter: blur(2px); }
    .policy-pop__head { display: flex; align-items: center; gap: 12px; padding: 16px 20px; border-bottom: 1px solid #F0E4DC; }
    .policy-pop__head h2 { flex: 1; margin: 0; font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.15rem; font-weight: 800; color: #222; }
    .policy-pop__x { border: 0; background: none; font-size: 22px; line-height: 1; color: #9a8b81; cursor: pointer; padding: 4px 6px; }
    .policy-pop__body { flex: 1; min-height: 0; overflow-y: auto; padding: 18px 22px; font-size: .875rem; line-height: 1.6; color: #444; }
    .policy-pop__foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 10px; padding: 12px 20px; border-top: 1px solid #F0E4DC; background: #FDF8F5; }
    .policy-pop__foot a { margin-right: auto; font-size: .8rem; color: #8a7a70; text-decoration: underline; }
    .policy-pop__btn { border-radius: 10px; padding: 9px 18px; font-size: .875rem; font-weight: 700; cursor: pointer; }
    .policy-pop__btn.ghost { border: 1px solid #E5E7EB; background: #fff; color: #374151; }
    .policy-pop__btn.primary { border: 0; background: #fa4e1c; color: #fff; }
</style>

@foreach ($keys as $key)
    @php $body = \App\Models\PlatformPolicy::body($key); @endphp
    <dialog class="policy-pop" id="policy-pop-{{ $key }}" data-policy-dialog="{{ $key }}" aria-labelledby="policy-pop-title-{{ $key }}">
        <div class="policy-pop__head">
            <h2 id="policy-pop-title-{{ $key }}">{{ $titles[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</h2>
            <button type="button" class="policy-pop__x" data-policy-close aria-label="Close">&times;</button>
        </div>
        <div class="policy-pop__body">
            @if ($body)
                {!! $body !!}
            @else
                <p>This policy is being prepared. Check back soon.</p>
            @endif
        </div>
        <div class="policy-pop__foot">
            <a href="{{ route('policies.show', $key) }}" target="_blank">Open in a new tab</a>
            <button type="button" class="policy-pop__btn ghost" data-policy-close>Close</button>
            @if (! empty($agree))
                <button type="button" class="policy-pop__btn primary" data-policy-agree>I agree</button>
            @endif
        </div>
    </dialog>
@endforeach

<script>
(function () {
    var agreeId = @json($agree ?? null);
    // Links inside a policy open in a new tab, so a half-filled form isn't lost.
    document.querySelectorAll('.policy-pop__body a[href]').forEach(function (a) {
        if (a.getAttribute('href').charAt(0) !== '#') { a.target = '_blank'; a.rel = 'noopener'; }
    });
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-policy-popup]');
        if (link) {
            var dialog = document.getElementById('policy-pop-' + link.dataset.policyPopup);
            if (dialog && typeof dialog.showModal === 'function') {
                e.preventDefault();
                e.stopPropagation();           // don't toggle the checkbox the link sits in
                dialog.showModal();
                dialog.querySelector('.policy-pop__body').scrollTop = 0;
            }
            return;
        }
        var dialog = e.target.closest('dialog[data-policy-dialog]');
        if (!dialog) return;
        if (e.target === dialog || e.target.closest('[data-policy-close]')) {   // backdrop, × or Close
            dialog.close();
        } else if (e.target.closest('[data-policy-agree]')) {
            var box = agreeId && document.getElementById(agreeId);
            if (box && !box.checked) {
                box.checked = true;
                box.dispatchEvent(new Event('change', { bubbles: true }));
            }
            dialog.close();
        }
    });
})();
</script>
