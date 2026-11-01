<x-seller-layout title="Vouchers" active="vouchers">

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-bold" style="color:#222;">Store vouchers</h1>
        <p class="mt-1 max-w-2xl text-sm" style="color:#8a7f78;">
            Discounts that only work on <strong>your</strong> products — e.g. ₱150 off when a buyer spends ₱2,000 in your store.
            The discount comes out of your sale for that order.
        </p>
    </div>
    <a href="{{ route('seller.vouchers.create') }}" class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#fa4e1c;">+ New voucher</a>
</div>

@if (session('success'))
    <div class="mb-4 rounded-lg border p-3 text-sm" style="background:#ECFDF5;border-color:#A7F3D0;color:#047857;">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-lg border p-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">{{ session('error') }}</div>
@endif

@include('vouchers._list', ['panel' => 'seller'])

</x-seller-layout>
