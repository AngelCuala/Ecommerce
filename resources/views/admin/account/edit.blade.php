@extends('admin.layouts.app')

@section('title', 'Account Management')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
@endsection

@section('content')

<div class="account-grid">

    {{-- System Accounts table --}}
    <div>
        <div class="panel">
            <div class="panel__header">
                <h2>System Accounts</h2>
                <button class="btn btn--primary btn--sm">+ Add Account</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Name / Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Last Login</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach(\App\Models\User::where('role','admin')->orWhere('role','courier')->latest()->get() as $u)
                    @php
                        $roleColor = match($u->role) {
                            'admin'   => 'var(--red)',
                            'courier' => 'var(--blue)',
                            default   => 'var(--amber)',
                        };
                        $isActive = !in_array($u->role,['suspended','deactivated']);
                    @endphp
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--text-primary);">{{ $u->name }}</div>
                            <div class="td-sub" style="color:var(--blue);">{{ $u->email }}</div>
                        </td>
                        <td>
                            <span class="badge" style="background:{{ $roleColor }}22;color:{{ $roleColor }};border-color:{{ $roleColor }}44;">
                                {{ ucfirst($u->role) }}
                            </span>
                        </td>
                        <td>
                            @if($isActive)
                                <span class="badge badge--active"><span class="badge__dot"></span>Active</span>
                            @else
                                <span class="badge badge--inactive"><span class="badge__dot"></span>Inactive</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $u->created_at->format('Y-m-d') }}</td>
                        <td class="text-muted">{{ $u->updated_at->format('Y-m-d H:i') }}</td>
                        <td>
                            @if($u->id !== auth()->id())
                                @if($isActive)
                                    <form method="POST" action="{{ route('admin.users.suspend',$u->id) }}" style="display:inline">@csrf @method('PATCH')
                                        <button class="btn btn--danger btn--sm">Deactivate</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.activate',$u->id) }}" style="display:inline">@csrf @method('PATCH')
                                        <button class="btn btn--primary btn--sm">Activate</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- My profile panel --}}
    <div>
        <div class="panel">
            <div class="panel__header"><h2>My Profile</h2></div>
            <div style="padding:20px;">

                {{-- Avatar --}}
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:var(--blue);color:#0d1117;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;">
                        {{ strtoupper(substr(auth()->user()->name,0,1)) }}
                    </div>
                    <div>
                        <div style="font-weight:600;color:var(--text-primary);">{{ auth()->user()->name }}</div>
                        <div style="font-size:12px;color:var(--blue);">{{ auth()->user()->email }}</div>
                        <span class="badge" style="margin-top:4px;background:rgba(248,81,73,.15);color:var(--red);border-color:rgba(248,81,73,.3);">{{ ucfirst(auth()->user()->role) }}</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.account.update') }}" class="space-y-field">
                    @csrf @method('PUT')
                    <div class="field" style="margin-bottom:14px;">
                        <label>Full Name</label>
                        <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" required>
                    </div>
                    <div class="field" style="margin-bottom:14px;">
                        <label>Email Address</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required>
                    </div>

                    <div class="divider"></div>
                    <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:12px;text-transform:uppercase;letter-spacing:.3px;">Change Password</div>

                    <div class="field" style="margin-bottom:14px;">
                        <label>Current Password</label>
                        <input type="password" name="current_password" placeholder="••••••••">
                    </div>
                    <div class="field" style="margin-bottom:14px;">
                        <label>New Password</label>
                        <input type="password" name="password" placeholder="••••••••">
                    </div>
                    <div class="field" style="margin-bottom:20px;">
                        <label>Confirm New Password</label>
                        <input type="password" name="password_confirmation" placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn--solid-blue btn--lg" style="width:100%;">Save Changes</button>
                </form>
            </div>
        </div>
    </div>

</div>

@endsection
