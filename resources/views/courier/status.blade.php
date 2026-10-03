<x-layout title="Application Status — ALVY">
<div class="mx-auto max-w-xl px-4 py-16 sm:px-6 lg:px-8">

    <div class="rounded-2xl p-8 text-center" style="background:#fff;border:1px solid #cfdce8;">

        @if (!$courier)
            {{-- No application found --}}
            <span style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:999px;background:#f0f6fa;color:#6b90aa;">
                @include('courier.partials.icon', ['name' => 'list', 'size' => 30])
            </span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#222;">No Application Found</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">You haven't submitted a sorting center registration yet.</p>
            <a href="{{ route('courier.register') }}" class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white" style="background:#002b4d;">Register Now</a>

        @elseif ($courier->isPending())
            <span style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:999px;background:#fffbeb;color:#b45309;">
                @include('courier.partials.icon', ['name' => 'clock', 'size' => 30])
            </span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#222;">Application Under Review</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">
                Submitted {{ $courier->created_at->format('M d, Y') }}.
                We'll notify you at <strong>{{ auth()->user()->email }}</strong> once reviewed.
            </p>
            <div class="mt-6 rounded-xl border p-4 text-left text-sm" style="border-color:#cfdce8;">
                <p class="font-semibold mb-2" style="color:#374151;">Your Details</p>
                <div class="space-y-1 text-xs" style="color:#6B7280;">
                    <p><span class="font-medium" style="color:#374151;">Name:</span>
                       {{ $courier->first_name }} {{ $courier->middle_initial }} {{ $courier->last_name }}</p>
                    <p><span class="font-medium" style="color:#374151;">Contact:</span> {{ $courier->contact_no }}</p>
                    <p><span class="font-medium" style="color:#374151;">Address:</span>
                       {{ implode(', ', array_filter([$courier->street, $courier->barangay, $courier->municipality, $courier->province])) }}</p>
                    @if ($courier->business_name)
                        <p><span class="font-medium" style="color:#374151;">Business:</span> {{ $courier->business_name }}</p>
                    @endif
                </div>
            </div>
            <a href="{{ route('home') }}" class="mt-6 inline-flex items-center gap-1.5 rounded-xl border px-6 py-3 text-sm font-semibold" style="border-color:#cfdce8;color:#374151;">
                @include('courier.partials.icon', ['name' => 'back', 'size' => 15, 'sw' => 2.2]) Back to Home
            </a>

        @elseif ($courier->isApproved())
            <span style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:999px;background:#ecfdf5;color:#059669;">
                @include('courier.partials.icon', ['name' => 'check', 'size' => 30])
            </span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#059669;">Application Approved!</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">
                Welcome to the ALVY Logistics network. You can now access the Sorting Center dashboard.
            </p>
            <a href="{{ route('courier.dashboard') }}" class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white" style="background:#002b4d;">Go to Dashboard</a>

        @elseif ($courier->isRejected())
            <span style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:999px;background:#fef2f2;color:#dc2626;">
                @include('courier.partials.icon', ['name' => 'cross', 'size' => 30])
            </span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#DC2626;">Application Rejected</p>
            @if ($courier->rejection_reason)
                <p class="mt-2 text-sm" style="color:#6B7280;">Reason: {{ $courier->rejection_reason }}</p>
            @endif
            <p class="mt-2 text-sm" style="color:#6B7280;">You may re-apply with updated information.</p>
            <a href="{{ route('courier.register') }}" class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white" style="background:#002b4d;">Re-apply</a>

        @else
            {{-- Suspended --}}
            <span style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:999px;background:#f3f4f6;color:#6b7280;">
                @include('courier.partials.icon', ['name' => 'ban', 'size' => 30])
            </span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#DC2626;">Account Suspended</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">Your sorting center account has been suspended. Please contact support.</p>
        @endif

        @if (session('success'))
            <div class="mt-6 flex items-center justify-center gap-2 rounded-xl border p-3 text-sm"
                 style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#fa4e1c;">
                @include('courier.partials.icon', ['name' => 'check-plain', 'size' => 15, 'sw' => 2.4])
                {{ session('success') }}
            </div>
        @endif

    </div>
</div>
</x-layout>
