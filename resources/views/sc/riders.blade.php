@extends('sc.layout')
@section('title', 'Rider Management')
@section('icon', '🏍️')
@section('topbar-right')
    <input type="text" class="search-input" placeholder="Search name or ID..."
           onkeyup="filterTable(this.value,'riders-tbody')" />
@endsection

@section('content')
<div class="page-header"><h1>Rider Management</h1></div>
<div class="page-body">

    <div class="flex-between mb-16">
        <div class="tab-bar" style="margin-bottom:0;">
            @php
                $tabs = ['all'=>'All','pending'=>'Pending','active'=>'Active','inactive'=>'Inactive','rejected'=>'Rejected'];
                $cur  = request('tab','all');
            @endphp
            @foreach($tabs as $key => $label)
                <a href="{{ route('sc.riders',['tab'=>$key]) }}"
                   class="tab {{ $cur===$key ? 'active' : '' }}">
                    {{ $label }}
                    @php
                        $cnt = match($key) {
                            'pending'  => $counts['pending'],
                            'active'   => $counts['active'],
                            'inactive' => $counts['inactive'],
                            'rejected' => $counts['rejected'],
                            default    => $counts['all'],
                        };
                    @endphp
                    @if($cnt > 0 && $key !== 'all')
                        <span class="badge {{ $key==='pending' ? 'badge-yellow' : ($key==='active' ? 'badge-green' : ($key==='rejected' ? 'badge-red' : 'badge-gray')) }}"
                              style="margin-left:4px;font-size:10px;padding:1px 6px;">{{ $cnt }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Rider ID</th><th>Name</th><th>Contact</th><th>Area</th>
                    <th>Applied</th><th>Deliveries</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody id="riders-tbody">
            @forelse($riders as $rider)
                @php
                    $isActive   = $rider->application_status === 'approved' && $rider->is_active;
                    $isPending  = $rider->application_status === 'pending';
                    $isRejected = $rider->application_status === 'rejected';
                    $isInactive = $rider->application_status === 'approved' && !$rider->is_active;
                    $badgeClass = $isActive ? 'badge-green' : ($isPending ? 'badge-yellow' : ($isRejected ? 'badge-red' : 'badge-gray'));
                    $badgeLabel = $isActive ? 'Active' : ($isPending ? 'Pending' : ($isRejected ? 'Rejected' : 'Inactive'));
                    $deliveries = $rider->parcelDeliveries()->where('status','delivered')->count();
                @endphp
                <tr>
                    <td><span class="id-link">RDR-{{ str_pad($rider->id,3,'0',STR_PAD_LEFT) }}</span></td>
                    <td>
                        <div style="font-weight:600;">{{ $rider->full_name }}</div>
                        <div class="text-muted text-sm">{{ $rider->user->email ?? '' }}</div>
                    </td>
                    <td>{{ $rider->phone ?? '—' }}</td>
                    <td><span class="text-orange">{{ $rider->area->name ?? '—' }}</span></td>
                    <td>{{ $rider->created_at->format('Y-m-d') }}</td>
                    <td>{{ $deliveries }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                            @if($isPending)
                                <form method="POST" action="{{ route('sc.riders.approve',$rider) }}">@csrf
                                    <button class="btn btn-green btn-sm">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('sc.riders.reject',$rider) }}">@csrf
                                    <button class="btn btn-red btn-sm">Reject</button>
                                </form>
                            @elseif($isActive)
                                <form method="POST" action="{{ route('sc.riders.toggle',$rider) }}">@csrf
                                    <button class="btn btn-red btn-sm">Deactivate</button>
                                </form>
                            @elseif($isInactive)
                                <form method="POST" action="{{ route('sc.riders.toggle',$rider) }}">@csrf
                                    <button class="btn btn-green btn-sm">Activate</button>
                                </form>
                            @elseif($isRejected)
                                <form method="POST" action="{{ route('sc.riders.approve',$rider) }}">@csrf
                                    <button class="btn btn-yellow btn-sm">Re-review</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">No riders found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $riders->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function filterTable(q, tbodyId) {
    q = q.toLowerCase();
    document.querySelectorAll('#' + tbodyId + ' tr').forEach(function(tr) {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
@endpush
