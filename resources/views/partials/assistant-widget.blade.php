{{-- ALLIE (ALVY's assistant): help chat for guests and buyers (App\Services\Assistant). AI answers once the AI key is set (GEMINI_API_KEY by default, see config/services.php); Quick help until then. --}}
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

    /* ALLIE's moods. Parts of her face: .ai-body .ai-bulb (heart) .ai-bow .ai-eye .ai-look .ai-happy-eyes .ai-cheeks .ai-mouth .ai-grin */
    .ai-avatar .ai-body, .ai-avatar .ai-bulb, .ai-avatar .ai-bow, .ai-avatar .ai-cheeks { transform-box: fill-box; transform-origin: center; }
    .ai-avatar .ai-body { transform-origin: 50% 100%; }
    .ai-avatar .ai-happy-eyes, .ai-avatar .ai-grin { opacity: 0; transition: opacity .15s; }
    .ai-avatar .ai-eye, .ai-avatar .ai-mouth { transition: opacity .15s; }
    .ai-avatar.is-happy .ai-eye, .ai-avatar.is-happy .ai-mouth { opacity: 0; }
    .ai-avatar.is-happy .ai-happy-eyes, .ai-avatar.is-happy .ai-grin { opacity: 1; }
    .ai-avatar.is-happy .ai-cheeks { transform: scale(1.25); transition: transform .2s; }
    /* Sad: when you're about to hide her. */
    .ai-avatar .ai-brows, .ai-avatar .ai-tear, .ai-avatar .ai-frown { opacity: 0; transition: opacity .18s; }
    .ai-avatar .ai-tear { transform-box: fill-box; transform-origin: 50% 0; }
    .ai-avatar .ai-look, .ai-avatar .ai-cheeks { transition: transform .2s, opacity .2s; }
    .ai-avatar.is-sad .ai-brows, .ai-avatar.is-sad .ai-frown { opacity: 1; }
    .ai-avatar.is-sad .ai-mouth, .ai-avatar.is-sad .ai-grin, .ai-avatar.is-sad .ai-happy-eyes { opacity: 0 !important; }
    .ai-avatar.is-sad .ai-eye { opacity: 1 !important; }
    .ai-avatar.is-sad .ai-look { transform: translateY(1.3px) !important; animation: none !important; }
    .ai-avatar.is-sad .ai-cheeks { opacity: .35; transform: scale(.85); }
    .ai-avatar.is-sad .ai-tear { opacity: 1; animation: aiTear 1.6s ease-in infinite; }
    @keyframes aiTear { 0% { transform: translateY(-2px) scale(.4); opacity: 0; } 20% { transform: translateY(0) scale(1); opacity: 1; } 75% { transform: translateY(5px) scale(1); opacity: 1; } 100% { transform: translateY(8px) scale(.7); opacity: 0; } }
    .ai-avatar.is-sad .ai-bulb { animation: none !important; transform: rotate(-24deg) scale(.82); filter: saturate(.45); transition: transform .3s, filter .3s; }
    .ai-avatar.is-sad .ai-body { animation: aiSigh 2.4s ease-in-out infinite !important; }
    @keyframes aiSigh { 0%, 100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(1.5px) rotate(-3deg); } }
    #ai-assist.ai-dragging #ai-launch { z-index: 70; }   /* in front of the × target, so her sad face shows */
    #ai-assist.ai-dragging #ai-launch.is-over-drop { transform: scale(.92); }
    #ai-launch.is-over-drop .ai-body { animation: aiTremble .32s linear infinite !important; }
    @keyframes aiTremble { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-1px) rotate(-2deg); } 75% { transform: translateX(1px) rotate(2deg); } }
    /* Hiding: a sad little wobble, then she shrinks and sinks away. */
    #ai-assist.ai-leaving #ai-launch { animation: aiBye .75s cubic-bezier(.5,0,.75,0) forwards !important; pointer-events: none; }
    @keyframes aiBye { 0% { transform: none; } 25% { transform: rotate(-8deg); } 45% { transform: rotate(6deg); } 60% { transform: rotate(-3deg) scale(1); opacity: 1; } 100% { transform: translateY(26px) scale(.2); opacity: 0; } }
    #ai-assist.ai-leaving #ai-panel { animation: aiPanelBye .6s ease-in .25s forwards; pointer-events: none; }
    @keyframes aiPanelBye { to { opacity: 0; transform: translateY(16px) scale(.96); } }
    #ai-assist.ai-leaving .ai-hide-x, #ai-assist.ai-leaving #ai-greet { display: none !important; }
    /* On the button and in the header she is lively; the small faces next to messages only blink. */
    #ai-launch .ai-bulb, .ai-head .ai-bulb { animation: aiBeat 2.6s ease-in-out infinite; }
    @keyframes aiBeat { 0%, 40%, 100% { transform: scale(1); } 10% { transform: scale(1.28); } 20% { transform: scale(.96); } 30% { transform: scale(1.18); } }
    #ai-launch .ai-bow { animation: aiBow 3.6s ease-in-out infinite; }
    @keyframes aiBow { 0%, 70%, 100% { transform: rotate(0); } 78% { transform: rotate(-14deg); } 86% { transform: rotate(12deg); } 93% { transform: rotate(-6deg); } }
    #ai-launch .ai-look { animation: aiLook 7s ease-in-out infinite; }
    @keyframes aiLook { 0%, 30%, 62%, 100% { transform: translate(0, 0); } 36%, 46% { transform: translate(-1.6px, .4px); } 52%, 58% { transform: translate(1.6px, -.4px); } }
    #ai-launch .ai-body { animation: aiTilt 9s ease-in-out infinite; }
    @keyframes aiTilt { 0%, 80%, 100% { transform: rotate(0); } 85% { transform: rotate(-9deg); } 91% { transform: rotate(6deg); } 96% { transform: rotate(-2deg); } }
    /* Hover the button: squinty-eyed smile and a little bounce. */
    #ai-launch:hover .ai-body { animation: aiBounce .5s ease-out; }
    @keyframes aiBounce { 0% { transform: scale(1); } 40% { transform: scale(1.1, .92); } 70% { transform: scale(.96, 1.05); } 100% { transform: scale(1); } }
    /* "Hi! Need help?": a hello hop. */
    #ai-launch.ai-hello .ai-body { animation: aiHop .9s ease-out 2; }
    @keyframes aiHop { 0%, 100% { transform: translateY(0) scale(1); } 15% { transform: translateY(0) scale(1.08, .9); } 45% { transform: translateY(-7px) scale(.95, 1.06); } 75% { transform: translateY(0) scale(1.05, .95); } }
    /* Opening the chat: her face pops in. */
    #ai-assist.open .ai-head .ico { animation: aiPopIn .45s cubic-bezier(.3,1.6,.5,1); }
    @keyframes aiPopIn { from { transform: scale(.3) rotate(-20deg); } to { transform: none; } }
    /* Thinking: looks around, tilts her head, heart beats faster. */
    #ai-assist.ai-thinking .ai-head .ai-look { animation: aiThinkLook 1.6s ease-in-out infinite; }
    @keyframes aiThinkLook { 0%, 100% { transform: translate(0, -1px); } 30% { transform: translate(-1.8px, -1.4px); } 65% { transform: translate(1.8px, -1.4px); } }
    #ai-assist.ai-thinking .ai-head .ai-body { animation: aiThinkTilt 1.6s ease-in-out infinite; }
    @keyframes aiThinkTilt { 0%, 100% { transform: rotate(-6deg); } 50% { transform: rotate(6deg); } }
    #ai-assist.ai-thinking .ai-head .ai-bulb { animation-duration: .9s; }
    /* A reply arrived: a happy hop. */
    #ai-assist.ai-cheer .ai-head .ai-body { animation: aiHop .8s ease-out; }
    /* Her face next to each new message pops in. */
    .ai-row .ai-face { animation: aiPopIn .35s cubic-bezier(.3,1.6,.5,1); }
    /* Little hearts that float up from her. */
    .ai-heart { position: fixed; z-index: 70; pointer-events: none; color: #FF5C8A; font-size: 14px; line-height: 1; animation: aiFloat 1.3s ease-out forwards; }
    @keyframes aiFloat { 0% { opacity: 0; transform: translate(0, 0) scale(.4); } 15% { opacity: 1; transform: translate(calc(var(--dx) * .2), -8px) scale(1); }
                         100% { opacity: 0; transform: translate(var(--dx), -54px) scale(.8) rotate(var(--rot)); } }
    #ai-greet { position: absolute; right: 74px; bottom: 14px; display: none; white-space: nowrap; border-radius: 1rem 1rem .3rem 1rem; padding: .5rem .8rem;
                background: #fff; color: #222; font-size: .8rem; font-weight: 600; box-shadow: 0 10px 24px -10px rgba(0,43,77,.4); border: 1px solid #F0E4DC; }
    #ai-greet.show { display: block; animation: aiPop .35s ease-out; }
    #ai-greet button { margin-left: .35rem; color: #9db3c4; font-size: .9rem; line-height: 1; }
    @keyframes aiPop { from { opacity: 0; transform: translateY(6px) scale(.96); } to { opacity: 1; transform: none; } }
    /* Moving ALLIE: drag her anywhere; she snaps to the nearest side. Drop her on the × to hide her; the tab brings her back. */
    #ai-launch { cursor: grab; touch-action: none; user-select: none; -webkit-user-select: none; -webkit-user-drag: none; }
    #ai-assist.ai-dragging, body:has(#ai-assist.ai-dragging) { user-select: none; -webkit-user-select: none; }
    #ai-assist.ai-dragging #ai-launch { cursor: grabbing; animation: none; transform: scale(1.1); box-shadow: 0 18px 36px -10px rgba(0,43,77,.5), 0 0 0 1px #F2E6DF; }
    #ai-assist.ai-dragging #ai-greet, #ai-assist.ai-dragging .ai-hide-x { display: none !important; }
    #ai-assist.ai-snap { transition: left .28s cubic-bezier(.3,1.3,.5,1), top .28s cubic-bezier(.3,1.3,.5,1); }
    #ai-assist.ai-side-left #ai-greet { right: auto; left: 74px; border-radius: 1rem 1rem 1rem .3rem; }
    .ai-hide-x { position: absolute; top: -6px; left: -6px; z-index: 2; display: flex; height: 22px; width: 22px; align-items: center; justify-content: center;
                 border-radius: 999px; background: #002b4d; color: #fff; font-size: 14px; line-height: 1; border: 2px solid #fff; box-shadow: 0 2px 6px rgba(0,0,0,.25);
                 opacity: 0; transform: scale(.6); transition: opacity .15s, transform .15s; }
    #ai-assist.ai-side-left .ai-hide-x { left: auto; right: -6px; }
    #ai-assist:hover .ai-hide-x, .ai-hide-x:focus-visible { opacity: 1; transform: none; }
    @media (hover: none) { .ai-hide-x { opacity: .9; transform: scale(.9); } }
    #ai-assist.open .ai-hide-x { display: none; }
    #ai-drop { position: fixed; left: 50%; bottom: 22px; z-index: 69; display: none; flex-direction: column; align-items: center; gap: .3rem; transform: translateX(-50%);
               pointer-events: none; font-size: .72rem; font-weight: 700; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,.5); }
    #ai-drop span { display: flex; height: 56px; width: 56px; align-items: center; justify-content: center; border-radius: 999px; background: rgba(0,43,77,.82);
                    border: 2px solid rgba(255,255,255,.9); font-size: 26px; transition: transform .15s, background .15s; }
    #ai-assist.ai-dragging #ai-drop { display: flex; animation: aiPop .2s ease-out; }
    #ai-drop.over span { transform: scale(1.22); background: #DC2626; }
    #ai-peek { position: fixed; right: 0; z-index: 61; display: none; align-items: center; gap: .4rem; padding: .3rem .7rem .3rem .3rem; border-radius: 999px 0 0 999px;
               background: #fff; border: 1px solid #F0E4DC; border-right: 0; box-shadow: 0 8px 20px -8px rgba(0,43,77,.45); color: #002b4d; font-size: .75rem; font-weight: 700;
               transform: translateX(calc(100% - 40px)); transition: transform .25s cubic-bezier(.3,1.3,.5,1); }
    #ai-peek:hover, #ai-peek:focus-visible { transform: none; }
    #ai-peek .ai-peek-face { display: flex; flex: none; }
    #ai-assist.ai-side-left #ai-peek { right: auto; left: 0; flex-direction: row-reverse; padding: .3rem .3rem .3rem .7rem; border-radius: 0 999px 999px 0;
                                       border: 1px solid #F0E4DC; border-left: 0; transform: translateX(calc(-100% + 40px)); }
    #ai-assist.ai-side-left #ai-peek:hover, #ai-assist.ai-side-left #ai-peek:focus-visible { transform: none; }
    #ai-assist.ai-hidden #ai-launch, #ai-assist.ai-hidden #ai-greet, #ai-assist.ai-hidden #ai-panel, #ai-assist.ai-hidden .ai-hide-x { display: none !important; }
    #ai-assist.ai-hidden #ai-peek { display: flex; animation: aiPop .3s ease-out; }
    #ai-launch.ai-back { animation: aiPopIn .5s cubic-bezier(.3,1.6,.5,1); }
    @media (prefers-reduced-motion: reduce) {
        #ai-launch, .ai-avatar *, #ai-assist .ai-head .ico, .ai-row .ai-face, #ai-drop, #ai-peek, #ai-assist.ai-leaving #ai-panel { animation: none !important; }
        #ai-assist.ai-snap, #ai-peek { transition: none; }
        .ai-heart { display: none; }
    }
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
    <button id="ai-launch" type="button" aria-controls="ai-panel" aria-expanded="false" title="Ask {{ \App\Services\Assistant\ShopAssistant::NAME }}">
        @include('partials.assistant-avatar', ['size' => 50, 'id' => 'launch'])
        <span class="ai-online" aria-hidden="true"></span>
    </button>
    <button type="button" class="ai-hide-x" data-ai-hide aria-label="Hide {{ \App\Services\Assistant\ShopAssistant::NAME }} from the screen" title="Hide {{ \App\Services\Assistant\ShopAssistant::NAME }}">&times;</button>
    <div id="ai-drop" aria-hidden="true"><span>&times;</span>Drop to hide</div>
    {{-- Shown while ALLIE is hidden: brings her back. --}}
    <button id="ai-peek" type="button" aria-label="Show {{ \App\Services\Assistant\ShopAssistant::NAME }} again" title="Show {{ \App\Services\Assistant\ShopAssistant::NAME }}">
        <span class="ai-peek-face" aria-hidden="true">@include('partials.assistant-avatar', ['size' => 30, 'id' => 'peek'])</span>
        Show {{ \App\Services\Assistant\ShopAssistant::NAME }}
    </button>
    <template id="ai-face-tpl">@include('partials.assistant-avatar', ['size' => 22, 'id' => 'msg'])</template>

    <section id="ai-panel" role="dialog" aria-label="{{ \App\Services\Assistant\ShopAssistant::NAME }}, ALVY's assistant">
        <header class="ai-head">
            <span class="ico" aria-hidden="true">@include('partials.assistant-avatar', ['size' => 32, 'id' => 'head'])</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold">{{ \App\Services\Assistant\ShopAssistant::NAME }} <span class="ml-1 rounded px-1.5 py-0.5 align-middle text-[10px] font-bold" style="background:rgba(255,255,255,.15);" data-ai-mode="{{ $aiMode ? 'ai' : 'quick' }}">{{ $aiMode ? 'AI' : 'Quick help' }}</span></p>
                <p class="text-[11px]" style="color:rgba(255,255,255,.7);">ALVY's assistant · products, orders, delivery and more</p>
            </div>
            <button type="button" data-ai-reset title="Start a new conversation">Start over</button>
            <button type="button" data-ai-hide title="Hide {{ \App\Services\Assistant\ShopAssistant::NAME }} from the screen (a tab at the edge brings her back)">Hide</button>
            <button type="button" data-ai-close aria-label="Close" style="font-size:1.1rem;line-height:1;">&times;</button>
        </header>

        <div id="ai-log" aria-live="polite">
            <div class="ai-row" data-ai-welcome><span class="ai-face" aria-hidden="true">@include('partials.assistant-avatar', ['size' => 22, 'id' => 'welcome'])</span><div class="ai-msg bot">Hi{{ auth()->check() ? ' ' . \Illuminate\Support\Str::before(auth()->user()->name, ' ') : '' }}! Welcome to ALVY. I'm {{ \App\Services\Assistant\ShopAssistant::NAME }}, your shopping assistant. I can help you find products, check your orders, track deliveries, understand promotions, and answer questions about your account. What can I help you with today?</div></div>
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
        root.classList.add('open'); root.querySelector('#ai-launch').setAttribute('aria-expanded', 'true');
        placePanel(); input.focus();
        if (loaded) return;
        loaded = true;
        req(root.dataset.history).then(function (r) { return r.ok ? r.json() : { messages: [] }; }).then(function (d) {
            (d.messages || []).forEach(function (m) { add(m.role === 'user' ? 'me' : 'bot', m.content); });
            if ((d.messages || []).length) chips(false);
        }).catch(function () {});
    }
    // ── ALLIE's moods ──
    var calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function happy(avatar, ms) {
        if (!avatar) return;
        avatar.classList.add('is-happy');
        clearTimeout(avatar._happy);
        if (ms) avatar._happy = setTimeout(function () { avatar.classList.remove('is-happy'); }, ms);
    }
    function hearts(from, count) {
        if (calm || !from) return;
        var r = from.getBoundingClientRect();
        for (var i = 0; i < count; i++) {
            var h = document.createElement('span');
            h.className = 'ai-heart'; h.textContent = '\u2665'; h.setAttribute('aria-hidden', 'true');
            h.style.left = (r.left + r.width / 2 - 7 + (Math.random() * 24 - 12)) + 'px';
            h.style.top = (r.top + r.height * .25) + 'px';
            h.style.fontSize = (11 + Math.random() * 8) + 'px';
            h.style.setProperty('--dx', (Math.random() * 44 - 22) + 'px');
            h.style.setProperty('--rot', (Math.random() * 50 - 25) + 'deg');
            h.style.animationDelay = (i * 0.12) + 's';
            document.body.appendChild(h);
            setTimeout(function (el) { el.remove(); }.bind(null, h), 1500 + i * 120);
        }
    }
    function cheer() {
        root.classList.remove('ai-cheer'); void root.offsetWidth; root.classList.add('ai-cheer');
        setTimeout(function () { root.classList.remove('ai-cheer'); }, 900);
        happy(root.querySelector('.ai-head .ai-avatar'), 1300);
    }
    var launch = root.querySelector('#ai-launch'), lastHover = 0;
    launch.addEventListener('mouseenter', function () {
        if (root.classList.contains('ai-dragging')) return;   // she follows the pointer while dragged
        happy(launch.querySelector('.ai-avatar'));
        if (Date.now() - lastHover > 1500) { lastHover = Date.now(); hearts(launch, 3); }
    });
    launch.addEventListener('mouseleave', function () { launch.querySelector('.ai-avatar').classList.remove('is-happy'); });

    function ask(text) {
        text = text.trim();
        if (!text || busy) return;
        busy = true; send.disabled = true; chips(false);
        root.classList.add('ai-thinking');
        // "Thank you" / "salamat": she's happy and sends hearts.
        var thanked = /\b(thank|thanks|ty|tysm|salamat|love you|ily)\b/i.test(text);
        add('me', text); input.value = '';
        var typing = add('bot', ''); typing.innerHTML = '<span class="ai-dots"><span></span><span></span><span></span></span>';
        req(root.dataset.ask, { method: 'POST', body: JSON.stringify({ message: text }) })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                typing.row.remove();
                if (res.ok) {
                    add('bot', res.d.reply); cards(res.d.cards || []); actions(res.d.actions || []);
                    cheer();
                    if (thanked) hearts(root.querySelector('.ai-head .ico'), 6);
                }
                else add('err', (res.d && (res.d.message || (res.d.errors && Object.values(res.d.errors)[0][0]))) || 'Something went wrong. Please try again.');
            })
            .catch(function () { typing.row.remove(); add('err', 'Connection problem. Please try again.'); })
            .finally(function () { busy = false; send.disabled = false; root.classList.remove('ai-thinking'); input.focus(); });
    }

    var greet = document.getElementById('ai-greet');
    function hideGreet() { greet.classList.remove('show'); try { sessionStorage.setItem('ai-greeted', '1'); } catch (e) {} }
    try { if (!sessionStorage.getItem('ai-greeted')) setTimeout(function () {
        if (root.classList.contains('open') || root.classList.contains('ai-hidden')) return;
        greet.classList.add('show');
        launch.classList.add('ai-hello');   // a hello hop next to "Hi! Need help?"
        happy(launch.querySelector('.ai-avatar'), 1800);
        setTimeout(function () { launch.classList.remove('ai-hello'); }, 1900);
    }, 2500); } catch (e) {}
    greet.querySelector('[data-ai-greet-close]').addEventListener('click', function (e) { e.stopPropagation(); hideGreet(); });
    greet.addEventListener('click', function () { hideGreet(); open(); });
    root.querySelector('#ai-launch').addEventListener('click', function (e) {
        if (dragged) { dragged = false; e.preventDefault(); return; }   // the end of a drag isn't a click
        hideGreet(); open();
    });
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

    // ── Moving, hiding and bringing ALLIE back (remembered in this browser) ──
    var POS_KEY = 'allie-position', HIDDEN_KEY = 'allie-hidden', SIZE = 64, EDGE = 16;
    var drop = document.getElementById('ai-drop'), peek = document.getElementById('ai-peek'), panel = document.getElementById('ai-panel');
    var bottomGap = parseFloat(getComputedStyle(root).bottom) || 20;   // stays clear of the bottom bar when signed in
    var pos = null, dragged = false;
    function store(key, value) { try { value === null ? localStorage.removeItem(key) : localStorage.setItem(key, value); } catch (e) {} }
    try { pos = JSON.parse(localStorage.getItem(POS_KEY) || 'null'); } catch (e) { pos = null; }
    if (!pos || (pos.side !== 'left' && pos.side !== 'right') || typeof pos.y !== 'number') pos = null;

    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
    function topFor(p) { return clamp(Math.round(p.y * innerHeight), 12, innerHeight - SIZE - bottomGap); }
    // Without a saved spot she keeps her usual place (bottom right); with one, she sits on that side at that height.
    function place() {
        root.classList.toggle('ai-side-left', !!pos && pos.side === 'left');
        if (!pos) { root.style.left = root.style.top = root.style.right = root.style.bottom = ''; }
        else {
            root.style.right = root.style.bottom = 'auto';
            root.style.top = topFor(pos) + 'px';
            root.style.left = (pos.side === 'left' ? EDGE : innerWidth - SIZE - EDGE) + 'px';
        }
        var peekTop = pos ? topFor(pos) : innerHeight - bottomGap - SIZE;
        peek.style.top = (peekTop + 14) + 'px';
        if (root.classList.contains('open')) placePanel();
    }
    // The chat opens next to her, fully on screen.
    function placePanel() {
        if (!pos) { panel.style.position = panel.style.left = panel.style.top = panel.style.right = panel.style.bottom = ''; return; }
        panel.style.position = 'fixed'; panel.style.right = panel.style.bottom = 'auto';
        var w = panel.offsetWidth, h = panel.offsetHeight;
        panel.style.left = (pos.side === 'left' ? EDGE : innerWidth - w - EDGE) + 'px';
        panel.style.top = clamp(topFor(pos) + SIZE - h, 12, Math.max(12, innerHeight - h - 12)) + 'px';
    }

    function sad(avatar, on) { if (avatar) { avatar.classList.toggle('is-sad', on); if (on) avatar.classList.remove('is-happy'); } }
    var leaving = false;
    function hideAllie(focusTab) {
        if (leaving || root.classList.contains('ai-hidden')) return;
        var wasOpen = root.classList.contains('open');
        hideGreet();
        store(HIDDEN_KEY, '1');
        function done() {
            leaving = false;
            root.classList.remove('open', 'ai-leaving'); launch.setAttribute('aria-expanded', 'false');
            sad(launch.querySelector('.ai-avatar'), false); sad(root.querySelector('.ai-head .ai-avatar'), false);
            launch.classList.remove('is-over-drop');
            root.classList.add('ai-hidden');
            place();
            if (focusTab) peek.focus();
        }
        if (calm) return done();
        // She's sad to go: a sad face, a little wobble, then she shrinks away.
        leaving = true;
        sad(wasOpen ? root.querySelector('.ai-head .ai-avatar') : launch.querySelector('.ai-avatar'), true);
        root.classList.add('ai-leaving');
        setTimeout(done, wasOpen ? 850 : 780);
    }
    function showAllie() {
        root.classList.remove('ai-hidden'); store(HIDDEN_KEY, null);
        sad(launch.querySelector('.ai-avatar'), false);
        launch.classList.remove('ai-back'); void launch.offsetWidth; launch.classList.add('ai-back');
        setTimeout(function () { launch.classList.remove('ai-back'); }, 600);
        happy(launch.querySelector('.ai-avatar'), 1400); hearts(launch, 4);
        launch.focus();
    }
    root.querySelectorAll('[data-ai-hide]').forEach(function (b) {
        b.addEventListener('click', function (e) { e.stopPropagation(); hideAllie(true); });
    });
    peek.addEventListener('click', showAllie);
    // Hovering a "hide" button makes her sad; moving away cheers her up again.
    root.querySelectorAll('[data-ai-hide]').forEach(function (b) {
        var face = function () { return b.closest('.ai-head') ? root.querySelector('.ai-head .ai-avatar') : launch.querySelector('.ai-avatar'); };
        ['mouseenter', 'focus'].forEach(function (t) { b.addEventListener(t, function () { sad(face(), true); }); });
        ['mouseleave', 'blur'].forEach(function (t) { b.addEventListener(t, function () { if (!leaving) sad(face(), false); }); });
    });
    // Waiting in the tab she looks a bit sad, and cheers up when you come to get her.
    var peekFace = peek.querySelector('.ai-avatar');
    sad(peekFace, true);
    ['mouseenter', 'focus'].forEach(function (t) { peek.addEventListener(t, function () { sad(peekFace, false); peekFace.classList.add('is-happy'); }); });
    ['mouseleave', 'blur'].forEach(function (t) { peek.addEventListener(t, function () { peekFace.classList.remove('is-happy'); sad(peekFace, true); }); });

    // Drag with a mouse, finger or pen. A tiny wiggle still counts as a click. Once pressed, the pointer is
    // followed across the whole page, so a quick flick that leaves her bubble straight away still drags her.
    var start = null;
    launch.addEventListener('dragstart', function (e) { e.preventDefault(); });
    launch.addEventListener('pointerdown', function (e) {
        if (e.button !== 0) return;
        // Stop the browser starting its own text selection or link drag, which would cancel ours.
        if (e.pointerType === 'mouse') e.preventDefault();
        var r = root.getBoundingClientRect();
        start = { x: e.clientX, y: e.clientY, left: r.left, top: r.top, id: e.pointerId, moving: false };
        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', onCancel);
    });
    function stopFollowing() {
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        window.removeEventListener('pointercancel', onCancel);
    }
    function onUp(e) { endDrag(e, false); }
    function onCancel(e) { endDrag(e, true); }
    function overDrop(cx, cy) {
        var d = drop.querySelector('span').getBoundingClientRect();
        return Math.hypot(cx - (d.left + d.width / 2), cy - (d.top + d.height / 2)) < 64;
    }
    function onMove(e) {
        if (!start || e.pointerId !== start.id) return;
        var dx = e.clientX - start.x, dy = e.clientY - start.y;
        if (!start.moving) {
            if (Math.hypot(dx, dy) < 6) return;
            start.moving = true;
            root.classList.add('ai-dragging'); root.classList.remove('ai-snap'); hideGreet();
        }
        e.preventDefault();
        root.style.right = root.style.bottom = 'auto';
        root.style.left = clamp(start.left + dx, 0, innerWidth - SIZE) + 'px';
        root.style.top = clamp(start.top + dy, 0, innerHeight - SIZE) + 'px';
        var over = overDrop(e.clientX, e.clientY);
        if (over !== drop.classList.contains('over')) {
            drop.classList.toggle('over', over);
            launch.classList.toggle('is-over-drop', over);   // over the ×: she trembles and looks sad
            sad(launch.querySelector('.ai-avatar'), over);
        }
    }
    function endDrag(e, cancelled) {
        if (!start || e.pointerId !== start.id) return;
        stopFollowing();
        var moved = start.moving; start = null;
        if (!moved) return;
        dragged = true; setTimeout(function () { dragged = false; }, 50);
        root.classList.remove('ai-dragging');
        var r = root.getBoundingClientRect(), dropped = !cancelled && drop.classList.contains('over');
        drop.classList.remove('over');
        if (dropped) { hideAllie(false); return; }   // she leaves from the ×; her saved spot stays where it was
        launch.classList.remove('is-over-drop'); sad(launch.querySelector('.ai-avatar'), false);
        if (!cancelled) {
            pos = { side: r.left + r.width / 2 < innerWidth / 2 ? 'left' : 'right', y: r.top / innerHeight };
            store(POS_KEY, JSON.stringify(pos));
        }
        root.classList.add('ai-snap'); place();
        setTimeout(function () { root.classList.remove('ai-snap'); }, 320);
    }
    // Keyboard: with ALLIE focused, Arrow Left / Right moves her to that side, Up / Down moves her up or down.
    launch.addEventListener('keydown', function (e) {
        var keys = { ArrowLeft: 1, ArrowRight: 1, ArrowUp: 1, ArrowDown: 1 };
        if (!keys[e.key]) return;
        e.preventDefault();
        var cur = pos || { side: 'right', y: (innerHeight - bottomGap - SIZE) / innerHeight };
        pos = { side: e.key === 'ArrowLeft' ? 'left' : e.key === 'ArrowRight' ? 'right' : cur.side,
                y: clamp(cur.y + (e.key === 'ArrowUp' ? -0.08 : e.key === 'ArrowDown' ? 0.08 : 0), 0, 1) };
        store(POS_KEY, JSON.stringify(pos));
        root.classList.add('ai-snap'); place();
        setTimeout(function () { root.classList.remove('ai-snap'); }, 320);
    });
    window.addEventListener('resize', place);

    place();
    try { if (localStorage.getItem(HIDDEN_KEY) === '1') root.classList.add('ai-hidden'); } catch (e) {}
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); ask(input.value); } });
    input.addEventListener('input', function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 96) + 'px'; });
})();
</script>
@endif
