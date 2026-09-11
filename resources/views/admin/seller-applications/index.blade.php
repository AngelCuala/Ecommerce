<x-admin-layout title="Seller Applications" active="seller-applications">

@if (session('success'))
    <div class="mb-6 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

{{-- Filter tabs --}}
<div class="mb-6 flex gap-2 flex-wrap">
    @foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $val=>$label)
        @php $active = request('status', 'all') === $val; @endphp
        <a href="{{ route('admin.seller-applications.index', ['status'=>$val]) }}"
           class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
           style="{{ $active ? 'background:#fa4e1c;color:#FFFFFF;' : 'background:#F5F5F5;color:#666666;' }}">
            {{ $label }}
            <span class="ml-1 opacity-60">
                ({{ $applications->where($val === 'all' ? fn($a)=>true : 'status', $val === 'all' ? true : $val)->count() }})
            </span>
        </a>
    @endforeach
</div>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applicant</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Shop Name</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applied</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</th>
            </tr>
        </thead>
        <tbody>
            @php
                $filtered = request('status','all') === 'all'
                    ? $applications
                    : $applications->where('status', request('status'));
            @endphp
            @forelse ($filtered as $app)
                @php
                    $sc = match($app->status) {
                        'approved' => ['bg'=>'#ECFDF5','color'=>'#059669'],
                        'rejected' => ['bg'=>'#FEF2F2','color'=>'#DC2626'],
                        default    => ['bg'=>'rgba(250,78,28,.12)','color'=>'#fa4e1c'],
                    };
                @endphp
                <tr style="border-top:1px solid #dce8f0;" onmouseover="this.style.background='#e8f0f6';" onmouseout="this.style.background='';">
                    <td class="px-5 py-3">
                        <p class="font-semibold" style="color:#222222;">{{ $app->full_name }}</p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $app->user->email }}</p>
                    </td>
                    <td class="px-3 py-3" style="color:#555555;">{{ $app->shop_name }}</td>
                    <td class="px-3 py-3" style="color:#6b90aa;">{{ $app->created_at->format('M d, Y') }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
                            {{ ucfirst($app->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.seller-applications.show', $app->id) }}"
                           class="text-xs font-semibold" style="color:#fa4e1c;">Review →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No applications found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

</x-admin-layout>