<x-layout title="My Addresses — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'Addresses', 'subtitle' => 'Manage your saved delivery addresses.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">

            {{-- Flash / errors --}}
            @if (session('success'))
                <div class="pf-alert pf-alert-success">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="pf-alert pf-alert-error">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- ═══════════ SAVED ADDRESSES ═══════════ --}}
            <div class="pf-card p-6 sm:p-7">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="pf-icon">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <div>
                            <h3 class="pf-card-title">My Addresses</h3>
                            <p class="text-xs pf-muted">Manage your saved delivery addresses.</p>
                        </div>
                    </div>
                    <button type="button" onclick="document.getElementById('add-address-modal').classList.remove('hidden')" class="pf-btn">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                        Add Address
                    </button>
                </div>

                {{-- Address list --}}
                <div class="grid gap-4 md:grid-cols-2">
                    @forelse ($addresses as $address)
                        <div class="relative flex flex-col rounded-2xl p-5 transition"
                             style="background:{{ $address->is_default ? '#fff5f3' : '#fff' }};border:{{ $address->is_default ? '2px solid #fa4e1c' : '1px solid #F0E4DC' }};">

                            <div class="flex items-start gap-3">
                                <span class="pf-icon text-lg">{{ $address->label === 'Home' ? '🏠' : ($address->label === 'Work' ? '🏢' : '📍') }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-display text-sm font-extrabold" style="color:#222;">{{ $address->label }}</p>
                                        {{-- Default badge --}}
                                        @if ($address->is_default)
                                            <span class="pf-badge">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                                                Default
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 truncate text-sm font-semibold pf-text">{{ $address->full_name }}</p>
                                    @if ($address->phone)
                                        <p class="mt-0.5 flex items-center gap-1.5 text-xs pf-muted">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/></svg>
                                            {{ $address->phone }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-4 flex-1 rounded-xl p-3.5 text-sm pf-text" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                                {{ $address->full_address }}
                            </div>

                            {{-- Actions --}}
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <button onclick="openEditModal({{ $address->id }}, {{ json_encode($address) }})"
                                        class="pf-btn-ghost px-3.5 py-1.5 text-xs">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Edit
                                </button>

                                @if (! $address->is_default)
                                    <form action="{{ route('addresses.set-default', $address) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="pf-btn-ghost px-3.5 py-1.5 text-xs">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                            Set as Default
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('addresses.destroy', $address) }}" method="POST" class="ml-auto"
                                      onsubmit="return confirm('Remove this address?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="pf-btn-danger px-3.5 py-1.5 text-xs">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6h14z"/></svg>
                                        Remove
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center gap-3 rounded-2xl px-6 py-14 text-center md:col-span-2"
                             style="background:#FDF8F5;border:1px dashed #E6D9CF;">
                            <span class="pf-icon h-16 w-16 rounded-2xl">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            </span>
                            <p class="font-display text-base font-extrabold" style="color:#222;">No addresses saved yet</p>
                            <p class="text-sm pf-muted">Add an address to speed up checkout.</p>
                            <button onclick="document.getElementById('add-address-modal').classList.remove('hidden')" class="pf-btn mt-2">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                                Add Your First Address
                            </button>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</div>
</div>

