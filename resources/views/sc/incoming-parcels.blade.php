@extends('sc.layout')
@section('title', 'Incoming Parcels')
@section('icon', '📥')
@section('topbar-right')
    <input type="text" class="search-input" placeholder="Search tracking, sender, recipient..."
           onkeyup="filterTable(this.value,'parcels-tbody')" />
@endsection

@section('content')
<div class="page-header"><h1>Incoming Parcels</h1></div>
<div class="page-body">

    {{-- Mini stats --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;">
        @foreach([['Received',$miniStats['received']],['Logged',$miniStats['logged']],['For Sorting',$miniStats['for_sorting']],['Sorted',$miniStats['sorted']]] as [$lbl,$val])
        <div class="stat-card" style="padding:12px 16px;">
            <div class="stat-label" style="font-size:11px;margin-bottom:3px;">{{ $lbl }}</div>
            <div class="stat-value" style="font-size:24px;">{{ $val }}</div>
        </div>
        @endforeach
    </div>

    @php
        $cur  = request('tab','all');
        $tabs = ['all'=>'All','pickup_approved'=>'Received','picked_up'=>'Logged','picked_up'=>'For Sorting','sorted'=>'Sorted'];
    @endphp
    <div class="flex-between mb-16">
        <div class="tab-bar" style="margin-bottom:0;">
            @foreach(['all'=>'All','pickup_approved'=>'Received','picked_up'=>'For Sorting','sorted'=>'Sorted'] as $key => $label)
                <a href="{{ route('sc.incoming-parcels',['tab'=>$key]) }}"
                   class="tab {{ $cur===$key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking No.</th><th>Sender</th><th>Recipient</th><th>Weight</th>
                    <th>Type</th><th>Origin → Destination</th><th>Received</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody id="parcels-tbody">
            @forelse($parcels as $parcel)
                @php
                    $badgeClass = match($parcel->status) {
                        'sorted'          => 'badge-green',
                        'picked_up'       => 'badge-blue',
                        'pickup_approved' => 'badge-orange',
                        'assigned'        => 'badge-blue',
                        default           => 'badge-gray',
                    };
                    $badgeLabel = match($parcel->status) {
                        'sorted'          => 'Sorted',
                        'picked_up'       => 'For Sorting',
                        'pickup_approved' => 'Received',
                        'assigned'        => 'Assigned',
                        default           => ucwords(str_replace('_',' ',$parcel->status)),
                    };
                @endphp
                <tr>
                    <td><span class="id-link">{{ $parcel->tracking_number }}</span></td>
                    <td>{{ $parcel->seller->name ?? '—' }}</td>
                    <td>{{ $parcel->receiver_name }}</td>
                    <td>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                    <td>{{ $parcel->size ?? 'Parcel' }}</td>
                    <td>
                        <span class="text-blue">{{ $parcel->pickup_address }}</span>
                        <span style="color:var(--text-muted);"> → </span>
                        <span class="text-blue">{{ $parcel->dropoff_address }}</span>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $parcel->created_at->format('Y-m-d  H:i') }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                    <td>
                        @if($parcel->status === 'pickup_approved')
                            <form method="POST" action="{{ route('sc.parcels.advance',$parcel) }}">@csrf
                                <button class="btn btn-blue btn-sm">Advance →</button>
                            </form>
                        @elseif($parcel->status === 'picked_up')
                            <a href="{{ route('sc.parcel-sorting') }}" class="btn btn-blue btn-sm">Sort →</a>
                        @else
                            <span class="text-muted text-sm">Done</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted);">No parcels found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $parcels->links() }}</div>
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
