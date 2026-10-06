<x-admin-layout title="Sorting Center Applications" active="sc-applications">

@if (session('success'))
    <div class="mb-6 rounded-xl border p-4 text-sm" role="status" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

@php $current = request('status', 'all'); @endphp
<div class="mb-6 flex gap-2 flex-wrap">
    @foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Disapproved'] as $val => $label)
        <a href="{{ route('admin.sc-applications.index', ['status' => $val]) }}"
           class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
           @if($current === $val) aria-current="page" @endif
           style="{{ $current === $val ? 'background:#fa4e1c;color:#FFFFFF;' : 'background:#F5F5F5;color:#666666;' }}">
            {{ $label }}
            <span class="ml-1 opacity-60">({{ $val === 'all' ? $applications->count() : $applications->where('status', $val)->count() }})</span>
        </a>
    @endforeach
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th scope="col" class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Sorting Center</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applicant</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Municipality</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applied</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th scope="col" class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</th>
            </tr>
        </thead>
        <tbody>
            @php $filtered = $current === 'all' ? $applications : $applications->where('status', $current); @endphp
            @forelse ($filtered as $app)
                @php
                    $badge = match ($app->status) {
                        'approved' => ['#ECFDF5', '#059669', 'Approved'],
                        'rejected' => ['#FEF2F2', '#DC2626', 'Disapproved'],
                        default    => ['rgba(250,78,28,.12)', '#fa4e1c', 'Pending'],
                    };
                @endphp
                <tr style="border-top:1px solid #dce8f0;">
                    <td class="px-5 py-3 font-semibold" style="color:#222222;">{{ $app->business_name }}</td>
                    <td class="px-3 py-3">
                        <p style="color:#222222;">{{ $app->fullName() }}</p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $app->user->email ?? '—' }}</p>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $app->municipality }}{{ $app->province ? ', ' . $app->province : '' }}</td>
                    <td class="px-3 py-3" style="color:#6b90aa;">{{ $app->created_at->format('M d, Y') }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" style="background:{{ $badge[0] }};color:{{ $badge[1] }};">{{ $badge[2] }}</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.sc-applications.show', $app) }}" class="text-xs font-semibold" style="color:#fa4e1c;">Review →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No sorting center registrations found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

</x-admin-layout>
