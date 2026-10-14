<x-admin-layout title="Vouchers" active="vouchers">

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <p class="max-w-2xl text-sm" style="color:#6b90aa;">
        <strong style="color:#002b4d;">ALVY vouchers</strong> work on any seller's products — use them for sales like 11.11 or a flash deal.
        ALVY pays for the discount, so sellers earn the same. <strong style="color:#002b4d;">Store vouchers</strong> are made by sellers for their own products;
        you can turn one off if needed.
    </p>
    @if ($scope === 'platform')
        <a href="{{ route('admin.vouchers.create') }}" class="btn-gold text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">+ New ALVY voucher</a>
    @endif
</div>

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <div class="flex gap-2">
        @foreach (['platform' => 'ALVY vouchers', 'seller' => 'Store vouchers'] as $key => $label)
            <a href="{{ route('admin.vouchers.index', ['scope' => $key]) }}" class="rounded-full border px-4 py-1.5 text-sm font-semibold"
               style="{{ $scope === $key ? 'background:#fa4e1c;border-color:#fa4e1c;color:#fff;' : 'border-color:#FFDCC2;color:#6b5a50;' }}">
                {{ $label }} <span class="opacity-80">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </div>
    <form method="GET" class="flex gap-2">
        <input type="hidden" name="scope" value="{{ $scope }}">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Code or name…" class="input w-52 py-2 text-sm" style="border-color:#FFDCC2;">
        <button class="btn-gold !py-2 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">Search</button>
    </form>
</div>

@if (session('success'))
    <div class="mb-4 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-4 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">{{ session('error') }}</div>
@endif

@include('vouchers._list', ['panel' => 'admin', 'showSeller' => $scope === 'seller'])

<div class="mt-4">{{ $vouchers->links() }}</div>

</x-admin-layout>
