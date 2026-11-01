<x-admin-layout :title="$voucher->exists ? 'Edit voucher ' . $voucher->code : 'New ALVY voucher'" active="vouchers">

<a href="{{ route('admin.vouchers.index') }}" class="mb-4 inline-block text-sm font-semibold" style="color:#fa4e1c;">← Vouchers</a>

<div class="card max-w-3xl p-6">
    <p class="mb-5 text-sm" style="color:#6b90aa;">
        ALVY vouchers work on <strong>any seller's</strong> products. ALVY pays for the discount — sellers still earn their full share.
    </p>

    <form method="POST" action="{{ $voucher->exists ? route('admin.vouchers.update', $voucher) : route('admin.vouchers.store') }}">
        @csrf
        @if ($voucher->exists) @method('PUT') @endif

        @include('vouchers._fields', ['allowShipping' => true])

        <div class="mt-6 flex gap-3">
            <button class="btn-gold px-6 py-2.5 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">
                {{ $voucher->exists ? 'Save changes' : 'Create voucher' }}
            </button>
            <a href="{{ route('admin.vouchers.index') }}" class="btn-outline px-6 py-2.5 text-sm">Cancel</a>
        </div>
    </form>
</div>

</x-admin-layout>
