<x-layout title="My Addresses — ALVY">
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-display text-xl font-bold" style="color:#002b4d;">My Addresses</h2>
                    <p class="text-sm" style="color:#6b90aa;">Manage your saved delivery addresses.</p>
                </div>
                <button onclick="document.getElementById('add-address-modal').classList.remove('hidden')"
                        class="rounded-full px-5 py-2.5 text-sm font-semibold transition"
                        style="background:#fa4e1c;color:#fff;"
                        onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                    + Add Address
                </button>
            </div>

            @if (session('success'))
                <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#fa4e1c;">
                    ✓ {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Address list --}}
            @forelse ($addresses as $address)
                <div class="relative rounded-2xl mb-4 p-5 transition"
                     style="background:#fff;border:2px solid {{ $address->is_default ? '#fa4e1c' : '#cfdce8' }};">

                    {{-- Default badge --}}
                    @if ($address->is_default)
                        <span class="absolute top-4 right-4 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide"
                              style="background:rgba(250,78,28,.12);color:#fa4e1c;">
                            ✓ Default
                        </span>
                    @endif

                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 text-xl">{{ $address->label === 'Home' ? '🏠' : ($address->label === 'Work' ? '🏢' : '📍') }}</span>
                        <div class="flex-1 min-w-0 pr-20">
                            <p class="font-semibold text-sm" style="color:#002b4d;">
                                {{ $address->label }}
                                <span class="font-normal text-xs ml-1" style="color:#6b90aa;">· {{ $address->full_name }}</span>
                            </p>
                            @if ($address->phone)
                                <p class="text-xs mt-0.5" style="color:#6b90aa;">📞 {{ $address->phone }}</p>
                            @endif
                            <p class="text-sm mt-1" style="color:#1a4d6e;">{{ $address->full_address }}</p>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if (! $address->is_default)
                            <form action="{{ route('addresses.set-default', $address) }}" method="POST">
                                @csrf @method('PATCH')
                                <button type="submit"
                                        class="rounded-full border px-4 py-1.5 text-xs font-semibold transition"
                                        style="border-color:#fa4e1c;color:#fa4e1c;"
                                        onmouseover="this.style.background='#FFF6EE';" onmouseout="this.style.background='';">
                                    Set as Default
                                </button>
                            </form>
                        @endif

                        <button onclick="openEditModal({{ $address->id }}, {{ json_encode($address) }})"
                                class="rounded-full border px-4 py-1.5 text-xs font-semibold transition"
                                style="border-color:#cfdce8;color:#1a4d6e;"
                                onmouseover="this.style.background='#FFF8F3';" onmouseout="this.style.background='';">
                            Edit
                        </button>

                        <form action="{{ route('addresses.destroy', $address) }}" method="POST"
                              onsubmit="return confirm('Remove this address?')">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="rounded-full border px-4 py-1.5 text-xs font-semibold transition"
                                    style="border-color:#FCA5A5;color:#DC2626;"
                                    onmouseover="this.style.background='#FEF2F2';" onmouseout="this.style.background='';">
                                Remove
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center gap-3 rounded-2xl py-16 text-center"
                     style="background:#fff;border:1px solid #cfdce8;">
                    <span class="text-5xl">📍</span>
                    <p class="font-display text-base font-semibold" style="color:#002b4d;">No addresses saved yet</p>
                    <p class="text-sm" style="color:#6b90aa;">Add an address to speed up checkout.</p>
                    <button onclick="document.getElementById('add-address-modal').classList.remove('hidden')"
                            class="mt-2 rounded-full px-6 py-2.5 text-sm font-semibold transition"
                            style="background:#fa4e1c;color:#fff;"
                            onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                        Add Your First Address
                    </button>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ═══════════════════════════════ ADD ADDRESS MODAL ═══════════════════════════════ --}}
