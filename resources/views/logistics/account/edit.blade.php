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
            <div class="field"><label>Current Password</label><input type="password" name="current_password" required></div>
            <div class="field"><label>New Password</label><input type="password" name="password" required></div>
            <div class="field"><label>Confirm New Password</label><input type="password" name="password_confirmation" required></div>
            <button class="btn btn--primary" style="width:fit-content;">Update Password</button>
        </form>
    </div>

</div>

</x-logistics-layout>
