<x-admin-layout title="Courier Applications" active="couriers">

<div class="mb-5">
    <h1 class="font-display text-2xl font-bold" style="color:#222222;">Courier Applications</h1>
    <p class="mt-1 text-sm" style="color:#6b90aa;">Review, approve, or reject courier / rider registrations.</p>
</div>

{{-- Status filter tabs --}}
@php
    $tabs = [
        ''          => 'All (' . $counts['all'] . ')',
        'pending'   => 'Pending (' . $counts['pending'] . ')',
        'approved'  => 'Approved (' . $counts['approved'] . ')',
        'rejected'  => 'Rejected (' . $counts['rejected'] . ')',
        'suspended' => 'Suspended (' . $counts['suspended'] . ')',
    ];
@endphp
<div class="mb-5 flex flex-wrap gap-2">
    @foreach ($tabs as $val => $label)
        @php $active = (string) $status === (string) $val; @endphp
        <a href="{{ route('admin.couriers.index', $val ? ['status' => $val] : []) }}"
           class="rounded-full px-4 py-1.5 text-xs font-bold transition"
           style="{{ $active ? 'background:#fa4e1c;color:#fff;' : 'background:#e8f0f6;color:#fa4e1c;' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm" style="min-width:820px;">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Courier</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Vehicle</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Contact</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applied</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($couriers as $c)
                @php
                    $sc = match ($c->status) {
                        'approved'  => ['bg'=>'#ECFDF5','text'=>'#059669'],
                        'rejected'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                        'suspended' => ['bg'=>'#F3F4F6','text'=>'#6B7280'],
                        default     => ['bg'=>'#FFFBEB','text'=>'#B45309'],
                    };
                @endphp
                <tr style="border-top:1px solid #dce8f0;"
                    onmouseover="this.style.background='#FFF9F5';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-bold text-sm" style="background:#002b4d;color:#fff;">
                                {{ strtoupper(substr($c->first_name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-semibold leading-snug" style="color:#222222;">{{ $c->fullName() }}</p>
                                <p class="text-xs" style="color:#6b90aa;">{{ $c->user->email ?? '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">
                        {{ $c->vehicle_type }}<br><span class="text-xs" style="color:#9db3c4;">{{ $c->plate_number }}</span>
                    </td>
                    <td class="px-3 py-3 text-xs" style="color:#555555;">{{ $c->contact_no }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                            {{ ucfirst($c->status) }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-xs" style="color:#6b90aa;">{{ optional($c->submitted_at ?? $c->created_at)->format('M d, Y') }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.couriers.show', $c->id) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                           style="background:#e8f0f6;color:#1a4d6e;border:1px solid #cfdce8;"
                           onmouseover="this.style.background='#1a4d6e';this.style.color='#fff';"
                           onmouseout="this.style.background='#e8f0f6';this.style.color='#1a4d6e';">
                            Review
                        </a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No courier applications found.</td></tr>
            @endforelse
        </tbody>
    </table>
  </div>
</div>

</x-admin-layout>
