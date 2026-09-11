<x-admin-layout title="Order Management" active="orders">

{{-- Filters --}}
<form method="GET" class="mb-6 flex flex-wrap items-center gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search buyer name or email…"
           class="input w-64 py-2 text-sm" style="border-color:#FFDCC2;">
    <select name="status" class="input w-40 py-2 text-sm" onchange="this.form.submit()" style="border-color:#FFDCC2;">
        <option value="">All statuses</option>
        @foreach ($statuses as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
        @endforeach
    </select>
    <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Filter</button>
    <a href="{{ route('admin.orders.index') }}" class="text-sm" style="color:#fa4e1c;">Clear</a>
</form>

<div class="card overflow-hidden">
    <table class="w-full text-sm">
        <thead style="background:#e8f0f6;">
            <tr>
                <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">#</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Buyer</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Date</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Payment</th>
                <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                @php
                    $sc = match(strtolower($order->status)) {
                        'delivered'  => ['bg'=>'#ECFDF5','text'=>'#059669'],
                        'shipped'    => ['bg'=>'#FFF7ED','text'=>'#d93d0e'],
                        'processing' => ['bg'=>'#FFEEDD','text'=>'#d93d0e'],
                        'pending'    => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'],
                        'cancelled'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                        default      => ['bg'=>'#F5F5F5','text'=>'#666666'],
                    };
                @endphp
                <tr style="border-top:1px solid #dce8f0;"
                    onmouseover="this.style.background='#e8f0f6';"
                    onmouseout="this.style.background='';">
                    <td class="px-5 py-3 font-semibold" style="color:#222222;">
                        <a href="{{ route('admin.orders.show', $order->id) }}"
                           style="color:#fa4e1c;"
                           onmouseover="this.style.color='#d93d0e';"
                           onmouseout="this.style.color='#fa4e1c';">
                            #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}
                        </a>
                    </td>
                    <td class="px-3 py-3" style="color:#444444;">
                        <p class="font-medium">{{ $order->user->name ?? 'Deleted User' }}</p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $order->user->email ?? '' }}</p>
                    </td>
                    <td class="px-3 py-3 text-xs" style="color:#6b90aa;">{{ $order->created_at->format('M d, Y') }}</td>
                    <td class="px-3 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                              style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                            {{ $order->status }}
                        </span>
                    </td>
                    <td class="px-3 py-3 text-xs capitalize" style="color:#555555;">{{ $order->payment_method }}</td>
                    <td class="px-5 py-3 text-right font-bold" style="color:#222222;">${{ number_format($order->total_price, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-10 text-center text-sm" style="color:#6b90aa;">No orders yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $orders->links() }}</div>

</x-admin-layout>