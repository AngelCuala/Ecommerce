<x-admin-layout title="Courier Application" active="couriers">

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">
        ✓ {{ session('success') }}
    </div>
@endif

<div class="mb-5">
    <a href="{{ route('admin.couriers.index') }}" class="text-sm" style="color:#fa4e1c;">← All Applications</a>
</div>

<div class="grid gap-6 lg:grid-cols-[1fr_300px]">

    {{-- Details --}}
    <div class="space-y-5">
        <div class="card p-6">
            <h2 class="font-display text-lg font-bold mb-4" style="color:#222222;">Personal Information</h2>
            <dl class="grid gap-y-2 text-sm">
                @foreach ([
                    'Full Name'    => $courier->fullName(),
                    'Sex'          => $courier->sex,
                    'Birthday'     => $courier->birthday->format('M d, Y'),
                    'Age'          => $courier->age,
                    'Contact No.'  => $courier->contact_no,
                    'Email'        => $courier->user->email,
                    'Address'      => $courier->street.', '.$courier->barangay.', '.$courier->municipality.', '.$courier->province,
                    'Vehicle'      => $courier->vehicle_type.' · '.$courier->plate_number,
                    'Applied On'   => $courier->created_at->format('M d, Y H:i'),
                ] as $label => $val)
                    <div class="flex justify-between border-b py-2" style="border-color:#dce8f0;">
                        <dt style="color:#6b90aa;">{{ $label }}</dt>
                        <dd class="font-medium text-right" style="color:#222222;">{{ $val }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Documents --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Documents</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold mb-2" style="color:#6b90aa;">OR/CR</p>
                    @php $orExt = pathinfo($courier->or_cr_path, PATHINFO_EXTENSION); @endphp
                    @if (in_array(strtolower($orExt), ['jpg','jpeg','png']))
                        <a href="{{ asset('storage/'.$courier->or_cr_path) }}" target="_blank">
                            <img src="{{ asset('storage/'.$courier->or_cr_path) }}" class="max-h-48 rounded-xl border object-contain" style="border-color:#dce8f0;">
                        </a>
                    @else
                        <a href="{{ asset('storage/'.$courier->or_cr_path) }}" target="_blank" class="btn-outline text-sm inline-flex" style="border-color:#fa4e1c;color:#fa4e1c;">📄 View OR/CR</a>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold mb-2" style="color:#6b90aa;">ID / Driver's License</p>
                    @php $idExt = pathinfo($courier->id_license_path, PATHINFO_EXTENSION); @endphp
                    @if (in_array(strtolower($idExt), ['jpg','jpeg','png']))
                        <a href="{{ asset('storage/'.$courier->id_license_path) }}" target="_blank">
                            <img src="{{ asset('storage/'.$courier->id_license_path) }}" class="max-h-48 rounded-xl border object-contain" style="border-color:#dce8f0;">
                        </a>
                    @else
                        <a href="{{ asset('storage/'.$courier->id_license_path) }}" target="_blank" class="btn-outline text-sm inline-flex" style="border-color:#fa4e1c;color:#fa4e1c;">📄 View ID</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="space-y-4">
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-2" style="color:#222222;">Status</h2>
            @php
                $sc = match($courier->status) {
                    'approved'  => ['bg'=>'#ECFDF5','text'=>'#059669'],
                    'rejected'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                    'suspended' => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
                    default     => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'],
                };
            @endphp
            <span class="inline-block rounded-full px-3 py-1 text-sm font-semibold"
                  style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                {{ ucfirst($courier->status) }}
            </span>

            @if ($courier->rejection_reason)
                <p class="mt-3 text-xs p-3 rounded-xl" style="background:#FEF2F2;color:#B91C1C;">
                    <strong>Reason:</strong> {{ $courier->rejection_reason }}
                </p>
            @endif
        </div>

        @if ($courier->isPending())
            <div class="card p-5">
                <h3 class="font-semibold text-sm mb-3" style="color:#222222;">Approve Application</h3>
                <form action="{{ route('admin.couriers.approve', $courier->id) }}" method="POST">
                    @csrf @method('PATCH')
                    <button class="btn-gold w-full" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">✓ Approve Courier</button>
                </form>
            </div>
            <div class="card p-5">
                <h3 class="font-semibold text-sm mb-3" style="color:#DC2626;">Reject Application</h3>
                <form action="{{ route('admin.couriers.reject', $courier->id) }}" method="POST" class="space-y-3">
                    @csrf @method('PATCH')
                    <textarea name="rejection_reason" rows="3" class="input text-sm"
                              placeholder="Reason for rejection (optional)"></textarea>
                    <button class="w-full rounded-full border-2 py-2 text-sm font-semibold"
                            style="border-color:#DC2626;color:#DC2626;"
                            onclick="return confirm('Reject this application?')">✕ Reject</button>
                </form>
            </div>
        @elseif ($courier->isApproved())
            <div class="card p-5">
                <p class="text-xs mb-3" style="color:#555555;">Suspend this courier to prevent them from accepting deliveries.</p>
                <form action="{{ route('admin.couriers.suspend', $courier->id) }}" method="POST"
                      onsubmit="return confirm('Suspend this courier?')">
                    @csrf @method('PATCH')
                    <button class="w-full rounded-full border-2 py-2 text-sm font-semibold"
                            style="border-color:#DC2626;color:#DC2626;">Suspend</button>
                </form>
            </div>
        @endif

        {{-- Delivery stats --}}
        @if ($courier->isApproved())
            <div class="card p-5">
                <h3 class="font-semibold text-sm mb-2" style="color:#222222;">Delivery Stats</h3>
                <p class="text-sm" style="color:#555555;">Completed: <strong>{{ $courier->completedDeliveries() }}</strong></p>
                <p class="text-sm" style="color:#555555;">Total Earned: <strong style="color:#059669;">₱{{ number_format($courier->total_earnings, 2) }}</strong></p>
            </div>
        @endif
    </div>
</div>

</x-admin-layout>