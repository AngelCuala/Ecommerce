<x-admin-layout title="Buyer details" active="customers">
@include('partials.panel.plain')
@php
    $status   = $customer->status;
    $fullName = trim($customer->first_name . ' ' . ($customer->middle_initial ? $customer->middle_initial . '. ' : '') . $customer->last_name) ?: $customer->name;
    $statusTag = ['approved' => 'delivered', 'pending' => 'pending', 'unverified' => 'neutral', 'rejected' => 'cancelled', 'suspended' => 'cancelled'][$status] ?? 'neutral';
    $address  = collect([$customer->house_number, $customer->street, $customer->barangay, $customer->municipality, $customer->province])
        ->filter()->implode(', ');
    $orders   = $customer->orders->sortByDesc('created_at');
    $spent    = $orders->where('status', '!=', 'Cancelled')->sum('total_price');
    $orderTag = fn ($s) => ['pending' => 'pending', 'processing' => 'processing', 'shipped' => 'shipped', 'delivered' => 'delivered', 'cancelled' => 'cancelled'][strtolower((string) $s)] ?? 'neutral';
    $idExt    = $customer->valid_id_path ? strtolower(pathinfo($customer->valid_id_path, PATHINFO_EXTENSION)) : null;
    $first    = addslashes($customer->first_name ?: $customer->name);
@endphp

