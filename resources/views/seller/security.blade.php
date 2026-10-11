<x-seller-layout title="Change password" active="account">
@include('partials.panel.plain')

<div class="mx-auto max-w-xl">
    <div class="mb-5">
        <a href="{{ route('seller.account') }}" class="text-sm hover:underline pl-muted">&larr; Account</a>
        <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222;">Change password</h1>
        <p class="mt-0.5 text-sm pl-muted">Use at least 8 characters, and don't reuse a password from another site.</p>
    </div>

    <form action="{{ route('seller.account.password') }}" method="POST" class="pl-card pl-body space-y-4">
        @csrf @method('PUT')

        <div>
            <label for="current_password" class="pl-label">Current password</label>
            <div class="relative">
                <input type="password" id="current_password" name="current_password" data-password class="input pr-11" required autocomplete="current-password">
                <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7f78;"></button>
            </div>
            @error('current_password')<p class="pl-note !text-[#B91C1C]">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="pl-label">New password</label>
            <div class="relative">
                <input type="password" id="password" name="password" data-password class="input pr-11" minlength="8" required autocomplete="new-password">
                <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7f78;"></button>
            </div>
            @error('password')<p class="pl-note !text-[#B91C1C]">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="pl-label">Confirm new password</label>
            <div class="relative">
                <input type="password" id="password_confirmation" name="password_confirmation" data-password class="input pr-11" minlength="8" required autocomplete="new-password">
                <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7f78;"></button>
            </div>
        </div>

        <div class="flex justify-end border-t pt-4" style="border-color:#F2EEEA;">
            <button type="submit" class="pl-btn">Update password</button>
        </div>
    </form>
</div>

@include('partials.password-toggle')
</x-seller-layout>
