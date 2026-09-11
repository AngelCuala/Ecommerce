<x-layout title="Settings — ALVY">
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1 space-y-6">

            {{-- Change Password --}}
            <div class="rounded-2xl p-6 sm:p-8" style="background:#fff;border:1px solid #cfdce8;">
                <h2 class="font-display text-xl font-bold mb-1" style="color:#002b4d;">Change Password</h2>
                <p class="text-sm mb-6" style="color:#6b90aa;">Keep your account secure with a strong password.</p>

                @if (session('success'))
                    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#fa4e1c;">
                        ✓ {{ session('success') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form action="{{ route('profile.change-password') }}" method="POST" class="grid gap-5 sm:grid-cols-2">
                    @csrf @method('PATCH')

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Current Password</label>
                        <input type="password" name="current_password"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">New Password</label>
                        <input type="password" name="password"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               required minlength="8">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Confirm New Password</label>
                        <input type="password" name="password_confirmation"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               required>
                    </div>

                    <div class="sm:col-span-2 pt-1">
                        <button type="submit"
                                class="rounded-full px-8 py-2.5 text-sm font-semibold transition"
                                style="background:#fa4e1c;color:#fff;"
                                onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>

            {{-- Danger Zone --}}
            <div class="rounded-2xl p-6 sm:p-8" style="background:#FEF2F2;border:1px solid rgba(220,38,38,.2);">
                <h2 class="font-display text-xl font-bold mb-1" style="color:#DC2626;">Danger Zone</h2>
                <p class="text-sm mb-6" style="color:#F87171;">These actions are permanent and cannot be undone.</p>

                <div class="flex items-center justify-between gap-4 rounded-xl border p-4"
                     style="background:#fff;border-color:rgba(220,38,38,.2);">
                    <div>
                        <p class="font-semibold text-sm" style="color:#DC2626;">Delete Account</p>
                        <p class="text-xs mt-0.5" style="color:#F87171;">Permanently delete your account and all data.</p>
                    </div>
                    <button type="button"
                            onclick="alert('Please contact support to delete your account.')"
                            class="shrink-0 rounded-full border-2 px-4 py-2 text-xs font-semibold transition hover:bg-red-50"
                            style="border-color:#F87171;color:#DC2626;">
                        Delete Account
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
</x-layout>
