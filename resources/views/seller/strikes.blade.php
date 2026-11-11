<x-seller-layout title="Contact strikes" active="dashboard">
@php
    $tz = config('app.business_timezone');
    $limit = \App\Services\ContactStrikes::LIMIT;
    $window = \App\Services\ContactStrikes::WINDOW_DAYS;
@endphp
<div class="mx-auto max-w-4xl">
    <div class="mb-5">
        <span class="section-eyebrow">Store health</span>
        <h1 class="text-2xl">Contact strikes</h1>
        <p class="text-sm" style="color:#8a7f78;">A strike is given when you send a buyer a phone number, email, link or social-media account in chat. ALVY hides it
            automatically. {{ $limit }} strikes within {{ $window }} days and ALVY reviews your store. If a strike was a mistake (for example, the number
            was not a contact number), appeal it and an ALVY administrator will check. Strikes are deleted after one year.</p>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</div>@endif

    <div class="mb-5 rounded-xl px-5 py-4" style="background:{{ $count >= $limit ? '#FEF2F2' : ($count ? '#FFFBEB' : '#ECFDF5') }};border:1px solid {{ $count >= $limit ? '#FECACA' : ($count ? '#FDE68A' : '#A7F3D0') }};" data-strike-count>
        <p class="font-display text-2xl font-bold" style="color:{{ $count >= $limit ? '#991B1B' : ($count ? '#92400E' : '#047857') }};">{{ $count }} / {{ $limit }}</p>
        <p class="text-sm" style="color:#57534E;">{{ $count ? 'strikes counted in the last ' . $window . ' days' : 'No strikes counted. Thank you for keeping deals on ALVY.' }}</p>
    </div>

    <div class="overflow-hidden rounded-xl border bg-white" style="border-color:#ECE7E3;">
        @forelse ($list as $s)
            <div class="border-b px-5 py-4 text-sm last:border-b-0" style="border-color:#F2EEEA;" data-strike="{{ $s->id }}">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p><strong>{{ $s->created_at->copy()->tz($tz)->format('M j, Y g:i A') }}</strong> · {{ ucfirst($s->kindsLabel()) }}</p>
                    @if ($s->appeal_status)
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" style="{{ $s->appeal_status === 'accepted' ? 'background:#ECFDF5;color:#047857;' : ($s->appeal_status === 'rejected' ? 'background:#FEF2F2;color:#991B1B;' : 'background:#FFFBEB;color:#92400E;') }}">{{ $s->appealLabel() }}</span>
                    @elseif ($s->created_at->lt(now()->subDays($window)))
                        <span class="text-xs" style="color:#8a7f78;">No longer counted (older than {{ $window }} days)</span>
                    @endif
                </div>
                @if ($s->message)
                    <p class="mt-1" style="color:#57534E;">Your message (as saved, details hidden): “{{ \Illuminate\Support\Str::limit($s->message->body, 240) }}”</p>
                @endif
                @if ($s->appeal_reason)<p class="mt-1 text-xs" style="color:#8a7f78;">Your appeal: “{{ $s->appeal_reason }}”</p>@endif
                @if ($s->appeal_note)<p class="mt-1 text-xs" style="color:#57534E;">ALVY: {{ $s->appeal_note }}</p>@endif
                @if ($s->canAppeal())
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-semibold" style="color:#fa4e1c;">This was a mistake — appeal</summary>
                        <form method="POST" action="{{ route('seller.strikes.appeal', $s) }}" class="mt-2 flex flex-col gap-2">
                            @csrf
                            <textarea name="reason" rows="2" required minlength="10" maxlength="500" class="rounded-lg border px-3 py-2 text-sm" style="border-color:#E7E5E4;"
                                      placeholder="e.g. It was the product's model number, not a phone number."></textarea>
                            <button class="self-start rounded-lg px-4 py-1.5 text-xs font-bold text-white" style="background:#002b4d;">Send appeal</button>
                        </form>
                    </details>
                @endif
            </div>
        @empty
            <p class="px-5 py-10 text-center text-sm" style="color:#8a7f78;">You have no strikes.</p>
        @endforelse
    </div>
    @if ($list->hasPages())<div class="mt-4">{{ $list->links() }}</div>@endif
</div>
</x-seller-layout>
