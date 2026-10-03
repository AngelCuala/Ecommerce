@extends('sc.layout')
@section('title', 'Account Management')
@section('icon', 'user')

@section('content')
<div class="page-header"><h1>Account Management</h1></div>
<div class="page-body">

    <div class="acct-layout">

        {{-- Sorting center details --}}
        <div>
            <div class="card" style="padding:18px 20px;margin-bottom:16px;">
                <h2 style="font-size:15px;font-weight:700;margin-bottom:14px;">Sorting Center Details</h2>
                <dl style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px 24px;">
                    <div>
                        <dt class="form-label">Center Name</dt>
                        <dd style="font-weight:600;">{{ $sc->name }}</dd>
                    </div>
                    <div>
                        <dt class="form-label">Status</dt>
                        <dd><span class="badge badge-green">Active</span></dd>
                    </div>
                    <div>
                        <dt class="form-label">Assigned Municipality</dt>
                        <dd style="font-weight:600;">{{ $sc->assigned_municipality ?? 'Not assigned' }}</dd>
                    </div>
                    <div>
                        <dt class="form-label">Province</dt>
                        <dd style="font-weight:600;">{{ $sc->assigned_province ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="form-label">Member Since</dt>
                        <dd>{{ $sc->created_at->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="form-label">Last Login</dt>
                        <dd>{{ $sc->last_login_at?->format('M d, Y h:i A') ?? '—' }}</dd>
                    </div>
                </dl>
                <p class="text-muted" style="font-size:12px;margin-top:14px;">
                    The assigned municipality is set by the ALVY administrator. Contact them if it needs to change.
                </p>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
                @foreach([['Coverage Barangays',$summary['areas'],route('sc.areas')],['Riders',$summary['riders'],route('sc.riders')],['Parcels Handled',$summary['handled'],route('sc.incoming-parcels')],['Delivered',$summary['delivered'],route('sc.delivery-monitoring',['status'=>'delivered'])]] as [$lbl,$val,$href])
                    <a href="{{ $href }}" class="stat-card" style="padding:12px 16px;text-decoration:none;">
                        <div class="stat-label" style="font-size:11px;margin-bottom:3px;">{{ $lbl }}</div>
                        <div class="stat-value" style="font-size:22px;">{{ $val }}</div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- My Profile --}}
        <div class="profile-card">
            <div class="profile-avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</div>
            <div class="profile-name">{{ auth()->user()->name }}</div>
            <div class="profile-email">{{ auth()->user()->email }}</div>
            <div style="margin-top:6px;"><span class="badge badge-red">{{ ucfirst(auth()->user()->role) }}</span></div>

            <hr style="border:none;border-top:1px solid var(--border);margin:18px 0;" />

            <form method="POST" action="{{ route('sc.account.update') }}">
                @csrf @method('PUT')

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', auth()->user()->name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" value="{{ old('email', auth()->user()->email) }}" required>
                </div>

                <div style="font-size:12px;font-weight:600;color:var(--text-sub);margin-bottom:10px;margin-top:6px;text-transform:uppercase;letter-spacing:.3px;">
                    Change Password
                </div>

                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <div style="position:relative;">
                        <input type="password" name="current_password" data-password class="form-input" placeholder="••••••••" style="padding-right:2.5rem;">
                        <button type="button" data-toggle-password aria-label="Show password"
                                style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;line-height:1;">@include('sc.partials.icon', ['name' => 'eye', 'size' => 18])</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div style="position:relative;">
                        <input type="password" name="password" data-password class="form-input" placeholder="••••••••" style="padding-right:2.5rem;">
                        <button type="button" data-toggle-password aria-label="Show password"
                                style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;line-height:1;">@include('sc.partials.icon', ['name' => 'eye', 'size' => 18])</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <div style="position:relative;">
                        <input type="password" name="password_confirmation" data-password class="form-input" placeholder="••••••••" style="padding-right:2.5rem;">
                        <button type="button" data-toggle-password aria-label="Show password"
                                style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;line-height:1;">@include('sc.partials.icon', ['name' => 'eye', 'size' => 18])</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-blue" style="width:100%;justify-content:center;padding:9px;">
                    Save Changes
                </button>
            </form>
        </div>

    </div>
</div>

@include('partials.password-toggle')
@endsection
