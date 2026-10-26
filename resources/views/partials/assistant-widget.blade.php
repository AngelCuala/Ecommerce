{{-- ALVY Assistant: help chat for guests and buyers (App\Services\Assistant). AI answers once ANTHROPIC_API_KEY is set; Quick help until then. --}}
@php
    $assistantOn = ! auth()->check() || auth()->user()->role === 'buyer';
    $aiMode = $assistantOn && app(\App\Services\Assistant\ShopAssistant::class)->available();
@endphp
@if ($assistantOn)
<style>
    #ai-assist { position: fixed; right: 20px; bottom: {{ auth()->check() ? '84px' : '20px' }}; z-index: 61; font-family: 'Inter', sans-serif; }
    #ai-launch { position: relative; display: flex; height: 64px; width: 64px; align-items: center; justify-content: center; margin-left: auto; border-radius: 999px;
                 background: #fff; border: 3px solid #fff; box-shadow: 0 10px 26px -8px rgba(0,43,77,.45), 0 0 0 1px #F2E6DF; animation: aiBob 3.2s ease-in-out infinite; }
    #ai-launch:hover { animation-play-state: paused; transform: scale(1.06); }
    #ai-launch .ai-online { position: absolute; right: 2px; bottom: 4px; height: 13px; width: 13px; border-radius: 999px; background: #22c55e; border: 2.5px solid #fff; }
    @keyframes aiBob { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    .ai-avatar .ai-eye { transform-origin: 32px 33px; animation: aiBlink 4.5s infinite; }
    @keyframes aiBlink { 0%, 92%, 100% { transform: scaleY(1); } 95% { transform: scaleY(.1); } }
    .ai-avatar .ai-bulb { animation: aiGlow 2.4s ease-in-out infinite; }
    @keyframes aiGlow { 0%, 100% { opacity: 1; } 50% { opacity: .55; } }
    #ai-greet { position: absolute; right: 74px; bottom: 14px; display: none; white-space: nowrap; border-radius: 1rem 1rem .3rem 1rem; padding: .5rem .8rem;
                background: #fff; color: #222; font-size: .8rem; font-weight: 600; box-shadow: 0 10px 24px -10px rgba(0,43,77,.4); border: 1px solid #F0E4DC; }
    #ai-greet.show { display: block; animation: aiPop .35s ease-out; }
    #ai-greet button { margin-left: .35rem; color: #9db3c4; font-size: .9rem; line-height: 1; }
    @keyframes aiPop { from { opacity: 0; transform: translateY(6px) scale(.96); } to { opacity: 1; transform: none; } }
    @media (prefers-reduced-motion: reduce) { #ai-launch, .ai-avatar .ai-eye, .ai-avatar .ai-bulb { animation: none; } }
    .ai-row { display: flex; align-items: flex-end; gap: .45rem; align-self: flex-start; max-width: 92%; }
    .ai-row .ai-msg { max-width: 100%; }
    .ai-row .ai-face { flex: none; display: flex; height: 26px; width: 26px; align-items: center; justify-content: center; border-radius: 999px; background: #fff; border: 1px solid #F2E6DF; }
    #ai-panel { position: absolute; right: 0; bottom: 0; display: none; flex-direction: column; width: 380px; max-width: calc(100vw - 40px); height: 560px; max-height: calc(100vh - 110px);
                overflow: hidden; border-radius: 1.1rem; background: #fff; border: 1px solid #E8EDF2; box-shadow: 0 24px 60px -20px rgba(0,43,77,.45); }
    #ai-assist.open #ai-panel { display: flex; }
    #ai-assist.open #ai-launch { display: none; }
    .ai-head { display: flex; align-items: center; gap: .6rem; padding: .8rem 1rem; color: #fff; background: linear-gradient(135deg, #002b4d, #0b4a7a); }
    .ai-head .ico { display: flex; height: 2.4rem; width: 2.4rem; align-items: center; justify-content: center; border-radius: 999px; background: #fff; }
    .ai-head button { border-radius: .5rem; padding: .3rem .45rem; color: rgba(255,255,255,.8); font-size: .72rem; font-weight: 600; }
    .ai-head button:hover { background: rgba(255,255,255,.12); color: #fff; }
    #ai-log { flex: 1; overflow-y: auto; padding: 1rem; background: #F7F9FB; display: flex; flex-direction: column; gap: .6rem; }
    .ai-msg { max-width: 85%; border-radius: 1rem; padding: .6rem .85rem; font-size: .85rem; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; }
    .ai-msg.bot { align-self: flex-start; background: #fff; color: #1f2937; border: 1px solid #E8EDF2; border-bottom-left-radius: .3rem; }
    .ai-msg.me { align-self: flex-end; background: #fa4e1c; color: #fff; border-bottom-right-radius: .3rem; }
    .ai-msg.err { align-self: center; background: #FEF2F2; color: #B91C1C; font-size: .78rem; }
    .ai-msg a { color: #fa4e1c; font-weight: 600; text-decoration: underline; }
    .ai-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
    .ai-chips button { border-radius: 999px; border: 1px solid #FBD5C5; background: #fff; padding: .35rem .7rem; font-size: .75rem; font-weight: 600; color: #C2410C; }
    .ai-chips button:hover { background: #FFF4EF; }
    .ai-dots span { display: inline-block; height: .45rem; width: .45rem; margin-right: .2rem; border-radius: 999px; background: #9db3c4; animation: aiDot 1s infinite ease-in-out; }
    .ai-dots span:nth-child(2) { animation-delay: .15s; } .ai-dots span:nth-child(3) { animation-delay: .3s; }
    @keyframes aiDot { 0%, 80%, 100% { opacity: .3; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }
    .ai-form { display: flex; gap: .5rem; border-top: 1px solid #E8EDF2; padding: .7rem; background: #fff; }
    .ai-form textarea { flex: 1; resize: none; max-height: 6rem; border-radius: .8rem; border: 1px solid #E2E8F0; padding: .55rem .75rem; font-size: .85rem; outline: none; }
    .ai-form textarea:focus { border-color: #fa4e1c; box-shadow: 0 0 0 3px rgba(250,78,28,.12); }
    .ai-form button { flex: none; height: 2.5rem; width: 2.5rem; align-self: flex-end; border-radius: .8rem; color: #fff; background: #fa4e1c; display: flex; align-items: center; justify-content: center; }
    .ai-form button:disabled { opacity: .5; cursor: not-allowed; }
    .ai-cards { display: flex; gap: .5rem; overflow-x: auto; padding: .1rem 0 .3rem 2rem; align-self: stretch; }
    .ai-card { flex: none; width: 128px; overflow: hidden; border-radius: .8rem; background: #fff; border: 1px solid #E8EDF2; text-decoration: none; color: #1f2937; transition: border-color .15s; }
    .ai-card:hover { border-color: #fa4e1c; }
    .ai-card img, .ai-card .noimg { display: block; height: 84px; width: 100%; object-fit: cover; background: #F3F4F6; }
    .ai-card p { padding: .35rem .5rem 0; font-size: .72rem; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .ai-card b { display: block; padding: .15rem .5rem .45rem; font-size: .78rem; color: #fa4e1c; }
    .ai-card s { font-weight: 400; font-size: .68rem; color: #9ca3af; margin-left: .25rem; }
    .ai-act { align-self: stretch; margin-left: 2rem; border-radius: .9rem; border: 1px solid #FBD5C5; background: #FFF7F3; padding: .6rem .7rem; font-size: .8rem; color: #57534E; }
    .ai-act p { font-weight: 600; color: #222; }
    .ai-act div { margin-top: .5rem; display: flex; gap: .4rem; }
    .ai-act button { border-radius: .6rem; padding: .35rem .8rem; font-size: .76rem; font-weight: 700; }
    .ai-act .yes { background: #fa4e1c; color: #fff; }
    .ai-act .no { background: #fff; color: #57534E; border: 1px solid #E5E7EB; }
    .ai-act.done { background: #F0FDF4; border-color: #BBF7D0; color: #166534; }
    .ai-act.fail { background: #FEF2F2; border-color: #FECACA; color: #B91C1C; }
    .ai-note { padding: 0 .9rem .6rem; background: #fff; font-size: .68rem; color: #9db3c4; text-align: center; }
</style>

<div id="ai-assist" data-history="{{ route('assistant.history') }}" data-ask="{{ route('assistant.ask') }}" data-reset="{{ route('assistant.reset') }}" data-confirm="{{ url('/assistant/actions') }}">
    <div id="ai-greet" role="status">Hi! Need help? <button type="button" data-ai-greet-close aria-label="Dismiss">&times;</button></div>
    <button id="ai-launch" type="button" aria-controls="ai-panel" aria-expanded="false" title="Ask ALVY Assistant">
        @include('partials.assistant-avatar', ['size' => 50, 'id' => 'launch'])
        <span class="ai-online" aria-hidden="true"></span>
    </button>
    <template id="ai-face-tpl">@include('partials.assistant-avatar', ['size' => 22, 'id' => 'msg'])</template>

    <section id="ai-panel" role="dialog" aria-label="ALVY Assistant">
        <header class="ai-head">
            <span class="ico" aria-hidden="true">@include('partials.assistant-avatar', ['size' => 32, 'id' => 'head'])</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold">ALVY Assistant <span class="ml-1 rounded px-1.5 py-0.5 align-middle text-[10px] font-bold" style="background:rgba(255,255,255,.15);" data-ai-mode="{{ $aiMode ? 'ai' : 'quick' }}">{{ $aiMode ? 'AI' : 'Quick help' }}</span></p>
                <p class="text-[11px]" style="color:rgba(255,255,255,.7);">Products, orders, delivery, payments and returns</p>
            </div>
            <button type="button" data-ai-reset title="Start a new conversation">Start over</button>
            <button type="button" data-ai-close aria-label="Close" style="font-size:1.1rem;line-height:1;">&times;</button>
        </header>

        <div id="ai-log" aria-live="polite">
            <div class="ai-row" data-ai-welcome><span class="ai-face" aria-hidden="true">@include('partials.assistant-avatar', ['size' => 22, 'id' => 'welcome'])</span><div class="ai-msg bot">Hi{{ auth()->check() ? ' ' . \Illuminate\Support\Str::before(auth()->user()->name, ' ') : '' }}! Welcome to ALVY. I'm your shopping assistant. I can help you find products, check your orders, track deliveries, understand promotions, and answer questions about your account. What can I help you with today?</div></div>
            <div class="ai-chips" data-ai-chips>
                <button type="button" data-q="Can you help me find products? Ask me what I'm looking for and my budget.">Find Products</button>
                <button type="button" data-q="Where is my latest order?">Track My Order</button>
                <button type="button" data-q="What promotions, flash sales and vouchers are available right now?">Check Promotions</button>
                <button type="button" data-q="How does shipping work, where do you deliver and how much is the fee?">Shipping Information</button>
                <button type="button" data-q="How do returns and refunds work?">Returns and Refunds</button>
                <button type="button" data-q="I need help from ALVY customer support.">Contact Support</button>
            </div>
        </div>

        <form class="ai-form" data-ai-form>
            <textarea rows="1" maxlength="{{ \App\Services\Assistant\ShopAssistant::MAX_INPUT }}" placeholder="Ask a question…" aria-label="Your question" required></textarea>
            <button type="submit" aria-label="Send">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>
        <p class="ai-note">{{ $aiMode ? 'AI can make mistakes.' : 'Automatic answers.' }} For changes to an order, use My Purchases.</p>
    </section>
</div>

<script>
(function () {
    var root = document.getElementById('ai-assist');
    var log = document.getElementById('ai-log'), form = root.querySelector('[data-ai-form]'), input = form.querySelector('textarea'), send = form.querySelector('button');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var loaded = false, busy = false;

    // Safe rendering: escape everything, then turn site paths (/...) and this site's URLs into links, **bold** into <strong>.
    function render(text) {
        var esc = String(text).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
        var origin = location.origin.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        esc = esc.replace(new RegExp('(' + origin + ')?(\\/(?:products|profile|cart|login|register|categories|checkout|shop)[^\\s<)\\]]*)', 'g'),
            function (m, o, path) { return '<a href="' + path + '">' + path + '</a>'; });
        return esc.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    }
    function add(role, text) {
        var el = document.createElement('div');
        el.className = 'ai-msg ' + role;
        el.innerHTML = role === 'bot' ? render(text) : String(text).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; });
        var node = el;
        if (role === 'bot') {   // the assistant's messages sit next to its little face
            node = document.createElement('div'); node.className = 'ai-row';
            var face = document.createElement('span'); face.className = 'ai-face'; face.setAttribute('aria-hidden', 'true');
            face.appendChild(document.getElementById('ai-face-tpl').content.cloneNode(true));
            node.appendChild(face); node.appendChild(el);
        }
        log.appendChild(node); log.scrollTop = log.scrollHeight;
        el.row = node;
        return el;
    }
    function chips(show) { var c = log.querySelector('[data-ai-chips]'); if (c) c.style.display = show ? '' : 'none'; }
    function req(url, opts) {
        return fetch(url, Object.assign({ headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin' }, opts || {}));
    }

    function open() {
        root.classList.add('open'); root.querySelector('#ai-launch').setAttribute('aria-expanded', 'true'); input.focus();
        if (loaded) return;
        loaded = true;
        req(root.dataset.history).then(function (r) { return r.ok ? r.json() : { messages: [] }; }).then(function (d) {
            (d.messages || []).forEach(function (m) { add(m.role === 'user' ? 'me' : 'bot', m.content); });
            if ((d.messages || []).length) chips(false);
        }).catch(function () {});
    }
    function ask(text) {
        text = text.trim();
        if (!text || busy) return;
        busy = true; send.disabled = true; chips(false);
        add('me', text); input.value = '';
        var typing = add('bot', ''); typing.innerHTML = '<span class="ai-dots"><span></span><span></span><span></span></span>';
        req(root.dataset.ask, { method: 'POST', body: JSON.stringify({ message: text }) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                typing.row.remove();
                if (res.ok) { add('bot', res.d.reply); cards(res.d.cards || []); actions(res.d.actions || []); }
                else add('err', (res.d && (res.d.message || (res.d.errors && Object.values(res.d.errors)[0][0]))) || 'Something went wrong. Please try again.');
            })
            .catch(function () { typing.row.remove(); add('err', 'Connection problem. Please try again.'); })
            .finally(function () { busy = false; send.disabled = false; input.focus(); });
    }

    var greet = document.getElementById('ai-greet');
    function hideGreet() { greet.classList.remove('show'); try { sessionStorage.setItem('ai-greeted', '1'); } catch (e) {} }
    try { if (!sessionStorage.getItem('ai-greeted')) setTimeout(function () { if (!root.classList.contains('open')) greet.classList.add('show'); }, 2500); } catch (e) {}
    greet.querySelector('[data-ai-greet-close]').addEventListener('click', function (e) { e.stopPropagation(); hideGreet(); });
    greet.addEventListener('click', function () { hideGreet(); open(); });
    root.querySelector('#ai-launch').addEventListener('click', function () { hideGreet(); open(); });
    root.querySelector('[data-ai-close]').addEventListener('click', function () { root.classList.remove('open'); root.querySelector('#ai-launch').setAttribute('aria-expanded', 'false'); });
    root.querySelector('[data-ai-reset]').addEventListener('click', function () {
        req(root.dataset.reset, { method: 'POST' }).finally(function () {
            Array.from(log.children).forEach(function (m) { if (!m.hasAttribute('data-ai-welcome') && !m.hasAttribute('data-ai-chips')) m.remove(); });
            chips(true);
        });
    });
    log.querySelectorAll('[data-ai-chips] button').forEach(function (b) { b.addEventListener('click', function () { ask(b.dataset.q || b.textContent); }); });

    // Product cards under a reply (links stay on this site).
    function cards(list) {
        if (!list.length) return;
        var row = document.createElement('div'); row.className = 'ai-cards';
        list.slice(0, 4).forEach(function (c) {
            if (!/^\/products\//.test(c.link)) return;
            var a = document.createElement('a'); a.className = 'ai-card'; a.href = c.link;
            var img = c.image && /^https?:\/\//.test(c.image) && c.image.indexOf(location.origin) === 0 ? document.createElement('img') : document.createElement('span');
            if (img.tagName === 'IMG') { img.src = c.image; img.alt = ''; img.loading = 'lazy'; } else { img.className = 'noimg'; }
            var name = document.createElement('p'); name.textContent = c.name;
            var price = document.createElement('b'); price.textContent = c.stock ? c.stock : c.price;
            if (c.was && !c.stock) { var was = document.createElement('s'); was.textContent = c.was; price.appendChild(was); }
            a.appendChild(img); a.appendChild(name); a.appendChild(price); row.appendChild(a);
        });
        log.appendChild(row); log.scrollTop = log.scrollHeight;
    }
    // Prepared actions: nothing happens until the buyer presses Confirm.
    function actions(list) {
        list.forEach(function (act) {
            var box = document.createElement('div'); box.className = 'ai-act';
            var label = document.createElement('p'); label.textContent = act.label + '?';
            var btns = document.createElement('div');
            var yes = document.createElement('button'); yes.type = 'button'; yes.className = 'yes'; yes.textContent = 'Confirm';
            var no = document.createElement('button'); no.type = 'button'; no.className = 'no'; no.textContent = 'Not now';
            btns.appendChild(yes); btns.appendChild(no); box.appendChild(label); box.appendChild(btns);
            no.addEventListener('click', function () { box.remove(); });
            yes.addEventListener('click', function () {
                yes.disabled = no.disabled = true; yes.textContent = 'Working…';
                req(root.dataset.confirm + '/' + encodeURIComponent(act.token), { method: 'POST' })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
                    .then(function (res) { box.className = 'ai-act ' + (res.ok ? 'done' : 'fail'); box.textContent = (res.ok ? '✓ ' : '') + (res.d.message || 'Something went wrong.'); })
                    .catch(function () { box.className = 'ai-act fail'; box.textContent = 'Connection problem. Please try again.'; });
            });
            log.appendChild(box); log.scrollTop = log.scrollHeight;
        });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); ask(input.value); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(input.value); } });
    input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 96) + 'px'; });
})();
</script>
@endif
