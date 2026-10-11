@php
    $status = $user->approval_status;
    $label  = ['pending' => 'Pending Approval', 'rejected' => 'Rejected', 'unverified' => 'Unverified'][$status] ?? ucfirst($status);
@endphp
<x-auth-shell title="Seller Application — ALVY" heading="Seller Application"
              :subtitle="'Hi ' . ($user->first_name ?: $user->name) . ' — here\'s where your shop application stands.'"
              width="wide" :shop-link="false">
    <x-slot:head>
        @include('profile._styles')
    </x-slot:head>
    <x-slot:topRight>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-white/70 px-3.5 py-1.5 text-sm font-semibold shadow-sm transition hover:bg-white" style="color:#5a4d45;">Log out</button>
        </form>
    </x-slot:topRight>

    <div class="space-y-5 px-6 pb-8 pt-6 sm:px-10 sm:pb-10">
        @if (session('success'))
            <div class="pf-alert pf-alert-success">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="pf-alert pf-alert-error">{{ $errors->first() }}</div>
        @endif

        {{-- Status --}}
        <div class="pf-card p-6 sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    @if ($status === 'rejected')
                        <span class="pf-icon" style="background:#FEF2F2;color:#DC2626;">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="m15 9-6 6M9 9l6 6"/></svg>
                        </span>
                    @else
                        <span class="pf-icon">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                        </span>
                    @endif
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide pf-muted">Application status</p>
                        <p class="font-display text-lg font-extrabold" style="color:#222;">{{ $application?->shop_name ?? 'Your shop' }}</p>
                    </div>
                </div>
                <span class="pf-badge {{ $status === 'rejected' ? 'pf-badge-red' : '' }}" style="font-size:.8rem;padding:.3rem .8rem;">{{ $label }}</span>
            </div>

            @if ($status === 'pending')
                <div class="mt-5 rounded-xl p-4 text-sm pf-text" style="background:#fff5f3;border:1px solid #fdd5c8;">
                    <strong>Your application is under review.</strong>
                    Our team usually reviews applications within 1–3 business days, and we'll email
                    <strong>{{ $user->email }}</strong> once it's done.
                    Until then you can't add products or receive orders.
                </div>
            @elseif ($status === 'rejected')
                <div class="mt-5 rounded-xl p-4 text-sm" style="background:#FEF2F2;border:1px solid rgba(220,38,38,.2);color:#991B1B;">
                    <strong>Your application was not approved.</strong>
                    @if ($application?->rejection_reason)
                        <p class="mt-1"><span class="font-semibold">Reason:</span> {{ $application->rejection_reason }}</p>
                    @endif
                    <p class="mt-1">The seller dashboard isn't available. Upload corrected documents below to send your application for review again.</p>
                </div>
            @endif
        </div>

        {{-- Summary --}}
        @if ($application)
            <div class="pf-card p-6 sm:p-7">
                <h2 class="pf-card-title mb-4">Application Details</h2>
                <dl class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'Shop Category' => $application->line_of_business,
                        'Seller Type'   => $application->seller_type,
                        'TIN'           => $application->tin,
                        'Mobile Number' => $application->phone,
                        'Shop Address'  => $application->address,
                        'Submitted'     => $application->created_at?->format('M d, Y'),
                    ] as $k => $v)
                        <div class="rounded-xl p-3.5" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide pf-muted">{{ $k }}</dt>
                            <dd class="mt-1 break-words text-sm font-semibold" style="color:#222;">{{ $v ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        {{-- Resubmit after rejection --}}
        @if ($status === 'rejected' && $application)
            <form action="{{ route('seller.status.resubmit') }}" method="POST" enctype="multipart/form-data" class="pf-card p-6 sm:p-7">
                @csrf
                <h2 class="pf-card-title">Upload Corrected Documents</h2>
                <p class="mt-1 mb-4 text-xs pf-muted">Upload only the documents you need to replace. JPG, PNG or PDF · max 5 MB each.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        'government_id'   => 'Government-issued ID',
                        ($application->seller_type === 'Corporation' ? 'sec_certificate' : 'dti_certificate')
                                          => ($application->seller_type === 'Corporation' ? 'SEC Certificate' : 'DTI Certificate'),
                        'bir_2303'        => 'BIR Form 2303',
                    ] as $field => $docLabel)
                        <div>
                            <label class="pf-label" for="{{ $field }}">{{ $docLabel }}</label>
                            <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,.pdf" class="pf-input py-2">
                            @error($field)<p class="mt-1 text-xs" style="color:#DC2626;">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 border-t pt-5" style="border-color:#F0E4DC;">
                    <button type="submit" class="pf-btn">Send for Review Again</button>
                </div>
            </form>
        @endif
    </div>
</x-auth-shell>
