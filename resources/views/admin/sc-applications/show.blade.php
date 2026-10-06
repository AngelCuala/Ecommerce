<x-admin-layout title="Sorting Center Application" active="sc-applications">

@if (session('success'))
    <div class="mb-6 rounded-xl border p-4 text-sm" role="status" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-6 rounded-xl border p-4 text-sm" role="alert" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">
        {{ session('error') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-6 rounded-xl border p-4 text-sm" role="alert" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">
        {{ $errors->first() }}
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-[1fr_320px]">

    <div class="space-y-6">
        <div class="card p-6">
            <h2 class="font-display text-lg font-bold" style="color:#222222;">Applicant Details</h2>
            <dl class="mt-4 divide-y text-sm">
                @foreach ([
                    'Business Name'  => $application->business_name,
                    'Last Name'      => $application->last_name,
                    'First Name'     => $application->first_name,
                    'Middle Initial' => $application->middle_initial ?: '—',
                    'Sex'            => $application->sex,
                    'E-mail'         => $application->user->email ?? '—',
                    'Contact No.'    => $application->contact_no,
                    'Birthday'       => $application->birthday?->format('M d, Y'),
                    'Age'            => $application->age,
                    'Address'        => $application->address,
                    'Region'         => $application->region ?: '—',
                    'Coverage (Municipality)' => $application->municipality . ' · PSGC ' . $application->municipality_code,
                    'Applied On'     => $application->created_at->format('M d, Y H:i'),
                ] as $label => $value)
                    <div class="flex justify-between gap-4 py-2.5" style="border-color:#dce8f0;">
                        <dt style="color:#6b90aa;">{{ $label }}</dt>
                        <dd class="font-medium text-right" style="color:#222222;">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-3" style="color:#222222;">Requirements</h2>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.sc-applications.document', [$application, 'id']) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-semibold" style="border-color:#fa4e1c;color:#fa4e1c;">
                    View Valid ID
                </a>
                <a href="{{ route('admin.sc-applications.document', [$application, 'permit']) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-lg border-2 px-4 py-2 text-sm font-semibold" style="border-color:#fa4e1c;color:#fa4e1c;">
                    View Business / DTI Permit
                </a>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-1" style="color:#222222;">Status</h2>
            @php
                $badge = match ($application->status) {
                    'approved' => ['#ECFDF5', '#059669', 'Approved'],
                    'rejected' => ['#FEF2F2', '#DC2626', 'Disapproved'],
                    default    => ['rgba(250,78,28,.12)', '#fa4e1c', 'Pending'],
                };
            @endphp
            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold" style="background:{{ $badge[0] }};color:{{ $badge[1] }};">{{ $badge[2] }}</span>
            @if ($application->reviewed_at)
                <p class="mt-3 text-xs" style="color:#6b90aa;">Reviewed {{ $application->reviewed_at->format('M d, Y H:i') }}{{ $application->reviewedBy ? ' by ' . $application->reviewedBy->name : '' }}</p>
            @endif
            @if ($application->isRejected() && $application->rejection_reason)
                <p class="mt-3 text-sm" style="color:#DC2626;"><strong>Reason:</strong> {{ $application->rejection_reason }}</p>
            @endif
        </div>

        @if ($application->isPending())
            <div class="card p-6">
                <h3 class="font-semibold mb-3" style="color:#222222;">Approve Registration</h3>
                @if ($conflict)
                    <p class="text-xs mb-4" style="color:#DC2626;">
                        {{ $conflict->name }} already serves {{ $application->municipality }}. Approving is blocked so parcels are routed to one center only.
                    </p>
                @else
                    <p class="text-xs mb-4" style="color:#555555;">
                        Unlocks the LogiSort portal and assigns coverage to {{ $application->municipality }}. The applicant is notified by email.
                    </p>
                @endif
                <form action="{{ route('admin.sc-applications.approve', $application) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-gold w-full" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;"
                            @disabled($conflict) onclick="return confirm('Approve this sorting center?')">Approve</button>
                </form>
            </div>

            <div class="card p-6">
                <h3 class="font-semibold mb-3" style="color:#DC2626;">Disapprove Registration</h3>
                <form action="{{ route('admin.sc-applications.reject', $application) }}" method="POST" class="space-y-3">
                    @csrf @method('PATCH')
                    <div>
                        <label for="rejection_reason" class="text-xs font-semibold" style="color:#6b90aa;">Reason *</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" class="input mt-1" required maxlength="500"
                                  placeholder="e.g. DTI permit is expired">{{ old('rejection_reason') }}</textarea>
                    </div>
                    <button type="submit" class="w-full rounded-full border-2 py-2.5 text-sm font-semibold" style="border-color:#DC2626;color:#DC2626;"
                            onclick="return confirm('Disapprove this registration?')">Disapprove</button>
                </form>
            </div>
        @endif

        <a href="{{ route('admin.sc-applications.index') }}" class="block text-center text-sm" style="color:#fa4e1c;">← Back to all registrations</a>
    </div>
</div>

</x-admin-layout>
