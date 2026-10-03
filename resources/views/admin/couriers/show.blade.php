<x-admin-layout title="Courier Application" active="couriers">

<div class="mx-auto max-w-4xl">

    <a href="{{ route('admin.couriers.index') }}"
       class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#fa4e1c;">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        Back to applications
    </a>

    @php
        $sc = match ($courier->status) {
            'approved'  => ['bg'=>'#ECFDF5','text'=>'#059669','label'=>'Approved'],
            'rejected'  => ['bg'=>'#FEF2F2','text'=>'#DC2626','label'=>'Rejected'],
            'suspended' => ['bg'=>'#F3F4F6','text'=>'#6B7280','label'=>'Suspended'],
            default     => ['bg'=>'#FFFBEB','text'=>'#B45309','label'=>'Pending'],
        };
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">

        {{-- Details --}}
        <div class="space-y-6">
            <div class="card p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-lg font-bold" style="color:#222222;">{{ $courier->fullName() }}</h2>
                    <span class="rounded-full px-3 py-1 text-xs font-bold" style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">{{ $sc['label'] }}</span>
                </div>
                <dl class="mt-4 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs" style="color:#9db3c4;">Email</dt><dd class="font-medium" style="color:#222;">{{ $courier->user->email ?? '—' }}</dd></div>
                    <div><dt class="text-xs" style="color:#9db3c4;">Contact No.</dt><dd class="font-medium" style="color:#222;">{{ $courier->contact_no }}</dd></div>
                    <div><dt class="text-xs" style="color:#9db3c4;">Sex</dt><dd class="font-medium" style="color:#222;">{{ $courier->sex }}</dd></div>
                    <div><dt class="text-xs" style="color:#9db3c4;">Birthday / Age</dt><dd class="font-medium" style="color:#222;">{{ optional($courier->birthday)->format('M d, Y') }} · {{ $courier->age }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs" style="color:#9db3c4;">Address</dt><dd class="font-medium" style="color:#222;">{{ $courier->fullAddress() }}</dd></div>
                    <div><dt class="text-xs" style="color:#9db3c4;">Vehicle Type</dt><dd class="font-medium" style="color:#222;">{{ $courier->vehicle_type }}</dd></div>
                    <div><dt class="text-xs" style="color:#9db3c4;">Plate Number</dt><dd class="font-medium" style="color:#222;">{{ $courier->plate_number }}</dd></div>
                    <div><dt class="text-xs" style="color:#9db3c4;">Submitted</dt><dd class="font-medium" style="color:#222;">{{ optional($courier->submitted_at ?? $courier->created_at)->format('M d, Y g:i A') }}</dd></div>
                    @if ($courier->reviewed_at)
                        <div><dt class="text-xs" style="color:#9db3c4;">Reviewed</dt><dd class="font-medium" style="color:#222;">{{ $courier->reviewed_at->format('M d, Y g:i A') }}</dd></div>
                    @endif
                </dl>
                @if ($courier->isRejected() && $courier->rejection_reason)
                    <div class="mt-4 rounded-lg border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                        <strong>Rejection reason:</strong> {{ $courier->rejection_reason }}
                    </div>
                @endif
            </div>

            {{-- Documents --}}
            <div class="card p-6">
                <h3 class="font-display text-base font-bold mb-4" style="color:#222222;">Documents</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([['OR / CR', $courier->or_cr_path], ["Valid ID / Driver's License", $courier->id_license_path]] as [$label, $path])
                        <div>
                            <p class="text-xs font-semibold mb-2" style="color:#6b90aa;">{{ $label }}</p>
                            @if ($path)
                                @php $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); @endphp
                                @if (in_array($ext, ['jpg','jpeg','png']))
                                    <a href="{{ asset('storage/'.$path) }}" target="_blank">
                                        <img src="{{ asset('storage/'.$path) }}" class="max-h-40 w-full rounded-lg border object-contain transition hover:opacity-90" style="border-color:#cfdce8;">
                                    </a>
                                @else
                                    <a href="{{ asset('storage/'.$path) }}" target="_blank"
                                       class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium" style="border-color:#cfdce8;color:#fa4e1c;">
                                        View document
                                    </a>
                                @endif
                            @else
                                <p class="text-sm" style="color:#9db3c4;">Not uploaded.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="space-y-4">
            @if ($courier->isPending())
                <div class="card p-6">
                    <h3 class="text-sm font-bold mb-3" style="color:#222222;">Review</h3>

                    <form action="{{ route('admin.couriers.approve', $courier->id) }}" method="POST" class="mb-4"
                          onsubmit="return confirm('Approve {{ addslashes($courier->fullName()) }} as a courier?')">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-xl py-2.5 text-sm font-bold text-white transition hover:opacity-90" style="background:#059669;">
                            Approve Courier
                        </button>
                    </form>

                    <form action="{{ route('admin.couriers.reject', $courier->id) }}" method="POST" class="space-y-2"
                          onsubmit="return confirm('Reject this application?')">
                        @csrf @method('PATCH')
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Rejection reason (optional)</label>
                        <textarea name="rejection_reason" rows="3" maxlength="500" class="input" placeholder="e.g. Blurry OR/CR, expired ID…"></textarea>
                        <button class="w-full rounded-xl border-2 py-2.5 text-sm font-semibold transition"
                                style="border-color:#DC2626;color:#DC2626;"
                                onmouseover="this.style.background='#FEF2F2';" onmouseout="this.style.background='';">
                            Reject Application
                        </button>
                    </form>
                </div>
            @elseif ($courier->isApproved())
                <div class="card p-6">
                    <h3 class="text-sm font-bold mb-3" style="color:#222222;">Manage</h3>
                    <p class="text-xs mb-3" style="color:#6b90aa;">Suspend this courier to block portal access and deliveries.</p>
                    <form action="{{ route('admin.couriers.suspend', $courier->id) }}" method="POST"
                          onsubmit="return confirm('Suspend this courier?')">
                        @csrf @method('PATCH')
                        <button class="w-full rounded-xl border-2 py-2.5 text-sm font-semibold transition"
                                style="border-color:#DC2626;color:#DC2626;"
                                onmouseover="this.style.background='#FEF2F2';" onmouseout="this.style.background='';">
                            Suspend Courier
                        </button>
                    </form>
                </div>
            @else
                <div class="card p-6 text-sm" style="color:#6b90aa;">
                    This application is <strong style="color:{{ $sc['text'] }};">{{ $sc['label'] }}</strong>. No further action available.
                </div>
            @endif

            <div class="card p-6">
                <h3 class="text-sm font-bold mb-2" style="color:#222222;">Deliveries</h3>
                <p class="text-2xl font-extrabold" style="color:#fa4e1c;">{{ $courier->deliveries->count() }}</p>
                <p class="text-xs" style="color:#6b90aa;">total assigned · {{ $courier->completedDeliveries() }} completed</p>
                <p class="mt-3 text-sm" style="color:#222;">Total earnings: <strong>₱{{ number_format($courier->total_earnings, 2) }}</strong></p>
            </div>
        </div>

    </div>
</div>

</x-admin-layout>
