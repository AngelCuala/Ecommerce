<x-layout title="Accounts & Security — ALVY">
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1 space-y-6">

            @if (session('success'))
                <div class="rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#fa4e1c;">
                    ✓ {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Account Details --}}
            <div class="rounded-2xl p-6 sm:p-8" style="background:#fff;border:1px solid #cfdce8;">
                <h2 class="font-display text-xl font-bold mb-1" style="color:#002b4d;">Account Details</h2>
                <p class="text-sm mb-6" style="color:#6b90aa;">Manage your username, phone number, and email address.</p>

                <form action="{{ route('profile.account.update') }}" method="POST" class="grid gap-5 sm:grid-cols-2">
                    @csrf @method('PATCH')

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Username</label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               placeholder="your_username">
                        <p class="mt-1 text-[11px]" style="color:#6b90aa;">Letters, numbers, dashes and underscores only.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               placeholder="+63 912 345 6789">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                    </div>

                    <div class="sm:col-span-2 pt-1">
                        <button type="submit"
                                class="rounded-full px-8 py-2.5 text-sm font-semibold transition"
                                style="background:#fa4e1c;color:#fff;"
                                onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                            Save Account Details
                        </button>
                    </div>
                </form>
            </div>

            {{-- Change Password --}}
            <div class="rounded-2xl p-6 sm:p-8" style="background:#fff;border:1px solid #cfdce8;">
                <h2 class="font-display text-xl font-bold mb-1" style="color:#002b4d;">Change Password</h2>
                <p class="text-sm mb-6" style="color:#6b90aa;">Keep your account secure with a strong password.</p>

                <form action="{{ route('profile.change-password') }}" method="POST" class="grid gap-5 sm:grid-cols-2">
                    @csrf @method('PATCH')

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Current Password</label>
                        <div class="relative">
                            <input type="password" name="current_password" data-password
                                   class="w-full rounded-xl border px-4 py-2.5 pr-11 text-sm outline-none"
                                   style="border-color:#cfdce8;color:#002b4d;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">New Password</label>
                        <div class="relative">
                            <input type="password" name="password" data-password minlength="8"
                                   class="w-full rounded-xl border px-4 py-2.5 pr-11 text-sm outline-none"
                                   style="border-color:#cfdce8;color:#002b4d;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" name="password_confirmation" data-password
                                   class="w-full rounded-xl border px-4 py-2.5 pr-11 text-sm outline-none"
                                   style="border-color:#cfdce8;color:#002b4d;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                        </div>
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

        </div>
    </div>
</div>

@include('partials.password-toggle')
</x-layout>