<div class="mx-auto max-w-5xl">

    {{-- Header --}}
    <a href="{{ route('admin.customers.index') }}" class="text-sm hover:underline pl-muted">&larr; Buyers</a>
    <div class="mt-1 mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-2xl font-bold" style="color:#222;">{{ $fullName }}</h1>
                <span class="pl-tag {{ $statusTag }}">{{ ucfirst($status) }}</span>
            </div>
            <p class="mt-0.5 text-sm pl-muted">
                {{ $customer->username ? '@' . $customer->username . ' · ' : '' }}Joined {{ $customer->created_at->format('M j, Y') }}
                · {{ $orders->count() }} {{ \Illuminate\Support\Str::plural('order', $orders->count()) }}
            </p>
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap gap-2">
            @if ($status === 'pending')
                <button type="button" onclick="document.getElementById('reject-panel').classList.toggle('hidden')" class="pl-btn-line !text-[#B91C1C]">Reject</button>
                <form action="{{ route('admin.customers.approve', $customer->id) }}" method="POST"
                      onsubmit="return confirm('Approve {{ $first }}\'s account?')">
                    @csrf @method('PATCH')
                    <button type="submit" class="pl-btn">Approve</button>
                </form>
            @elseif ($status === 'approved')
                <form action="{{ route('admin.customers.suspend', $customer->id) }}" method="POST"
                      onsubmit="return confirm('Suspend {{ $first }}\'s account? They won\'t be able to log in.')">
                    @csrf @method('PATCH')
                    <button type="submit" class="pl-btn-line !text-[#B91C1C]">Suspend account</button>
                </form>
            @elseif ($status === 'suspended')
                <form action="{{ route('admin.customers.restore', $customer->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="pl-btn">Restore account</button>
                </form>
            @endif
        </div>
    </div>

    {{-- Rejection form (pending only) --}}
    @if ($status === 'pending')
        <form id="reject-panel" action="{{ route('admin.customers.reject', $customer->id) }}" method="POST"
              class="pl-card pl-body mb-5 hidden" onsubmit="return confirm('Reject {{ $first }}\'s account?')">
            @csrf @method('PATCH')
            <p class="pl-label">Reason for rejection</p>
            <div class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2" style="color:#44403C;">
                @foreach ([
                    'invalid_id'             => 'Invalid ID',
                    'incorrect_information'  => 'Incorrect information',
                    'duplicate_account'      => 'Duplicate account',
                    'incomplete_information' => 'Incomplete information',
                    'invalid_address'        => 'Invalid address',
                ] as $value => $label)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="rejection_reason" value="{{ $value }}" required style="accent-color:#fa4e1c;"> {{ $label }}
                    </label>
                @endforeach
                <label class="flex items-center gap-2">
                    <input type="radio" name="rejection_reason" value="other" id="reason-other-radio" style="accent-color:#fa4e1c;"> Other
                </label>
            </div>
            <input type="text" name="rejection_reason_other" id="reason-other-input" placeholder="Describe the reason" class="input mt-3 hidden text-sm">
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('reject-panel').classList.add('hidden')" class="pl-btn-line">Cancel</button>
                <button type="submit" class="pl-btn !bg-[#DC2626]">Reject account</button>
            </div>
        </form>
    @endif

    @if ($status === 'rejected' && $customer->rejection_reason)
        <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#991B1B;">
            Rejected: {{ \Illuminate\Support\Str::of($customer->rejection_reason)->replace('_', ' ')->ucfirst() }}
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_18rem]">

        {{-- Details --}}
        <section class="pl-card">
            <div class="pl-head"><h2>Details</h2></div>
            <dl class="pl-dl pl-body !py-2">
                <div><dt>Full name</dt><dd>{{ $fullName }}</dd></div>
                <div><dt>Sex</dt><dd>{{ $customer->sex ? ucfirst($customer->sex) : '—' }}</dd></div>
                <div><dt>Birthday</dt><dd>{{ $customer->birthday ? $customer->birthday->format('M j, Y') . ' (age ' . $customer->birthday->age . ')' : '—' }}</dd></div>
                <div><dt>Email</dt><dd class="break-all">{{ $customer->email }}</dd></div>
                <div><dt>Mobile number</dt><dd>{{ $customer->contact_no ?: ($customer->phone ?: '—') }}</dd></div>
                <div><dt>Username</dt><dd>{{ $customer->username ?: '—' }}</dd></div>
                <div><dt>Address</dt><dd class="max-w-[22rem]">{{ $address ?: '—' }}</dd></div>
            </dl>
        </section>

        {{-- Valid ID --}}
        <section class="pl-card self-start">
            <div class="pl-head"><h2>Valid ID</h2></div>
            <div class="pl-body">
                @if ($customer->valid_id_path)
                    @if (in_array($idExt, ['jpg', 'jpeg', 'png', 'webp']))
                        <button type="button" class="block w-full" onclick="document.getElementById('customer-id-lightbox').classList.remove('hidden')">
                            <img src="{{ route('admin.customers.validId', $customer->id) }}" alt="Uploaded ID"
                                 class="max-h-44 w-full rounded-md border object-contain transition hover:opacity-90" style="border-color:#ECE7E3;background:#FAFAF9;">
                        </button>
                        <a href="{{ route('admin.customers.validId', $customer->id) }}" target="_blank" rel="noopener" class="pl-link mt-2 inline-block">Open full size</a>

                        <div id="customer-id-lightbox" class="fixed inset-0 z-50 hidden items-center justify-center p-4 [&:not(.hidden)]:flex"
                             style="background:rgba(0,0,0,.8);" onclick="this.classList.add('hidden')">
                            <img src="{{ route('admin.customers.validId', $customer->id) }}" alt="Uploaded ID"
                                 class="max-h-[85vh] max-w-full rounded-lg object-contain" onclick="event.stopPropagation()">
                        </div>
                    @else
                        <a href="{{ route('admin.customers.validId', $customer->id) }}" target="_blank" rel="noopener"
                           class="flex items-center justify-between rounded-md border px-3 py-2 text-sm hover:bg-stone-50" style="border-color:#ECE7E3;color:#222;">
                            Uploaded ID ({{ strtoupper($idExt) }})
                            <span class="pl-link">View</span>
                        </a>
                    @endif
                    @include('partials.id-check-note', ['record' => $customer])
                @else
                    <p class="text-sm pl-muted">No ID uploaded.</p>
                @endif
            </div>
        </section>
    </div>

    {{-- Orders --}}
    <section class="pl-card mt-5 overflow-hidden">
        <div class="pl-head">
            <h2>Orders</h2>
            @if ($orders->isNotEmpty())
                <span class="text-sm pl-muted">₱{{ number_format($spent, 2) }} spent</span>
            @endif
        </div>
        @if ($orders->isEmpty())
            <p class="px-5 py-8 text-center text-sm pl-muted">No orders yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="pl-table">
                    <thead>
                        <tr><th>Order</th><th>Date</th><th>Items</th><th>Status</th><th class="!text-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order->id) }}" class="font-medium hover:underline" style="color:#222;">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</a></td>
                                <td class="whitespace-nowrap pl-muted">{{ $order->created_at->format('M j, Y') }}</td>
                                <td class="max-w-[16rem] truncate">{{ $order->items->map(fn ($i) => $i->product->title ?? 'Removed product')->implode(', ') }}</td>
                                <td><span class="pl-tag {{ $orderTag($order->status) }}">{{ $order->status }}</span></td>
                                <td class="text-right font-medium" style="color:#222;">₱{{ number_format($order->total_price, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<script src="{{ asset('js/customer-reject.js') }}"></script>
</x-admin-layout>
