<x-admin-layout title="Buyer Application" active="customers">

<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('admin.customers.index') }}"
       class="text-sm transition" style="color:#fa4e1c;"
       onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
        ← Back to buyers
    </a>

    @php
        $sc = match($customer->status) {
            'approved'  => ['bg'=>'#F0FDF4','text'=>'#059669'],
            'rejected'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
            'suspended' => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
            default     => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'],
        };
    @endphp
    <span class="rounded-full px-3 py-1 text-xs font-semibold"
          style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
        {{ ucfirst($customer->status) }}
    </span>
</div>

<div class="grid gap-6 lg:grid-cols-[280px_1fr]">

    {{-- Left: avatar + quick facts --}}
    <div class="card h-fit p-6 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full font-display text-2xl font-bold"
             style="background:#fa4e1c;color:#FFFFFF;">
            {{ strtoupper(substr($customer->first_name, 0, 1)) }}
        </div>
        <p class="mt-3 font-display text-lg font-bold" style="color:#222222;">
            {{ $customer->first_name }} {{ $customer->middle_initial ? $customer->middle_initial.'.' : '' }} {{ $customer->last_name }}
        </p>
        <p class="text-sm" style="color:#6b90aa;">@{{ $customer->username }}</p>

        <div class="mt-5 space-y-2 text-sm text-left">
            <div class="flex justify-between border-b pb-2" style="border-color:#dce8f0;">
                <span style="color:#6b90aa;">Applied</span>
                <span style="color:#222222;">{{ $customer->created_at->format('M d, Y') }}</span>
            </div>
            <div class="flex justify-between">
                <span style="color:#6b90aa;">Total Orders</span>
                <span class="font-semibold" style="color:#222222;">{{ $customer->orders->count() }}</span>
            </div>
        </div>

        {{-- Approve / Reject --}}
        @if ($customer->status === 'pending')
            <div class="mt-5 flex flex-col gap-2 border-t pt-4" style="border-color:#dce8f0;">
                <form action="{{ route('admin.customers.approve', $customer->id) }}" method="POST"
                    onsubmit="return confirm('Approve {{ addslashes($customer->first_name) }} {{ addslashes($customer->last_name) }}?')">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-gold w-full !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Approve</button>
                </form>

                <button type="button" onclick="document.getElementById('reject-panel').classList.toggle('hidden')"
                        class="w-full rounded-full border-2 py-2 text-sm font-semibold transition"
                        style="border-color:#DC2626;color:#DC2626;"
                        onmouseover="this.style.background='#FEF2F2';"
                        onmouseout="this.style.background='';">
                    Reject
                </button>

                {{-- Rejection reason panel --}}
                <div id="reject-panel" class="hidden mt-3 rounded-lg border p-4 text-left" style="border-color:#dce8f0;background:#e8f0f6;">
                    <form action="{{ route('admin.customers.reject', $customer->id) }}" method="POST"
                        onsubmit="return confirm('Reject {{ addslashes($customer->first_name) }} {{ addslashes($customer->last_name) }}?')">
                        @csrf @method('PATCH')

                        <p class="mb-2 text-xs font-semibold" style="color:#222222;">Reason for rejection:</p>

                        <div class="space-y-2 text-sm" style="color:#222222;">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rejection_reason" value="invalid_id" required>
                                Invalid ID
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rejection_reason" value="incorrect_information">
                                Incorrect information
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rejection_reason" value="duplicate_account">
                                Duplicate account
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rejection_reason" value="incomplete_information">
                                Incomplete information
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rejection_reason" value="invalid_address">
                                Invalid address
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="rejection_reason" value="other" id="reason-other-radio">
                                Other
                            </label>
                            <input type="text" name="rejection_reason_other" id="reason-other-input"
                                placeholder="Please specify…" class="input mt-1 hidden text-sm">
                        </div>

                        <button type="submit"
                                class="mt-3 w-full rounded-full py-2 text-sm font-semibold text-white transition"
                                style="background:#DC2626;">
                            Confirm Rejection
                        </button>
                    </form>
                </div>
            </div>
        @elseif ($customer->status === 'approved')
            <div class="mt-5 border-t pt-4" style="border-color:#dce8f0;">
                <form action="{{ route('admin.customers.suspend', $customer->id) }}" method="POST"
                    onsubmit="return confirm('Suspend {{ addslashes($customer->first_name) }} {{ addslashes($customer->last_name) }}?')">
                    @csrf @method('PATCH')
                    <button type="submit" class="w-full rounded-full border-2 py-2 text-sm font-semibold transition"
                            style="border-color:#DC2626;color:#DC2626;"
                            onmouseover="this.style.background='#FEF2F2';"
                            onmouseout="this.style.background='';">
                        Suspend Account
                    </button>
                </form>
            </div>
        @elseif ($customer->status === 'suspended')
            <div class="mt-5 border-t pt-4" style="border-color:#dce8f0;">
                <form action="{{ route('admin.customers.restore', $customer->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-gold w-full !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Restore Account</button>
                </form>
            </div>
        @elseif ($customer->status === 'rejected' && $customer->rejection_reason)
            <div class="mt-5 rounded-lg border-t pt-4 text-left text-sm" style="border-color:#dce8f0;">
                <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Rejection reason</p>
                <p class="mt-1" style="color:#222222;">{{ $customer->rejection_reason }}</p>
            </div>
        @endif

    {{-- Right: full details --}}
    <div class="space-y-6">

        {{-- Personal Information --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Personal Information</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <dt style="color:#6b90aa;">Last name</dt>
                    <dd style="color:#222222;">{{ $customer->last_name }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">First name</dt>
                    <dd style="color:#222222;">{{ $customer->first_name }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Middle initial</dt>
                    <dd style="color:#222222;">{{ $customer->middle_initial ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Sex</dt>
                    <dd style="color:#222222;">{{ ucfirst($customer->sex) }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Birthday</dt>
                    <dd style="color:#222222;">{{ $customer->birthday->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Age</dt>
                    <dd style="color:#222222;">{{ $customer->age }}</dd>
                </div>
            </dl>
        </div>

        {{-- Account Information --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Account Information</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <dt style="color:#6b90aa;">Username</dt>
                    <dd style="color:#222222;">{{ $customer->username }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Email</dt>
                    <dd style="color:#222222;">{{ $customer->email }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Contact number</dt>
                    <dd style="color:#222222;">{{ $customer->contact_no }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Registered on</dt>
                    <dd style="color:#222222;">{{ $customer->created_at->format('M d, Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Address --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Address</h2>
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 text-sm">
                <div>
                    <dt style="color:#6b90aa;">Province</dt>
                    <dd style="color:#222222;">{{ $customer->province }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Municipality</dt>
                    <dd style="color:#222222;">{{ $customer->municipality }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Barangay</dt>
                    <dd style="color:#222222;">{{ $customer->barangay }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">Street</dt>
                    <dd style="color:#222222;">{{ $customer->street ?? '—' }}</dd>
                </div>
                <div>
                    <dt style="color:#6b90aa;">House number</dt>
                    <dd style="color:#222222;">{{ $customer->house_number ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Uploaded ID --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Uploaded ID</h2>
            @if ($customer->valid_id_path)
                @php
                    $ext = strtolower(pathinfo($customer->valid_id_path, PATHINFO_EXTENSION));
                @endphp
                @if (in_array($ext, ['jpg', 'jpeg', 'png']))
                    <a href="{{ route('admin.customers.validId', $customer->id) }}" target="_blank">
                        <img src="{{ route('admin.customers.validId', $customer->id) }}"
                             alt="Uploaded ID" class="max-h-64 rounded-lg border" style="border-color:#dce8f0;">
                    </a>
                @else
                    <a href="{{ route('admin.customers.validId', $customer->id) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm font-semibold" style="color:#fa4e1c;">
                        📄 View uploaded ID (PDF)
                    </a>
                @endif
            @else
                <p class="text-sm" style="color:#6b90aa;">No ID uploaded.</p>
            @endif
        </div>

        {{-- Order History --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Order History</h2>
            @forelse ($customer->orders->sortByDesc('created_at') as $order)
                @php $oc = $order->statusColor(); @endphp
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg px-4 py-3" style="background:#e8f0f6;">
                    <div>
                        <a href="{{ route('admin.orders.show', $order->id) }}"
                           class="font-semibold text-sm" style="color:#fa4e1c;">
                            Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
                        </a>
                        <p class="text-xs" style="color:#6b90aa;">{{ $order->created_at->format('M d, Y') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $oc['bg'] }};color:{{ $oc['text'] }};">
                            {{ $order->status }}
                        </span>
                        <span class="font-bold text-sm" style="color:#222222;">${{ number_format($order->total_price, 2) }}</span>
                    </div>
                </div>
            @empty
                <p class="text-sm" style="color:#6b90aa;">This buyer has no orders yet.</p>
            @endforelse
        </div>

    </div>
</div>


<script>
    const otherRadio = document.getElementById('reason-other-radio');
    const otherInput = document.getElementById('reason-other-input');

    document.querySelectorAll('input[name="rejection_reason"]').forEach(radio => {
        radio.addEventListener('change', () => {
            otherInput.classList.toggle('hidden', !otherRadio.checked);
            otherInput.required = otherRadio.checked;
        });
    });
</script>
</x-admin-layout>