<x-layout title="Test payment — ALVY">
<div class="mx-auto max-w-md px-4 py-12">
    <div class="rounded-xl border-2 border-dashed p-4 text-center text-sm font-bold" style="border-color:#F59E0B;background:#FFFBEB;color:#92400E;">
        TEST PAYMENT PAGE — no real money moves.<br>
        <span class="font-normal">This stands in for GCash, Maya or the card form while online payment is in test mode.</span>
    </div>

    <div class="mt-5 rounded-xl border bg-white p-5" style="border-color:#ECE7E3;">
        <p class="text-sm" style="color:#8a7f78;">{{ $payment->methodLabel() }} · {{ $payment->provider_ref }}</p>
        <p class="mt-1 font-display text-3xl font-extrabold" style="color:#222;">₱{{ number_format($payment->amount, 2) }}</p>
        <ul class="mt-3 space-y-1 text-sm" style="color:#57534E;">
            @foreach ($orders as $order)
                <li>Order {{ $order->number() }} — ₱{{ number_format($order->total_price, 2) }} <span class="text-xs">({{ $order->status }})</span></li>
            @endforeach
        </ul>

        @if ($payment->isPending())
            <form action="{{ route('payments.sandbox.act', $payment) }}" method="POST" class="mt-5 grid gap-2">
                @csrf
                <button name="result" value="pay" class="rounded-lg py-3 font-bold text-white" style="background:#059669;">Pay ₱{{ number_format($payment->amount, 2) }}</button>
                <button name="result" value="decline" class="rounded-lg border py-2.5 text-sm font-semibold" style="border-color:#FCA5A5;color:#B91C1C;">Simulate declined card / failed payment</button>
                <button name="result" value="cancel" class="rounded-lg border py-2.5 text-sm font-semibold" style="border-color:#E7E5E4;color:#57534E;">Cancel and go back to ALVY</button>
            </form>
        @else
            <p class="mt-5 text-sm font-semibold">This payment is {{ $payment->status }}.</p>
            <a href="{{ route('payments.return', $payment) }}" class="mt-2 inline-block text-sm font-semibold" style="color:#fa4e1c;">Back to ALVY</a>
        @endif
    </div>
</div>
</x-layout>
