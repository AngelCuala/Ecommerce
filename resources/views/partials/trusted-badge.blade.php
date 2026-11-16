{{-- "Trusted Seller" badge (App\Support\TrustedSeller). Usage: @include('partials.trusted-badge', ['seller' => $seller]) --}}
@if (\App\Support\TrustedSeller::is($seller ?? null))
    <span class="ml-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 align-middle text-[11px] font-bold" style="background:#ECFDF5;color:#047857;border:1px solid #A7F3D0;"
          title="Trusted Seller: sells well on ALVY, well rated, and keeps orders and payments on ALVY" data-trusted-seller>
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6l8-3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
        Trusted Seller
    </span>
@endif
