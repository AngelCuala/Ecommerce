@extends('sc.layout')
@section('title', 'Account Management')
@section('icon', '👤')

@section('content')
<div class="page-header"><h1>Account Management</h1></div>
<div class="page-body">

    <div class="acct-layout">

        {{-- System Accounts table --}}
        <div>
            <div class="flex-between mb-16">
                <h2 style="font-size:15px;font-weight:600;">System Accounts</h2>
                <button class="btn btn-blue" onclick="alert('Add account form coming soon.')">+ Add Account</button>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name / Email</th><th>Role</th><th>Status</th>
                            <th>Created</th><th>Last Login</th><th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($accounts as $u)
                        @php
                            $roleColor = match($u->role) {
                                'admin'   => 'badge-red',
                                'courier' => 'badge-blue',
                                default   => 'badge-purple',
                            };
                            $roleLabel = ucfirst($u->role);
                            $active = !in_array($u->role,['suspended','deactivated']);
                        @endphp
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $u->name }}</div>
                                <div class="text-muted text-sm" style="color:var(--accent-blue)!important;">{{ $u->email }}</div>
                            </td>
                            <td><span class="badge {{ $roleColor }}">{{ $roleLabel }}</span></td>
                            <td>
                                @if($active)
                                    <span class="badge badge-green">Active</span>
                                @else
                                    <span class="badge badge-gray">Inactive</span>
                                @endif
                            </td>
                            <td style="color:var(--text-muted);font-size:12px;">{{ $u->created_at->format('Y-m-d') }}</td>
                            <td style="color:var(--text-muted);font-size:12px;">{{ $u->updated_at->format('Y-m-d  H:i') }}</td>
                            <td>
                                @if($u->id !== auth()->id())
                                    @if($active)
                                        <form method="POST" action="{{ route('admin.users.suspend',$u->id) }}" style="display:inline">@csrf @method('PATCH')
                                            <button class="btn btn-red btn-sm">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.activate',$u->id) }}" style="display:inline">@csrf @method('PATCH')
                                            <button class="btn btn-green btn-sm">Activate</button>
                                        </form>
                                    @endif
                                @else
                                    <span class="text-muted text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
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
                    <input type="password" name="current_password" class="form-input" placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-input" placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-blue" style="width:100%;justify-content:center;padding:9px;">
                    Save Changes
                </button>
            </form>
        </div>

    </div>
</div>
@endsection
