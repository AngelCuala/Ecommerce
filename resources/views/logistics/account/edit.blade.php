<x-logistics-layout title="Account Management">

<div style="display:grid;gap:22px;max-width:520px;">

    {{-- Profile info --}}
    <div class="panel">
        <div class="panel__header"><h2>Profile Details</h2></div>
        <form method="POST" action="{{ route('logistics.account.update') }}" style="padding:18px;display:grid;gap:14px;">
            @csrf @method('PUT')
            <div class="field"><label>Name</label><input type="text" name="name" value="{{ old('name', $user->name) }}" required></div>
            <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
            <button class="btn btn--primary" style="width:fit-content;">Save Changes</button>
        </form>
    </div>

    {{-- Password --}}
    <div class="panel">
        <div class="panel__header"><h2>Change Password</h2></div>
        <form method="POST" action="{{ route('logistics.account.password') }}" style="padding:18px;display:grid;gap:14px;">
            @csrf @method('PUT')
            <div class="field"><label>Current Password</label><div style="position:relative;"><input type="password" name="current_password" data-password required style="padding-right:2.5rem;"><button type="button" data-toggle-password aria-label="Show password" style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;line-height:1;">👁️</button></div></div>
            <div class="field"><label>New Password</label><div style="position:relative;"><input type="password" name="password" data-password required style="padding-right:2.5rem;"><button type="button" data-toggle-password aria-label="Show password" style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;line-height:1;">👁️</button></div></div>
            <div class="field"><label>Confirm New Password</label><div style="position:relative;"><input type="password" name="password_confirmation" data-password required style="padding-right:2.5rem;"><button type="button" data-toggle-password aria-label="Show password" style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;line-height:1;">👁️</button></div></div>
            <button class="btn btn--primary" style="width:fit-content;">Update Password</button>
        </form>
    </div>

</div>

@include('partials.password-toggle')
</x-logistics-layout>
