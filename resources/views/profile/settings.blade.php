<x-layout title="Settings — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'Settings', 'subtitle' => 'Manage your preferences and learn more about ALVY.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">

            @if (session('success'))
                <div class="pf-alert pf-alert-success">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            {{-- ═══════════ GENERAL SETTINGS ═══════════ --}}
            <div class="pf-card p-6 sm:p-7">
                <div class="mb-5 flex items-center gap-3">
                    <span class="pf-icon">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <div>
                        <h3 class="pf-card-title">Settings</h3>
                        <p class="text-xs pf-muted">Manage your preferences and learn more about ALVY.</p>
                    </div>
                </div>

                <div class="space-y-2.5">

                    {{-- About Us --}}
                    <a href="{{ route('policies.show', 'about_us') }}"
                       class="group flex items-center gap-4 rounded-xl p-3.5 transition hover:border-[#fa4e1c]/40 hover:bg-[#fff5f3]"
                       style="border:1px solid #F5EBE4;">
                        <span class="pf-icon transition group-hover:scale-105">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold" style="color:#222;">About Us</p>
                            <p class="text-xs pf-muted">Learn about ALVY, our mission, and our marketplace.</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-[#b5a79d] transition group-hover:translate-x-0.5 group-hover:text-[#fa4e1c]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    {{-- Accounts & Security --}}
                    <a href="{{ route('profile.security') }}"
                       class="group flex items-center gap-4 rounded-xl p-3.5 transition hover:border-[#fa4e1c]/40 hover:bg-[#fff5f3]"
                       style="border:1px solid #F5EBE4;">
                        <span class="pf-icon transition group-hover:scale-105">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold" style="color:#222;">Accounts &amp; Security</p>
                            <p class="text-xs pf-muted">Update your username, email, phone, and password.</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-[#b5a79d] transition group-hover:translate-x-0.5 group-hover:text-[#fa4e1c]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                    {{-- Policies --}}
                    <a href="{{ route('policies.show', 'privacy_policy') }}"
                       class="group flex items-center gap-4 rounded-xl p-3.5 transition hover:border-[#fa4e1c]/40 hover:bg-[#fff5f3]"
                       style="border:1px solid #F5EBE4;">
                        <span class="pf-icon transition group-hover:scale-105">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold" style="color:#222;">Privacy &amp; Policies</p>
                            <p class="text-xs pf-muted">Read our privacy policy, terms, and other policies.</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-[#b5a79d] transition group-hover:translate-x-0.5 group-hover:text-[#fa4e1c]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>

                </div>
            </div>

            {{-- ═══════════ DANGER ZONE ═══════════ --}}
            <div class="pf-card overflow-hidden" style="border-color:rgba(220,38,38,.22);">
                <div class="flex items-center gap-3 border-b p-6 sm:px-7" style="background:#FEF6F6;border-color:rgba(220,38,38,.15);">
                    <span class="pf-icon" style="background:#FEE2E2;color:#DC2626;">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </span>
                    <div>
                        <h3 class="pf-card-title" style="color:#DC2626;">Danger Zone</h3>
                        <p class="text-xs" style="color:#b45454;">These actions are permanent and cannot be undone.</p>
                    </div>
                </div>

                <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <div class="min-w-0">
                        <p class="text-sm font-bold" style="color:#222;">Delete Account</p>
                        <p class="mt-0.5 text-xs pf-muted">Permanently delete your account and all data.</p>
                    </div>
                    {{-- Keep text-only: Buyer.js matches e.target against [data-delete-account] --}}
                    <button type="button"
                            data-delete-account
                            class="pf-btn-danger shrink-0 btn-hover-danger">Delete Account</button>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
</x-layout>
