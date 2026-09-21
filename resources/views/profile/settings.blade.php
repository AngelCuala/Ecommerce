<x-layout title="Settings — ALVY">
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

            {{-- Settings --}}
            <div class="rounded-2xl p-6 sm:p-8" style="background:#fff;border:1px solid #cfdce8;">
                <h2 class="font-display text-xl font-bold mb-1" style="color:#002b4d;">Settings</h2>
                <p class="text-sm mb-6" style="color:#6b90aa;">Manage your preferences and learn more about ALVY.</p>

                <div class="divide-y" style="--tw-divide-color:#eef3f7;">

                    {{-- About Us --}}
                    <a href="{{ route('policies.show', 'about_us') }}"
                       class="flex items-center gap-4 py-4 transition"
                       onmouseover="this.style.background='#FFF8F3';" onmouseout="this.style.background='';">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background:#FFF1E6;">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#fa4e1c;">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm" style="color:#002b4d;">About Us</p>
                            <p class="text-xs" style="color:#6b90aa;">Learn about ALVY, our mission, and our marketplace.</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#9db3c4;"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    {{-- Accounts & Security --}}
                    <a href="{{ route('profile.security') }}"
                       class="flex items-center gap-4 py-4 transition"
                       onmouseover="this.style.background='#FFF8F3';" onmouseout="this.style.background='';">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background:#FFF1E6;">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#fa4e1c;">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm" style="color:#002b4d;">Accounts &amp; Security</p>
                            <p class="text-xs" style="color:#6b90aa;">Update your username, email, phone, and password.</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#9db3c4;"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    {{-- Policies --}}
                    <a href="{{ route('policies.show', 'privacy_policy') }}"
                       class="flex items-center gap-4 py-4 transition"
                       onmouseover="this.style.background='#FFF8F3';" onmouseout="this.style.background='';">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" style="background:#FFF1E6;">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#fa4e1c;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>
                            </svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm" style="color:#002b4d;">Privacy &amp; Policies</p>
                            <p class="text-xs" style="color:#6b90aa;">Read our privacy policy, terms, and other policies.</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#9db3c4;"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                </div>
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