{{-- ═══════════════════════════════ ADD ADDRESS MODAL ═══════════════════════════════ --}}
<div id="add-address-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(34,24,18,.55);backdrop-filter:blur(2px);">
    <div class="pf-card w-full max-w-lg overflow-hidden" style="max-height:92vh;overflow-y:auto;">
        <div class="flex items-center justify-between gap-3 px-6 py-4" style="background:linear-gradient(120deg,#fff5f3 0%,#fff 70%);border-bottom:1px solid #F0E4DC;">
            <div class="flex items-center gap-3">
                <span class="pf-icon">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <div>
                    <h3 class="pf-card-title">Add New Address</h3>
                    <p class="text-xs pf-muted">Where should we deliver your orders?</p>
                </div>
            </div>
            <button onclick="document.getElementById('add-address-modal').classList.add('hidden')"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-2xl leading-none transition hover:bg-[#fff1ee] hover:text-[#fa4e1c]" style="color:#8a7a70;">&times;</button>
        </div>
        <form action="{{ route('addresses.store') }}" method="POST" class="p-6 grid gap-4 sm:grid-cols-2">
            @csrf

            <div>
                <label class="pf-label">Label</label>
                <select name="label" class="pf-input">
                    <option value="Home">🏠 Home</option>
                    <option value="Work">🏢 Work</option>
                    <option value="Other">📍 Other</option>
                </select>
            </div>

            <div>
                <label class="pf-label">Full Name</label>
                <input type="text" name="full_name" value="{{ old('full_name', auth()->user()->name) }}"
                       class="pf-input" required placeholder="Recipient name">
            </div>

            <div>
                <label class="pf-label">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                       class="pf-input" placeholder="+63 912 345 6789">
            </div>

            <div>
                <label class="pf-label">ZIP Code</label>
                <input type="text" name="zip" class="pf-input" placeholder="1234">
            </div>

            <div class="sm:col-span-2">
                <label class="pf-label">Street / House No.</label>
                <input type="text" name="address_line" class="pf-input" required placeholder="e.g. 123 Rizal St., Unit 4B">
            </div>

            <div>
                <label class="pf-label">Barangay</label>
                <input type="text" name="barangay" class="pf-input" placeholder="Barangay">
            </div>

            <div>
                <label class="pf-label">City / Municipality</label>
                <input type="text" name="city" class="pf-input" required placeholder="Quezon City">
            </div>

            <div>
                <label class="pf-label">Province</label>
                <input type="text" name="province" class="pf-input" placeholder="Metro Manila">
            </div>

            <div>
                <label class="pf-label">Country</label>
                <input type="text" name="country" value="Philippines" class="pf-input">
            </div>

            <div class="sm:col-span-2 flex items-center gap-3 rounded-xl p-3.5" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                <input type="checkbox" name="is_default" value="1" id="add_is_default" class="h-4 w-4 rounded accent-orange-500">
                <label for="add_is_default" class="text-sm font-medium pf-text">Set as default delivery address</label>
            </div>

            <div class="sm:col-span-2 flex justify-end gap-3 border-t pt-5" style="border-color:#F0E4DC;">
                <button type="button"
                        onclick="document.getElementById('add-address-modal').classList.add('hidden')"
                        class="pf-btn-ghost">
                    Cancel
                </button>
                <button type="submit" class="pf-btn px-6">
                    Save Address
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════ EDIT ADDRESS MODAL ═══════════════════════════════ --}}
<div id="edit-address-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(34,24,18,.55);backdrop-filter:blur(2px);">
    <div class="pf-card w-full max-w-lg overflow-hidden" style="max-height:92vh;overflow-y:auto;">
        <div class="flex items-center justify-between gap-3 px-6 py-4" style="background:linear-gradient(120deg,#fff5f3 0%,#fff 70%);border-bottom:1px solid #F0E4DC;">
            <div class="flex items-center gap-3">
                <span class="pf-icon">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </span>
                <div>
                    <h3 class="pf-card-title">Edit Address</h3>
                    <p class="text-xs pf-muted">Update this delivery address.</p>
                </div>
            </div>
            <button onclick="document.getElementById('edit-address-modal').classList.add('hidden')"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-2xl leading-none transition hover:bg-[#fff1ee] hover:text-[#fa4e1c]" style="color:#8a7a70;">&times;</button>
        </div>
        <form id="edit-address-form" method="POST" class="p-6 grid gap-4 sm:grid-cols-2"
              data-addresses-base="{{ url('/profile/addresses') }}">
            @csrf @method('PATCH')

            <div>
                <label class="pf-label">Label</label>
                <select name="label" id="edit_label" class="pf-input">
                    <option value="Home">🏠 Home</option>
                    <option value="Work">🏢 Work</option>
                    <option value="Other">📍 Other</option>
                </select>
            </div>

            <div>
                <label class="pf-label">Full Name</label>
                <input type="text" name="full_name" id="edit_full_name" class="pf-input" required>
            </div>

            <div>
                <label class="pf-label">Phone</label>
                <input type="text" name="phone" id="edit_phone" class="pf-input">
            </div>

            <div>
                <label class="pf-label">ZIP Code</label>
                <input type="text" name="zip" id="edit_zip" class="pf-input">
            </div>

            <div class="sm:col-span-2">
                <label class="pf-label">Street / House No.</label>
                <input type="text" name="address_line" id="edit_address_line" class="pf-input" required>
            </div>

            <div>
                <label class="pf-label">Barangay</label>
                <input type="text" name="barangay" id="edit_barangay" class="pf-input">
            </div>

            <div>
                <label class="pf-label">City / Municipality</label>
                <input type="text" name="city" id="edit_city" class="pf-input" required>
            </div>

            <div>
                <label class="pf-label">Province</label>
                <input type="text" name="province" id="edit_province" class="pf-input">
            </div>

            <div>
                <label class="pf-label">Country</label>
                <input type="text" name="country" id="edit_country" class="pf-input">
            </div>

            <div class="sm:col-span-2 flex items-center gap-3 rounded-xl p-3.5" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                <input type="checkbox" name="is_default" value="1" id="edit_is_default" class="h-4 w-4 rounded accent-orange-500">
                <label for="edit_is_default" class="text-sm font-medium pf-text">Set as default delivery address</label>
            </div>

            <div class="sm:col-span-2 flex justify-end gap-3 border-t pt-5" style="border-color:#F0E4DC;">
                <button type="button"
                        onclick="document.getElementById('edit-address-modal').classList.add('hidden')"
                        class="pf-btn-ghost">
                    Cancel
                </button>
                <button type="submit" class="pf-btn px-6">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Address modal behaviour --}}
<script src="{{ asset('js/profile-addresses.js') }}"></script>
@if ($errors->any() && old('address_line'))
    <script>
        // Auto-open add modal if validation errors exist (user was adding)
        document.getElementById('add-address-modal').classList.remove('hidden');
    </script>
@endif
</x-layout>
