<x-admin-layout title="Seller Applications" active="seller-applications">
@include('partials.panel.plain')
<style>
    .sa-tab { position: relative; padding: .6rem .25rem; margin-right: 1.25rem; font-size: .875rem; font-weight: 600; color: #8a7f78; transition: color .15s; white-space: nowrap; }
    .sa-tab:hover { color: #222; }
    .sa-tab.is-active { color: #222; }
    .sa-tab.is-active::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: #fa4e1c; border-radius: 2px; }
    .sa-tab .tab-count { margin-left: .35rem; font-size: .75rem; color: #b3a79f; }
    .sa-tab.is-active .tab-count { color: #fa4e1c; }
</style>

@if (session('success'))
    <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#ECFDF5;border-color:#A7F3D0;color:#047857;">
        {{ session('success') }}
    </div>
@endif

{{-- Filter tabs --}}
<nav class="mb-5 flex flex-wrap border-b" style="border-color:#ECE7E3;">
    @foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $val=>$label)
        @php $active = request('status', 'all') === $val; @endphp
        <a href="{{ route('admin.seller-applications.index', ['status'=>$val]) }}"
           class="sa-tab {{ $active ? 'is-active' : '' }}">
            {{ $label }}<span class="tab-count">{{ $applications->where($val === 'all' ? fn($a)=>true : 'status', $val === 'all' ? true : $val)->count() }}</span>
        </a>
    @endforeach
</nav>

<div class="pl-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="pl-table">
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Shop name</th>
                    <th>Applied</th>
                    <th>Status</th>
                    <th class="!text-right">Action</th>
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
                        $tag = match($app->status) {
                            'approved' => 'delivered',
                            'rejected' => 'cancelled',
                            'pending'  => 'pending',
                            default    => 'neutral',
                        };
                    @endphp
                    <tr>
                        <td>
                            <p class="font-medium" style="color:#222;">{{ $app->full_name }}</p>
                            <p class="text-xs pl-muted">{{ $app->user->email }}</p>
                        </td>
                        <td>{{ $app->shop_name }}</td>
                        <td class="whitespace-nowrap pl-muted">{{ $app->created_at->format('M d, Y') }}</td>
                        <td><span class="pl-tag {{ $tag }}">{{ ucfirst($app->status) }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('admin.seller-applications.show', $app->id) }}" class="pl-link">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="!py-10 text-center text-sm pl-muted">No applications found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</x-admin-layout>
