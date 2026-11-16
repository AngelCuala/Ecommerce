<x-admin-layout title="Platform Settings" active="settings">
@include('partials.panel.plain')
@php
    $types = [
        'info'        => ['Info',        '#2563EB', '#EFF6FF', '<circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>'],
        'warning'     => ['Warning',     '#D97706', '#FFFBEB', '<path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>'],
        'maintenance' => ['Maintenance', '#475569', '#F1F5F9', '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8z"/>'],
        'promo'       => ['Promotion',   '#E0431A', '#FFF1EB', '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/>'],
    ];
    $audiences = [
        'all'     => ['All users', 'Buyers, sellers, sorting centers and riders', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.4c1.8.8 3 2.7 3 5.6"/>'],
        'buyers'  => ['Buyers only', 'People who shop on ALVY', '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>'],
        'sellers' => ['Sellers only', 'Stores selling on ALVY', '<path d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/>'],
    ];
    $stateOf = fn ($a) => $a->isExpired() ? 'expired' : ($a->is_active ? 'active' : 'inactive');
    $states = $announcements->map($stateOf)->countBy();
    $icon = fn (string $d, string $cls = 'h-4 w-4') => '<svg class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $d . '</svg>';
    $oldType = old('type', 'info');
    $oldAudience = old('audience', 'all');
@endphp
<style>
    .settings-tab { margin-right: 1.25rem; padding: .6rem .25rem; font-size: .875rem; font-weight: 600; white-space: nowrap; transition: color .15s; }
    /* admin-settings.js sets the active tab's colour inline; the orange underline marks it */
    .settings-tab[style*="transparent"]:hover { color: #222 !important; }

    .an-stat { display: flex; align-items: center; gap: .75rem; border-radius: .9rem; border: 1px solid #ECE7E3; background: #fff; padding: .85rem 1rem; }
    .an-stat-n { font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1.5rem; font-weight: 900; line-height: 1; color: #222; }
    .an-stat-ic { display: flex; height: 2.4rem; width: 2.4rem; align-items: center; justify-content: center; border-radius: .7rem; }
    .an-filter { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; border: 1px solid #ECE7E3; background: #fff; padding: .35rem .8rem; font-size: .8rem; font-weight: 600; color: #6b5e55; transition: all .15s; }
    .an-filter:hover { border-color: #fa4e1c; color: #222; }
    .an-filter.is-active { border-color: #222; background: #222; color: #fff; }
    .an-filter .n { border-radius: 999px; background: #F3EEEA; padding: 0 .4rem; font-size: .7rem; color: #6b5d54; }
    .an-filter.is-active .n { background: rgba(255,255,255,.2); color: #fff; }

    .an-card { position: relative; overflow: hidden; border-radius: .9rem; border: 1px solid #ECE7E3; background: #fff; transition: box-shadow .15s; }
    .an-card:hover { box-shadow: 0 10px 24px -18px rgba(0,0,0,.35); }
    .an-card::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--tone); }
    .an-card.is-dim { background: #FCFBFA; }
    .an-card.is-dim .an-body { opacity: .7; }
    .an-ic { display: flex; height: 2.4rem; width: 2.4rem; flex-shrink: 0; align-items: center; justify-content: center; border-radius: .7rem; background: var(--soft); color: var(--tone); }
    .an-pill { display: inline-flex; align-items: center; gap: .3rem; border-radius: 999px; padding: .15rem .55rem; font-size: .7rem; font-weight: 700; }
    .an-act { display: inline-flex; align-items: center; gap: .35rem; border-radius: .55rem; border: 1px solid #ECE7E3; background: #fff; padding: .35rem .7rem; font-size: .78rem; font-weight: 600; color: #44403C; transition: all .15s; }
    .an-act:hover { border-color: #222; color: #222; }
    .an-act.danger { color: #B91C1C; }
    .an-act.danger:hover { border-color: #DC2626; background: #FEF2F2; }

    .an-choice { position: relative; display: flex; cursor: pointer; align-items: center; gap: .6rem; border-radius: .75rem; border: 1.5px solid #ECE7E3; background: #fff; padding: .6rem .7rem; transition: all .15s; }
    .an-choice:hover { border-color: #d6c8be; }
    .an-choice input { position: absolute; opacity: 0; pointer-events: none; }
    .an-choice:has(input:checked) { border-color: var(--tone, #fa4e1c); background: var(--soft, #FFF1EB); }
    .an-choice:has(input:focus-visible) { outline: 2px solid #fa4e1c; outline-offset: 2px; }
    .an-quick { border-radius: 999px; border: 1px solid #ECE7E3; background: #fff; padding: .25rem .65rem; font-size: .72rem; font-weight: 600; color: #57534E; transition: all .15s; }
    .an-quick:hover, .an-quick.is-on { border-color: #fa4e1c; color: #d93d0e; background: #FFF1EB; }
    .an-preview { border-radius: .9rem; border: 1px dashed #E6D9CF; background: #FDF8F5; padding: .8rem; }
</style>

@if ($errors->any())
    <div class="mb-5 rounded-xl border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
        <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- Tabs --}}
<div class="mb-5 flex border-b" style="border-color:#ECE7E3;" id="settingsTabs">
    <button type="button" class="settings-tab -mb-px border-b-2" data-tab="announcements" style="border-color:#fa4e1c;color:#222;">Announcements</button>
    <button type="button" class="settings-tab -mb-px border-b-2" data-tab="policies" style="border-color:transparent;color:#8a7f78;">Policies</button>
</div>

{{-- Announcements tab --}}
<div id="tab-announcements" class="tab-content">

    {{-- Summary --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4" data-announcement-stats>
        @foreach ([['Total', $announcements->count(), '#002b4d', '#EEF4F9', '<path d="M3 11l18-8v18l-18-8z"/><path d="M11 13v6a2 2 0 0 1-4 0v-7"/>'],
                   ['Active', $states['active'] ?? 0, '#059669', '#ECFDF5', '<path d="M20 6 9 17l-5-5"/>'],
                   ['Expired', $states['expired'] ?? 0, '#B45309', '#FFFBEB', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
                   ['Inactive', $states['inactive'] ?? 0, '#78716C', '#F5F5F4', '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>']] as [$label, $n, $c, $bg, $d])
            <div class="an-stat">
                <span class="an-stat-ic" style="background:{{ $bg }};color:{{ $c }};">{!! $icon($d, 'h-5 w-5') !!}</span>
                <div><p class="an-stat-n">{{ $n }}</p><p class="text-xs pl-muted">{{ $label }}</p></div>
            </div>
        @endforeach
    </div>

        {{-- Post new announcement --}}
        <section class="pl-card mb-5" data-announcement-compose>
            <div class="pl-head"><h2>Post new announcement</h2></div>
            <form action="{{ route('admin.settings.announcements.store') }}" method="POST" class="pl-body grid gap-x-6 gap-y-4 lg:grid-cols-2" id="an-form"
                  data-confirm="It will be sent to the notifications of everyone in the audience you chose." data-confirm-title="Post this announcement?" data-confirm-ok="Post">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="pl-label" for="an-title">Title *</label>
                        <input id="an-title" type="text" name="title" value="{{ old('title') }}" class="input" required maxlength="255"
                               placeholder="e.g. Platform Maintenance Notice">
                    </div>
                    <div>
                        <div class="flex items-baseline justify-between"><label class="pl-label" for="an-body">Message *</label><span class="text-[11px] pl-muted" data-count>0 / 5000</span></div>
                        <textarea id="an-body" name="body" rows="5" class="input" required maxlength="5000"
                                  placeholder="Write your announcement here…">{{ old('body') }}</textarea>
                    </div>
                    <div>
                        <label class="pl-label" for="an-expires">Expires on (optional)</label>
                        <div class="mb-1.5 flex flex-wrap gap-1.5">
                            @foreach (['24' => '24 hours', '72' => '3 days', '168' => '1 week', '' => 'No expiry'] as $h => $l)
                                <button type="button" class="an-quick" data-expire="{{ $h }}">{{ $l }}</button>
                            @endforeach
                        </div>
                        <input id="an-expires" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="input">
                    </div>
                </div>
                <div class="space-y-4">
                    <fieldset>
                        <legend class="pl-label">Audience *</legend>
                        <div class="space-y-1.5">
                            @foreach ($audiences as $k => [$l, $hint, $d])
                                <label class="an-choice">
                                    <input type="radio" name="audience" value="{{ $k }}" @checked($oldAudience === $k) required data-reach="{{ $reach[$k] ?? 0 }}">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" style="background:#F5F2EF;color:#57534E;">{!! $icon($d) !!}</span>
                                    <span class="min-w-0 flex-1"><span class="block text-sm font-bold" style="color:#222;">{{ $l }}</span><span class="block text-[11px] pl-muted">{{ $hint }}</span></span>
                                    <span class="text-xs font-bold" style="color:#6b5e55;">{{ number_format($reach[$k] ?? 0) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend class="pl-label">Type *</legend>
                        <div class="grid grid-cols-2 gap-1.5">
                            @foreach ($types as $k => [$l, $c, $bg, $d])
                                <label class="an-choice" style="--tone:{{ $c }};--soft:{{ $bg }};">
                                    <input type="radio" name="type" value="{{ $k }}" @checked($oldType === $k) required>
                                    <span style="color:{{ $c }};">{!! $icon($d) !!}</span>
                                    <span class="text-sm font-semibold" style="color:#222;">{{ $l }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
                <div class="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-end lg:col-span-2" style="border-color:#F2EEEA;">
                    {{-- Live preview of the notification users get --}}
                    <div class="an-preview min-w-0 flex-1" aria-live="polite">
                        <p class="mb-2 text-[11px] font-bold uppercase tracking-wider pl-muted">Preview</p>
                        <div class="flex gap-2.5 rounded-xl bg-white p-3" style="box-shadow:0 4px 14px -10px rgba(0,0,0,.4);">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" data-preview-icon></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold" style="color:#222;" data-preview-title>Your title</p>
                                <p class="line-clamp-2 text-xs" style="color:#57534E;" data-preview-body>Your message shows here.</p>
                            </div>
                        </div>
                        <p class="mt-2 text-xs font-semibold" style="color:#6b5e55;" data-preview-reach></p>
                    </div>
                    <button type="submit" class="pl-btn flex w-full items-center justify-center gap-2 sm:w-auto sm:px-6">
                        {!! $icon('<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>') !!} Post announcement
                    </button>
                </div>
            </form>
        </section>
    <div>

        {{-- Existing announcements --}}
        <div>
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-base font-extrabold" style="color:#222;">Posted announcements</h2>
                @if ($announcements->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5" data-announcement-filters>
                        @foreach (['all' => 'All', 'active' => 'Active', 'expired' => 'Expired', 'inactive' => 'Inactive'] as $k => $l)
                            <button type="button" class="an-filter {{ $k === 'all' ? 'is-active' : '' }}" data-filter="{{ $k }}">
                                {{ $l }} <span class="n">{{ $k === 'all' ? $announcements->count() : ($states[$k] ?? 0) }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="space-y-3">
                @forelse ($announcements as $ann)
                    @php
                        $t = $types[$ann->type] ?? $types['info'];
                        $state = $stateOf($ann);
                        $pill = ['active' => ['Active', '#ECFDF5', '#047857'], 'expired' => ['Expired', '#FFFBEB', '#B45309'], 'inactive' => ['Inactive', '#F5F5F4', '#57534E']][$state];
                        $aud = $audiences[$ann->audience] ?? [ucfirst($ann->audience), '', $audiences['all'][2]];
                        $posted = $ann->published_at ?? $ann->created_at;
                    @endphp
                    <article class="an-card {{ $state !== 'active' ? 'is-dim' : '' }}" style="--tone:{{ $t[1] }};--soft:{{ $t[2] }};" data-announcement data-state="{{ $state }}">
                        <div class="an-body flex gap-3 p-4 pl-5">
                            <span class="an-ic">{!! $icon($t[3], 'h-5 w-5') !!}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <h3 class="mr-1 text-sm font-bold" style="color:#222;">{{ $ann->title }}</h3>
                                    <span class="an-pill" style="background:{{ $pill[1] }};color:{{ $pill[2] }};">{{ $pill[0] }}</span>
                                    <span class="an-pill" style="background:{{ $t[2] }};color:{{ $t[1] }};">{{ $t[0] }}</span>
                                    <span class="an-pill" style="background:#F5F2EF;color:#57534E;">{!! $icon($aud[2], 'h-3 w-3') !!} {{ $aud[0] }}</span>
                                </div>
                                <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed" style="color:#44403C;">{{ $ann->body }}</p>
                                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs pl-muted">
                                    <span title="{{ $posted->copy()->tz(config('app.business_timezone'))->format('M j, Y g:i A') }}">Posted {{ $posted->diffForHumans() }} by {{ $ann->admin->name ?? 'Admin' }}</span>
                                    @if ($ann->expires_at)
                                        <span title="{{ $ann->expires_at->copy()->tz(config('app.business_timezone'))->format('M j, Y g:i A') }}">{{ $ann->isExpired() ? 'Expired' : 'Expires' }} {{ $ann->expires_at->diffForHumans() }}</span>
                                    @else
                                        <span>No expiry</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2 border-t px-4 py-2.5" style="border-color:#F2EEEA;">
                            @unless ($state === 'expired')
                                <form action="{{ route('admin.settings.announcements.toggle', $ann->id) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button class="an-act">
                                        {!! $ann->is_active ? $icon('<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>', 'h-3.5 w-3.5') : $icon('<path d="M7 4v16l13-8z"/>', 'h-3.5 w-3.5') !!}
                                        {{ $ann->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @endunless
                            <form action="{{ route('admin.settings.announcements.destroy', $ann->id) }}" method="POST"
                                  data-confirm="&quot;{{ $ann->title }}&quot; will be removed from this list. Notifications already sent stay with users." data-confirm-title="Delete this announcement?" data-confirm-ok="Delete">
                                @csrf @method('DELETE')
                                <button class="an-act danger">{!! $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>', 'h-3.5 w-3.5') !!} Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="flex items-center gap-3 rounded-2xl border px-5 py-4" style="border-color:#ECE7E3;background:#fff;">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:#FFF1EB;color:#fa4e1c;">{!! $icon('<path d="M3 11l18-8v18l-18-8z"/><path d="M11 13v6a2 2 0 0 1-4 0v-7"/>', 'h-5 w-5') !!}</span>
                        <div><p class="text-sm font-bold" style="color:#222;">No announcements yet</p>
                        <p class="text-sm pl-muted">Post one above. It goes to everyone in the audience you choose.</p></div>
                    </div>
                @endforelse
                <p class="hidden rounded-xl border px-4 py-6 text-center text-sm pl-muted" style="border-color:#ECE7E3;background:#fff;" data-filter-empty>No announcements here.</p>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    // Filter the list by state.
    var cards = document.querySelectorAll('[data-announcement]');
    document.querySelectorAll('[data-filter]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('[data-filter]').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            var shown = 0;
            cards.forEach(function (c) {
                var on = btn.dataset.filter === 'all' || c.dataset.state === btn.dataset.filter;
                c.classList.toggle('hidden', !on);
                if (on) shown++;
            });
            document.querySelector('[data-filter-empty]').classList.toggle('hidden', shown > 0);
        });
    });

    // Compose: counter, quick expiry and live preview.
    var form = document.getElementById('an-form');
    if (!form) return;
    var types = @json(collect($types)->map(fn ($t) => ['tone' => $t[1], 'soft' => $t[2], 'icon' => $icon($t[3])]));
    var title = form.querySelector('[name="title"]'), body = form.querySelector('[name="body"]'), expires = form.querySelector('[name="expires_at"]');
    function pad(n) { return String(n).padStart(2, '0'); }
    function update() {
        form.querySelector('[data-count]').textContent = body.value.length + ' / 5000';
        form.querySelector('[data-preview-title]').textContent = title.value.trim() || 'Your title';
        form.querySelector('[data-preview-body]').textContent = body.value.trim() || 'Your message shows here.';
        var t = types[(form.querySelector('[name="type"]:checked') || {}).value] || types.info;
        var ic = form.querySelector('[data-preview-icon]');
        ic.style.background = t.soft; ic.style.color = t.tone; ic.innerHTML = t.icon;
        var aud = form.querySelector('[name="audience"]:checked');
        var n = aud ? parseInt(aud.dataset.reach, 10) : 0;
        form.querySelector('[data-preview-reach]').textContent = 'Goes to ' + n.toLocaleString() + ' ' + (n === 1 ? 'person' : 'people') + "'s notifications.";
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.querySelectorAll('[data-expire]').forEach(function (b) {
        b.addEventListener('click', function () {
            form.querySelectorAll('[data-expire]').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            if (!b.dataset.expire) { expires.value = ''; return; }
            var d = new Date(Date.now() + parseInt(b.dataset.expire, 10) * 3600e3);
            expires.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        });
    });
    update();
})();
</script>

{{-- Policies tab --}}
<div id="tab-policies" class="tab-content hidden">
    <div class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr><th>Policy</th><th>Last updated</th><th>Status</th><th class="!text-right">Action</th></tr>
                </thead>
                <tbody>
                    @foreach (\App\Models\PlatformPolicy::defaultPolicies() as $p)
                        @php
                            $existing = $policies[$p['key']] ?? null;
                            // Not edited here yet, but the site already shows its built-in text.
                            $builtIn = ! $existing && \App\Models\PlatformPolicy::defaultContent($p['key']);
                        @endphp
                        <tr>
                            <td class="font-medium" style="color:#222;">{{ $p['title'] }}</td>
                            <td>
                                @if ($existing)
                                    <span class="pl-muted">Updated {{ $existing->updated_at->diffForHumans() }}</span>
                                @elseif ($builtIn)
                                    <span class="pl-muted" title="The text that came with ALVY; edit it to make your own version.">Built-in text</span>
                                @else
                                    <span style="color:#B91C1C;">Not yet written</span>
                                @endif
                            </td>
                            <td>
                                @if ($existing || $builtIn)
                                    <span class="pl-tag delivered" data-policy-status="{{ $p['key'] }}">Published</span>
                                @else
                                    <span class="pl-tag neutral" data-policy-status="{{ $p['key'] }}">Draft</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.policies.show', $p['key']) }}" class="pl-link">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="{{ asset('js/admin-settings.js') }}"></script>

</x-admin-layout>
