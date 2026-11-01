<x-seller-layout :title="$voucher->exists ? 'Edit voucher ' . $voucher->code : 'New store voucher'" active="vouchers">

<a href="{{ route('seller.vouchers.index') }}" class="mb-4 inline-block text-sm font-semibold" style="color:#fa4e1c;">← Vouchers</a>

<div class="max-w-3xl rounded-xl border bg-white p-6" style="border-color:#ECE7E3;">
    <h1 class="text-lg font-bold" style="color:#222;">{{ $voucher->exists ? 'Edit voucher' : 'New store voucher' }}</h1>
    <p class="mb-5 mt-1 text-sm" style="color:#8a7f78;">
        Works only on your products. The minimum spend counts only your items in the buyer's cart, and the discount comes out of your sale.
    </p>

    <form method="POST" action="{{ $voucher->exists ? route('seller.vouchers.update', $voucher) : route('seller.vouchers.store') }}">
        @csrf
        @if ($voucher->exists) @method('PUT') @endif

        @include('vouchers._fields')

        <div class="mt-6 flex gap-3">
            <button class="rounded-lg px-6 py-2.5 text-sm font-semibold text-white" style="background:#fa4e1c;">
                {{ $voucher->exists ? 'Save changes' : 'Create voucher' }}
            </button>
            <a href="{{ route('seller.vouchers.index') }}" class="rounded-lg border px-6 py-2.5 text-sm font-semibold" style="border-color:#E6D9CF;color:#57534E;">Cancel</a>
        </div>
    </form>
</div>

</x-seller-layout>
