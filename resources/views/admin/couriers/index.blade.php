<x-admin-layout title="Courier Management" active="couriers">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

{{-- Filter tabs --}}
<div class="mb-6 flex gap-2 flex-wrap">
    @foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','suspended'=>'Suspended'] as $val=>$label)
        @php $active = request('status', 'all') === $val; @endphp
        <a href="{{ route('admin.couriers.index', ['status'=>$val]) }}"
           class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
           style="{{ $active ? 'background:#fa4e1c;color:#FFFFFF;' : 'background:#F5F5F5;color:#666666;' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Courier</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Vehicle</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Area</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applied</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @php
                $filtered = request('status','all') === 'all'
                    ? $couriers
                    : $couriers->where('status', request('status'));
            @endphp
            @forelse ($filtered as $c)
                @php
                    $sc = match($c->status) {
                        'approved'  => ['bg'=>'#ECFDF5','text'=>'#059669'],
                        'rejected'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                        'suspended' => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                        default     => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'],
                    };
                @endphp
                <tr style="border-top:1px solid #dce8f0;"
                    onmouseover="this.style.background='#e8f0f6';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <p class="font-semibold" style="color:#222222;">{{ $c->fullName() }}</p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $c->user->email ?? '' }}</p>
                    </td>
                    <td class="px-3 py-3 text-xs" style="color:#555555;">
                        {{ $c->vehicle_type }} · {{ $c->plate_number }}
                    </td>
                    <td class="px-3 py-3 text-xs" style="color:#555555;">{{ $c->municipality }}, {{ $c->province }}</td>
                    <td class="px-3 py-3 text-xs" style="color:#6b90aa;">{{ $c->created_at->format('M d, Y') }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                            {{ ucfirst($c->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.couriers.show', $c->id) }}"
                           class="text-xs font-semibold" style="color:#fa4e1c;">Review →</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No courier applications found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

</x-admin-layout>