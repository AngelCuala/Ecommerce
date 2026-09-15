<x-admin-layout title="Seller Application" active="seller-applications">

@if (session('success'))
    <div class="mb-6 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

<div class="grid gap-6 lg:grid-cols-[1fr_320px]">

    {{-- Application details --}}
    <div class="space-y-6">
        <div class="card p-6">
            <h2 class="font-display text-lg font-bold" style="color:#222222;">Applicant Details</h2>
            <dl class="mt-4 divide-y text-sm" style="--tw-divide-opacity:1;">
                @foreach ([
                    'Full Name'    => $application->full_name,
                    'Shop Name'    => $application->shop_name,
                    'Phone'        => $application->phone,
                    'Address'      => $application->address,
                    'Email'        => $application->user->email,
                    'Applied On'   => $application->created_at->format('M d, Y H:i'),
                ] as $label => $value)
                    <div class="flex justify-between py-2.5" style="border-color:#dce8f0;">
                        <dt style="color:#6b90aa;">{{ $label }}</dt>
                        <dd class="font-medium text-right" style="color:#222222;">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-3" style="color:#222222;">About the store</h2>
            <p class="text-sm leading-relaxed" style="color:#555555;">{{ $application->description }}</p>
        </div>

        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-3" style="color:#222222;">Government ID</h2>
            @php $ext = strtolower(pathinfo($application->government_id_path, PATHINFO_EXTENSION)); @endphp
            @if ($application->government_id_path)
                @if (in_array($ext, ['jpg','jpeg','png','webp']))
                    {{-- Thumbnail — click to open lightbox --}}
                    <img src="{{ asset('storage/'.$application->government_id_path) }}"
                         alt="Government ID"
                         class="max-h-48 w-full rounded-xl object-contain border cursor-pointer transition hover:opacity-90"
                         style="border-color:#dce8f0;"
                         onclick="document.getElementById('id-lightbox').classList.remove('hidden')">
                    <p class="mt-2 text-xs text-center" style="color:#6b90aa;">Click image to view full size</p>

                    {{-- Lightbox modal --}}
                    <div id="id-lightbox"
                         class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
                         style="background:rgba(0,0,0,.80);"
                         onclick="this.classList.add('hidden')">
                        <div class="relative max-w-3xl w-full" onclick="event.stopPropagation()">
                            <button onclick="document.getElementById('id-lightbox').classList.add('hidden')"
                                    class="absolute -top-10 right-0 text-white text-2xl font-bold leading-none"
                                    style="opacity:.8;">&times;</button>
                            <img src="{{ asset('storage/'.$application->government_id_path) }}"
                                 alt="Government ID"
                                 class="w-full rounded-xl object-contain"
                                 style="max-height:80vh;">
                            <a href="{{ asset('storage/'.$application->government_id_path) }}"
                               target="_blank"
                               class="mt-3 flex items-center justify-center gap-2 rounded-lg py-2 text-sm font-semibold text-white"
                               style="background:rgba(255,255,255,.15);">
                                ↗ Open in new tab
                            </a>
                        </div>
                    </div>
                @else
                    <a href="{{ asset('storage/'.$application->government_id_path) }}" target="_blank"
                       class="btn-outline inline-flex items-center gap-2 text-sm" style="border-color:#fa4e1c;color:#fa4e1c;">
                        📄 View / Download ID Document
                    </a>
                @endif
            @else
                <p class="text-sm" style="color:#6b90aa;">No ID uploaded.</p>
            @endif
        </div>
    </div>

    {{-- Actions --}}
    <div class="space-y-4">
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-1" style="color:#222222;">Status</h2>
            @php
                $sc = match($application->status) {
                    'approved' => ['bg'=>'#ECFDF5','color'=>'#059669'],
                    'rejected' => ['bg'=>'#FEF2F2','color'=>'#DC2626'],
                    default    => ['bg'=>'rgba(250,78,28,.12)','color'=>'#fa4e1c'],
                };
            @endphp
            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold"
                  style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};">
                {{ ucfirst($application->status) }}
            </span>

            @if ($application->isRejected() && $application->rejection_reason)
                <p class="mt-3 text-sm" style="color:#DC2626;">
                    <strong>Reason:</strong> {{ $application->rejection_reason }}
                </p>
            @endif
        </div>

        @if ($application->isPending())
        {{-- Approve --}}
        <div class="card p-6">
            <h3 class="font-semibold mb-3" style="color:#222222;">Approve Application</h3>
            <p class="text-xs mb-4" style="color:#555555;">This will upgrade the user's role to Seller and give them access to the Seller Dashboard.</p>
            <form action="{{ route('admin.seller-applications.approve', $application->id) }}" method="POST">
                @csrf @method('PATCH')
                <button type="submit" class="btn-gold w-full" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;"
                        onclick="return confirm('Approve this seller application?')">
                    ✓ Approve
                </button>
            </form>
        </div>

        {{-- Reject --}}
        <div class="card p-6">
            <h3 class="font-semibold mb-3" style="color:#DC2626;">Reject Application</h3>
            <form action="{{ route('admin.seller-applications.reject', $application->id) }}" method="POST" class="space-y-3">
                @csrf @method('PATCH')
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Rejection Reason (optional)</label>
                    <textarea name="rejection_reason" rows="3" class="input mt-1"
                              placeholder="e.g. Invalid ID, incomplete info…"></textarea>
                </div>
                <button type="submit" class="w-full rounded-full border-2 py-2.5 text-sm font-semibold transition"
                        style="border-color:#DC2626;color:#DC2626;"
                        onmouseover="this.style.background='#FEF2F2';"
                        onmouseout="this.style.background='';"
                        onclick="return confirm('Reject this application?')">
                    ✕ Reject
                </button>
            </form>
        </div>
        @endif

        <a href="{{ route('admin.seller-applications.index') }}"
           class="block text-center text-sm transition" style="color:#fa4e1c;"
           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
            ← Back to all applications
        </a>
    </div>

</div>

</x-admin-layout>