<x-admin-layout title="Seller Application" active="seller-applications">
@php
    $a      = $application;
    $owner  = trim($a->first_name . ' ' . $a->last_name) ?: ($a->user->name ?? '—');
    $isCorp = $a->seller_type === 'Corporation';
    $image  = fn (?string $p) => $p && in_array(strtolower(pathinfo($p, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    $url    = fn (?string $p) => $p ? asset('storage/' . $p) : null;

    // Documents: the government ID first, then the business documents this seller type needs.
    $documents = collect([
        ['Government ID',   $a->government_id_path,   true],
        [$isCorp ? 'SEC Certificate' : 'DTI Certificate', $isCorp ? $a->sec_certificate_path : $a->dti_certificate_path, true],
        ['BIR Form 2303',   $a->bir_2303_path,        true],
        [$isCorp ? 'DTI Certificate' : 'SEC Certificate', $isCorp ? $a->dti_certificate_path : $a->sec_certificate_path, false],
        ['Business Permit', $a->business_permit_path, false],
    ])->filter(fn ($d) => $d[2] || $d[1])->values();   // optional ones only when uploaded

    $inArea = \App\Support\ServiceArea::covers($a->municipality_code, $a->province, $a->municipality);
    $checks = [
        ['Government ID uploaded', (bool) $a->government_id_path,
            $a->id_check_status === 'passed' ? 'Automatic check: looks like ' . ($a->id_check_note ?: 'a government-issued ID') . '.'
                : ($a->government_id_path ? 'Not checked automatically. Look at it below.' : 'Missing.')],
        [($isCorp ? 'SEC' : 'DTI') . ' certificate', (bool) ($isCorp ? $a->sec_certificate_path : $a->dti_certificate_path), $isCorp ? 'Required for corporations.' : 'Required for this seller type.'],
        ['BIR Form 2303', (bool) $a->bir_2303_path, 'Certificate of registration.'],
        ['TIN provided', filled($a->tin), $a->tin ?: 'Missing.'],
        ['Address in the service area', $inArea, $inArea ? \App\Support\ServiceArea::label() . '.' : 'Outside ' . \App\Support\ServiceArea::label() . '. It cannot be approved.'],
    ];
    $passed = collect($checks)->where(1, true)->count();

    $status = [
        'approved' => ['Approved', '#ECFDF5', '#047857'],
        'rejected' => ['Rejected', '#FEF2F2', '#B91C1C'],
    ][$a->status] ?? ['Pending review', '#FFF1EB', '#d93d0e'];

    $address = collect([trim(($a->street ?? '') . ''), $a->barangay ? 'Brgy. ' . $a->barangay : null, $a->municipality, $a->province, $a->region, $a->zip_code])->filter()->implode(', ');
    $reasons = ['The ID photo is unclear or cropped', 'The ' . ($isCorp ? 'SEC' : 'DTI') . ' certificate is missing or invalid', 'The BIR Form 2303 is missing or unreadable',
                'The business address is outside our service area', 'The details on the documents do not match the application'];
    $icon = fn (string $d, string $cls = 'h-4 w-4') => '<svg class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $d . '</svg>';
@endphp
<style>
    .sa-card { border-radius: 1rem; border: 1px solid #ECE7E3; background: #fff; }
    .sa-head { position: relative; overflow: hidden; border-radius: 1rem; padding: 1.4rem 1.5rem; color: #fff; background: linear-gradient(115deg, #002b4d 0%, #0b4a7a 60%, #fa4e1c 160%); }
    .sa-head h1 { color: #fff; }
    .sa-avatar { display: flex; height: 3.5rem; width: 3.5rem; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 1rem; background: #fa4e1c; font-size: 1.4rem; font-weight: 900; box-shadow: 0 8px 18px -8px rgba(0,0,0,.5); }
    .sa-tag { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; background: rgba(255,255,255,.14); padding: .25rem .7rem; font-size: .75rem; font-weight: 600; }
    .sa-sec-title { display: flex; align-items: center; gap: .5rem; font-family: 'Nunito', ui-sans-serif, system-ui; font-size: 1rem; font-weight: 800; color: #222; }
    .sa-sec-title span { display: flex; height: 1.9rem; width: 1.9rem; align-items: center; justify-content: center; border-radius: .6rem; background: #FFF1EB; color: #fa4e1c; }
    .sa-grid { display: grid; gap: .9rem 1.5rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 640px) { .sa-grid { grid-template-columns: 1fr; } }
    .sa-dt { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #8a9bab; }
    .sa-dd { margin-top: .15rem; font-size: .9rem; font-weight: 600; color: #222; overflow-wrap: anywhere; }
    .sa-dd.empty { color: #b3a79f; font-weight: 500; }
    .sa-doc { display: flex; flex-direction: column; overflow: hidden; border-radius: .85rem; border: 1px solid #ECE7E3; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .sa-doc:hover { border-color: #fa4e1c; box-shadow: 0 10px 24px -16px rgba(250,78,28,.7); }
    .sa-doc-img { display: flex; height: 8.5rem; align-items: center; justify-content: center; overflow: hidden; background: #F7F5F3; }
    .sa-doc-img img { height: 100%; width: 100%; object-fit: cover; }
    .sa-doc.missing { border-style: dashed; background: #FEFAF8; }
    .sa-check { display: flex; gap: .65rem; padding: .6rem 0; }
    .sa-check + .sa-check { border-top: 1px solid #F2EEEA; }
    .sa-dot { display: flex; height: 1.35rem; width: 1.35rem; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 999px; }
    .sa-chip { border-radius: 999px; border: 1px solid #ECE7E3; background: #fff; padding: .3rem .65rem; font-size: .72rem; font-weight: 600; color: #57534E; text-align: left; transition: all .15s; }
    .sa-chip:hover { border-color: #DC2626; color: #B91C1C; background: #FEF2F2; }
    .sa-btn { display: flex; width: 100%; align-items: center; justify-content: center; gap: .45rem; border-radius: .75rem; padding: .75rem 1rem; font-size: .9rem; font-weight: 700; transition: all .15s; }
    .sa-btn:disabled { cursor: not-allowed; opacity: .5; }
</style>

<div class="mx-auto max-w-6xl">

    <a href="{{ route('admin.seller-applications.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-semibold hover:underline" style="color:#6b90aa;">
        {!! $icon('<path d="m15 18-6-6 6-6"/>') !!} All applications
    </a>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#B91C1C;">{{ $errors->first() }}</div>
    @endif

    {{-- Header --}}
    <section class="sa-head" data-application-head>
        <div class="flex flex-wrap items-center gap-4">
            <span class="sa-avatar">{{ strtoupper(substr($a->shop_name ?: $owner, 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="font-display text-2xl font-extrabold">{{ $a->shop_name ?: 'Unnamed shop' }}</h1>
                    <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" style="background:{{ $status[1] }};color:{{ $status[2] }};" data-application-status>{{ $status[0] }}</span>
                </div>
                <p class="mt-0.5 text-sm opacity-85">by {{ $owner }} · applied {{ $a->created_at->diffForHumans() }} ({{ $a->created_at->format('M j, Y g:i A') }})</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @if ($a->line_of_business)<span class="sa-tag">{!! $icon('<path d="M3 9l1.5-5h15L21 9M3 9h18M5 12v8h14v-8"/>', 'h-3.5 w-3.5') !!} {{ $a->line_of_business }}</span>@endif
                    @if ($a->seller_type)<span class="sa-tag">{!! $icon('<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"/>', 'h-3.5 w-3.5') !!} {{ $a->seller_type }}</span>@endif
                    @if ($a->municipality)<span class="sa-tag">{!! $icon('<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>', 'h-3.5 w-3.5') !!} {{ $a->municipality }}{{ $a->province ? ', ' . $a->province : '' }}</span>@endif
                </div>
            </div>
        </div>
    </section>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_340px] lg:items-start">

        {{-- Left: details and documents --}}
        <div class="space-y-5">
            <section class="sa-card p-5">
                <h2 class="sa-sec-title"><span>{!! $icon('<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>') !!}</span> Owner</h2>
                <dl class="sa-grid mt-4">
                    @foreach (['First name' => $a->first_name, 'Last name' => $a->last_name, 'Email' => $a->user?->email, 'Mobile number' => $a->phone] as $k => $v)
                        <div><dt class="sa-dt">{{ $k }}</dt><dd class="sa-dd {{ filled($v) ? '' : 'empty' }}">{{ filled($v) ? $v : 'Not given' }}</dd></div>
                    @endforeach
                </dl>
            </section>

            <section class="sa-card p-5">
                <h2 class="sa-sec-title"><span>{!! $icon('<path d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/>') !!}</span> Store</h2>
                <dl class="sa-grid mt-4">
                    @foreach (['Shop name' => $a->shop_name, 'Shop category' => $a->line_of_business, 'Seller type' => $a->seller_type, 'TIN' => $a->tin] as $k => $v)
                        <div><dt class="sa-dt">{{ $k }}</dt><dd class="sa-dd {{ filled($v) ? '' : 'empty' }}">{{ filled($v) ? $v : 'Not given' }}</dd></div>
                    @endforeach
                </dl>
                @if ($a->description)
                    <div class="mt-4 rounded-xl p-3.5 text-sm leading-relaxed" style="background:#FDF8F5;color:#57534E;">
                        <p class="sa-dt mb-1">About the store</p>{{ $a->description }}
                    </div>
                @endif
            </section>

            <section class="sa-card p-5">
                <h2 class="sa-sec-title"><span>{!! $icon('<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>') !!}</span> Business address</h2>
                <p class="mt-3 text-sm font-semibold" style="color:#222;">{{ $address ?: 'Not given' }}</p>
                <dl class="sa-grid mt-4">
                    @foreach (['Region' => $a->region, 'Province' => $a->province, 'City / Municipality' => $a->municipality, 'Barangay' => $a->barangay, 'Street address' => $a->street, 'Postal code' => $a->zip_code] as $k => $v)
                        <div><dt class="sa-dt">{{ $k }}</dt><dd class="sa-dd {{ filled($v) ? '' : 'empty' }}">{{ filled($v) ? $v : 'Not given' }}</dd></div>
                    @endforeach
                </dl>
                @unless ($inArea)
                    <p class="mt-4 flex items-start gap-2 rounded-xl px-3.5 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;" data-outside-area>
                        {!! $icon('<path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>', 'mt-0.5 h-4 w-4 shrink-0') !!}
                        This address is outside the service area ({{ \App\Support\ServiceArea::label() }}), so the application can't be approved.
                    </p>
                @endunless
            </section>

            <section class="sa-card p-5">
                <h2 class="sa-sec-title"><span>{!! $icon('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>') !!}</span> Documents</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($documents as [$label, $path, $required])
                        @if ($path)
                            <a href="{{ $url($path) }}" target="_blank" class="sa-doc" @if ($image($path)) data-lightbox="{{ $url($path) }}" data-lightbox-title="{{ $label }}" @endif data-document="{{ $label }}">
                                <div class="sa-doc-img">
                                    @if ($image($path))
                                        <img src="{{ $url($path) }}" alt="{{ $label }}" loading="lazy">
                                    @else
                                        <span class="flex h-14 w-14 items-center justify-center rounded-xl text-xs font-extrabold" style="background:#FFF1EB;color:#fa4e1c;">{{ strtoupper(pathinfo($path, PATHINFO_EXTENSION)) }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between gap-2 px-3 py-2.5">
                                    <span class="text-sm font-bold" style="color:#222;">{{ $label }}</span>
                                    <span class="text-xs font-semibold" style="color:#fa4e1c;">{{ $image($path) ? 'Enlarge' : 'Open' }}</span>
                                </div>
                            </a>
                        @else
                            <div class="sa-doc missing" data-document-missing="{{ $label }}">
                                <div class="sa-doc-img" style="background:transparent;color:#d6a99a;">{!! $icon('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15l6-6M15 15 9 9"/>', 'h-8 w-8') !!}</div>
                                <div class="px-3 py-2.5"><span class="text-sm font-bold" style="color:#222;">{{ $label }}</span><p class="text-xs" style="color:#B91C1C;">Not uploaded</p></div>
                            </div>
                        @endif
                    @endforeach
                </div>
                @if ($a->government_id_path)
                    @include('partials.id-check-note', ['record' => $a])
                @endif
            </section>
        </div>

        {{-- Right: checklist and decision (stays in view while scrolling) --}}
        <aside class="space-y-4 lg:sticky lg:top-24">
            <section class="sa-card p-5" data-review-checklist>
                <div class="flex items-center justify-between">
                    <h2 class="sa-sec-title">Review checklist</h2>
                    <span class="text-xs font-bold" style="color:{{ $passed === count($checks) ? '#047857' : '#B45309' }};">{{ $passed }} / {{ count($checks) }}</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full" style="background:#F2EEEA;">
                    <div class="h-full rounded-full" style="width:{{ round($passed / count($checks) * 100) }}%;background:{{ $passed === count($checks) ? '#10B981' : '#F59E0B' }};"></div>
                </div>
                <div class="mt-3">
                    @foreach ($checks as [$label, $ok, $note])
                        <div class="sa-check">
                            <span class="sa-dot" style="background:{{ $ok ? '#ECFDF5' : '#FEF2F2' }};color:{{ $ok ? '#059669' : '#DC2626' }};">
                                {!! $ok ? $icon('<path d="M20 6 9 17l-5-5"/>', 'h-3 w-3') : $icon('<path d="M18 6 6 18M6 6l12 12"/>', 'h-3 w-3') !!}
                            </span>
                            <div class="min-w-0"><p class="text-sm font-semibold" style="color:#222;">{{ $label }}</p><p class="text-xs" style="color:#8a7f78;">{{ $note }}</p></div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($a->isPending())
                <section class="sa-card p-5" data-decision>
                    <h2 class="sa-sec-title">Decision</h2>
                    <p class="mt-1 text-xs" style="color:#8a7f78;">Approving makes this account a seller with access to the Seller Centre. The applicant is emailed either way.</p>

                    <form action="{{ route('admin.seller-applications.approve', $a->id) }}" method="POST" class="mt-4"
                          data-confirm="{{ $a->shop_name ?: 'This shop' }} will become a seller on ALVY and can start listing products." data-confirm-title="Approve this seller?" data-confirm-ok="Approve">
                        @csrf @method('PATCH')
                        <button type="submit" class="sa-btn" style="background:#059669;color:#fff;" @disabled(! $inArea)>
                            {!! $icon('<path d="M20 6 9 17l-5-5"/>') !!} Approve seller
                        </button>
                        @unless ($inArea)<p class="mt-1.5 text-center text-xs" style="color:#B91C1C;">Outside the service area.</p>@endunless
                    </form>

                    <div class="my-4 flex items-center gap-3 text-xs font-semibold" style="color:#b3a79f;"><span class="h-px flex-1" style="background:#ECE7E3;"></span>or<span class="h-px flex-1" style="background:#ECE7E3;"></span></div>

                    <form action="{{ route('admin.seller-applications.reject', $a->id) }}" method="POST" class="space-y-2.5"
                          data-confirm="The applicant is emailed this reason and can apply again." data-confirm-title="Reject this application?" data-confirm-ok="Reject">
                        @csrf @method('PATCH')
                        <label for="rejection_reason" class="text-xs font-bold" style="color:#57534E;">Reason for rejecting <span style="color:#DC2626;">*</span></label>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($reasons as $r)
                                <button type="button" class="sa-chip" data-reason="{{ $r }}">{{ $r }}</button>
                            @endforeach
                        </div>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="500" class="input" required
                                  placeholder="Tell the applicant what to fix…">{{ old('rejection_reason') }}</textarea>
                        <button type="submit" class="sa-btn" style="border:1.5px solid #DC2626;color:#DC2626;background:#fff;"
                                onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='#fff'">
                            {!! $icon('<path d="M18 6 6 18M6 6l12 12"/>') !!} Reject application
                        </button>
                    </form>
                </section>
            @else
                <section class="sa-card p-5">
                    <h2 class="sa-sec-title">Decision</h2>
                    <p class="mt-2 text-sm" style="color:#57534E;">This application was <strong style="color:{{ $status[2] }};">{{ strtolower($status[0]) }}</strong>
                        {{ $a->updated_at?->diffForHumans() }}.</p>
                    @if ($a->isRejected() && $a->rejection_reason)
                        <p class="mt-3 rounded-xl px-3.5 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;"><strong>Reason:</strong> {{ $a->rejection_reason }}</p>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</div>

{{-- Full-size document viewer --}}
<div id="sa-lightbox" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(10,15,25,.85);" role="dialog" aria-modal="true">
    <div class="w-full max-w-4xl">
        <div class="mb-2 flex items-center justify-between text-white">
            <p class="text-sm font-bold" data-lightbox-label></p>
            <div class="flex items-center gap-3">
                <a href="#" target="_blank" class="text-xs font-semibold underline opacity-80" data-lightbox-open>Open in new tab</a>
                <button type="button" class="text-2xl leading-none opacity-80" data-lightbox-close aria-label="Close">&times;</button>
            </div>
        </div>
        <img src="" alt="" class="w-full rounded-xl bg-white object-contain" style="max-height:80vh;" data-lightbox-img>
    </div>
</div>

<script>
(function () {
    // Documents open full size in the viewer; Esc, × or a click outside closes it.
    var box = document.getElementById('sa-lightbox');
    function close() { box.classList.add('hidden'); box.classList.remove('flex'); }
    document.addEventListener('click', function (e) {
        var link = e.target.closest('[data-lightbox]');
        if (link) {
            e.preventDefault();
            box.querySelector('[data-lightbox-img]').src = link.dataset.lightbox;
            box.querySelector('[data-lightbox-img]').alt = link.dataset.lightboxTitle;
            box.querySelector('[data-lightbox-label]').textContent = link.dataset.lightboxTitle;
            box.querySelector('[data-lightbox-open]').href = link.dataset.lightbox;
            box.classList.remove('hidden'); box.classList.add('flex');
            return;
        }
        if (e.target === box || e.target.closest('[data-lightbox-close]')) close();

        // Common rejection reasons fill the box (the admin can still edit it).
        var chip = e.target.closest('[data-reason]');
        if (chip) {
            var ta = document.getElementById('rejection_reason');
            ta.value = ta.value.trim() ? ta.value.trim() + ' ' + chip.dataset.reason + '.' : chip.dataset.reason + '.';
            ta.focus();
        }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
</x-admin-layout>
