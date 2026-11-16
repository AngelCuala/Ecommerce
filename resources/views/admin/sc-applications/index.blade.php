<x-admin-layout title="Sorting Center Registrations" active="sc-applications">

@include('partials.page-tabs', ['tabs' => [['Products', 'admin.product-approvals.index', 'admin.product-approvals.*'], ['Sellers', 'admin.seller-applications.index', 'admin.seller-applications.*'], ['Sorting centers', 'admin.sc-applications.index', 'admin.sc-applications.*']]])

@if(session('success'))
    <div class="mb-6 rounded-xl border p-4 text-sm" role="status" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

{{-- Sorting center sign-up is unlisted (not on any login or sign-up page): share this link with new partners. --}}
<div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border bg-white px-4 py-3" style="border-color:#E5E7EB;" data-sc-invite>
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background:#FFF1EB;color:#fa4e1c;">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold" style="color:#1f2937;">Private registration link</p>
        <p class="text-xs" style="color:#6b7280;">Sorting center sign-up isn't shown on any login or sign-up page. Send this link to partners you want to invite.</p>
    </div>
    <input type="text" readonly value="{{ route('sc.register') }}" class="w-72 max-w-full rounded-lg border px-3 py-1.5 font-mono text-xs" style="border-color:#E5E7EB;color:#374151;" data-sc-invite-url onclick="this.select()" aria-label="Sorting center registration link">
    <button type="button" class="rounded-lg px-4 py-1.5 text-xs font-semibold text-white" style="background:#fa4e1c;"
            onclick="var i=document.querySelector('[data-sc-invite-url]');navigator.clipboard.writeText(i.value).then(()=>{this.textContent='Copied!';setTimeout(()=>this.textContent='Copy link',1500)})">Copy link</button>
</div>

@php $current = request('status','all'); @endphp
<div class="mb-6 flex gap-2 flex-wrap">
    @foreach(['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Disapproved'] as $val=>$label)
        <a href="{{ route('admin.sc-applications.index',['status'=>$val]) }}"
           class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
           style="{{ $current===$val?'background:#fa4e1c;color:#fff;':'background:#F5F5F5;color:#666;' }}">
            {{ $label }}
            <span class="ml-1 opacity-60">({{ $val==='all'?$applications->count():$applications->where('status',$val)->count() }})</span>
        </a>
    @endforeach
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th scope="col" class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Business Name</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applicant</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Municipality</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Applied</th>
                <th scope="col" class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th scope="col" class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</th>
            </tr>
        </thead>
        <tbody>
            @php $filtered = $current==='all'?$applications:$applications->where('status',$current); @endphp
            @forelse($filtered as $app)
                @php
                    $badge = match($app->status){
                        'approved'=>['#ECFDF5','#059669','Approved'],
                        'rejected'=>['#FEF2F2','#DC2626','Disapproved'],
                        default   =>['rgba(250,78,28,.12)','#fa4e1c','Pending'],
                    };
                @endphp
                <tr style="border-top:1px solid #dce8f0;">
                    <td class="px-5 py-3 font-semibold" style="color:#222;">{{ $app->business_name }}</td>
                    <td class="px-3 py-3">
                        <p style="color:#222;">{{ $app->fullName() }}</p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $app->user->email ?? '—' }}</p>
                    </td>
                    <td class="px-3 py-3" style="color:#555;">{{ $app->municipality }}{{ $app->province?', '.$app->province:'' }}</td>
                    <td class="px-3 py-3" style="color:#6b90aa;">{{ $app->created_at->format('M d, Y') }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $badge[0] }};color:{{ $badge[1] }};">{{ $badge[2] }}</span>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('admin.sc-applications.show',$app) }}" class="text-xs font-semibold" style="color:#fa4e1c;">Review →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No registrations found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

</x-admin-layout>