<div id="add-address-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5);">
    <div class="w-full max-w-lg rounded-2xl shadow-xl overflow-hidden" style="background:#fff;">
        <div class="flex items-center justify-between px-6 py-4" style="background:#FFF6EE;border-bottom:1px solid #cfdce8;">
            <h3 class="font-display font-bold text-lg" style="color:#002b4d;">Add New Address</h3>
            <button onclick="document.getElementById('add-address-modal').classList.add('hidden')"
                    class="text-2xl leading-none" style="color:#6b90aa;">&times;</button>
        </div>
        <form action="{{ route('addresses.store') }}" method="POST" class="p-6 grid gap-4 sm:grid-cols-2">
            @csrf

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Label</label>
                <select name="label" class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                        style="border-color:#cfdce8;color:#002b4d;"
                        onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
                    <option value="Home">🏠 Home</option>
                    <option value="Work">🏢 Work</option>
                    <option value="Other">📍 Other</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Full Name</label>
                <input type="text" name="full_name" value="{{ old('full_name', auth()->user()->name) }}"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                       required placeholder="Recipient name">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                       placeholder="+63 912 345 6789">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">ZIP Code</label>
                <input type="text" name="zip"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                       placeholder="1234">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Street / House No.</label>
                <input type="text" name="address_line"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                       required placeholder="e.g. 123 Rizal St., Unit 4B">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Barangay</label>
                <input type="text" name="barangay"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                       placeholder="Barangay">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">City / Municipality</label>
                <input type="text" name="city"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                       required placeholder="Quezon City">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Province</label>
                <input type="text" name="province"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                       placeholder="Metro Manila">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Country</label>
                <input type="text" name="country" value="Philippines"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            </div>

            <div class="sm:col-span-2 flex items-center gap-3">
                <input type="checkbox" name="is_default" value="1" id="add_is_default" class="rounded accent-orange-500">
                <label for="add_is_default" class="text-sm font-medium" style="color:#1a4d6e;">Set as default delivery address</label>
            </div>

            <div class="sm:col-span-2 flex justify-end gap-3 pt-2">
                <button type="button"
                        onclick="document.getElementById('add-address-modal').classList.add('hidden')"
                        class="rounded-full border px-6 py-2.5 text-sm font-semibold transition"
                        style="border-color:#cfdce8;color:#1a4d6e;"
                        onmouseover="this.style.background='#FFF8F3';" onmouseout="this.style.background='';">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-full px-6 py-2.5 text-sm font-semibold transition"
                        style="background:#fa4e1c;color:#fff;"
                        onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                    Save Address
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════ EDIT ADDRESS MODAL ═══════════════════════════════ --}}
<div id="edit-address-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5);">
    <div class="w-full max-w-lg rounded-2xl shadow-xl overflow-hidden" style="background:#fff;">
        <div class="flex items-center justify-between px-6 py-4" style="background:#FFF6EE;border-bottom:1px solid #cfdce8;">
            <h3 class="font-display font-bold text-lg" style="color:#002b4d;">Edit Address</h3>
            <button onclick="document.getElementById('edit-address-modal').classList.add('hidden')"
                    class="text-2xl leading-none" style="color:#6b90aa;">&times;</button>
        </div>
        <form id="edit-address-form" method="POST" class="p-6 grid gap-4 sm:grid-cols-2">
            @csrf @method('PATCH')

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Label</label>
                <select name="label" id="edit_label" class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                        style="border-color:#cfdce8;color:#002b4d;">
                    <option value="Home">🏠 Home</option>
                    <option value="Work">🏢 Work</option>
                    <option value="Other">📍 Other</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Full Name</label>
                <input type="text" name="full_name" id="edit_full_name"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Phone</label>
                <input type="text" name="phone" id="edit_phone"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">ZIP Code</label>
                <input type="text" name="zip" id="edit_zip"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Street / House No.</label>
                <input type="text" name="address_line" id="edit_address_line"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Barangay</label>
                <input type="text" name="barangay" id="edit_barangay"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">City / Municipality</label>
                <input type="text" name="city" id="edit_city"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Province</label>
                <input type="text" name="province" id="edit_province"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            </div>

            <div>
                <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Country</label>
                <input type="text" name="country" id="edit_country"
                       class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                       style="border-color:#cfdce8;color:#002b4d;"
                       onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
            </div>

            <div class="sm:col-span-2 flex items-center gap-3">
                <input type="checkbox" name="is_default" value="1" id="edit_is_default" class="rounded accent-orange-500">
                <label for="edit_is_default" class="text-sm font-medium" style="color:#1a4d6e;">Set as default delivery address</label>
            </div>

            <div class="sm:col-span-2 flex justify-end gap-3 pt-2">
                <button type="button"
                        onclick="document.getElementById('edit-address-modal').classList.add('hidden')"
                        class="rounded-full border px-6 py-2.5 text-sm font-semibold transition"
                        style="border-color:#cfdce8;color:#1a4d6e;"
                        onmouseover="this.style.background='#FFF8F3';" onmouseout="this.style.background='';">
                    Cancel
                </button>
                <button type="submit"
                        class="rounded-full px-6 py-2.5 text-sm font-semibold transition"
                        style="background:#fa4e1c;color:#fff;"
                        onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Close modals on backdrop click --}}
<script>
['add-address-modal','edit-address-modal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});

function openEditModal(id, addr) {
    var base = '{{ url("/profile/addresses") }}/';
    document.getElementById('edit-address-form').action = base + id;
    document.getElementById('edit_label').value        = addr.label        || 'Home';
    document.getElementById('edit_full_name').value    = addr.full_name    || '';
    document.getElementById('edit_phone').value        = addr.phone        || '';
    document.getElementById('edit_address_line').value = addr.address_line || '';
    document.getElementById('edit_barangay').value     = addr.barangay     || '';
    document.getElementById('edit_city').value         = addr.city         || '';
    document.getElementById('edit_province').value     = addr.province     || '';
    document.getElementById('edit_zip').value          = addr.zip          || '';
    document.getElementById('edit_country').value      = addr.country      || 'Philippines';
    document.getElementById('edit_is_default').checked = addr.is_default   == 1;
    document.getElementById('edit-address-modal').classList.remove('hidden');
}

// Auto-open add modal if validation errors exist (user was adding)
@if ($errors->any() && old('address_line'))
    document.getElementById('add-address-modal').classList.remove('hidden');
@endif
</script>
</x-layout>
