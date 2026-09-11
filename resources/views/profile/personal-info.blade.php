<x-layout title="Personal Info — ALVY">
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1">
            <div class="rounded-2xl p-6 sm:p-8" style="background:#fff;border:1px solid #cfdce8;">

                <h2 class="font-display text-xl font-bold mb-1" style="color:#002b4d;">Personal Information</h2>
                <p class="text-sm mb-6" style="color:#6b90aa;">Update your name, email, and contact details.</p>

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

                <form action="{{ route('profile.update') }}" method="POST" class="grid gap-5 sm:grid-cols-2">
                    @csrf @method('PATCH')

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               placeholder="+63 912 345 6789">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Country</label>
                        <input type="text" name="country" value="{{ old('country', auth()->user()->country) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               placeholder="Philippines">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Address</label>
                        <input type="text" name="address" value="{{ old('address', auth()->user()->address) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               placeholder="Street, House No.">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">City</label>
                        <input type="text" name="city" value="{{ old('city', auth()->user()->city) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">ZIP / Postal Code</label>
                        <input type="text" name="zip" value="{{ old('zip', auth()->user()->zip) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
                    </div>

                    <div class="sm:col-span-2 pt-2">
                        <button type="submit"
                                class="rounded-full px-8 py-2.5 text-sm font-semibold transition"
                                style="background:#fa4e1c;color:#fff;"
                                onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</x-layout>
