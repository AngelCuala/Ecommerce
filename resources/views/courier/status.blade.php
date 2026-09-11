<x-layout title="Application Status — ALVY">
<div class="mx-auto max-w-xl px-4 py-16 sm:px-6 lg:px-8">

    <div class="rounded-2xl p-8 text-center" style="background:#fff;border:1px solid #cfdce8;">

        @if (!$courier)
            {{-- No application found --}}
            <span class="text-5xl">📋</span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#222;">No Application Found</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">You haven't submitted a sorting center registration yet.</p>
            <a href="{{ route('courier.register') }}"
               class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white"
               style="background:#002b4d;">Register Now</a>

        @elseif ($courier->isPending())
            <span class="text-5xl">⏳</span>
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
            <a href="{{ route('home') }}"
               class="mt-6 inline-flex rounded-xl border px-6 py-3 text-sm font-semibold transition"
               style="border-color:#cfdce8;color:#374151;"
               onmouseover="this.style.background='#F9FAFB';" onmouseout="this.style.background='';">
                ← Back to Home
            </a>

        @elseif ($courier->isApproved())
            <span class="text-5xl">✅</span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#059669;">Application Approved!</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">
                Welcome to the ALVY Logistics network. You can now access the Sorting Center dashboard.
            </p>
            <a href="{{ route('courier.dashboard') }}"
               class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white"
               style="background:#002b4d;">Go to Dashboard</a>

        @elseif ($courier->isRejected())
            <span class="text-5xl">❌</span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#DC2626;">Application Rejected</p>
            @if ($courier->rejection_reason)
                <p class="mt-2 text-sm" style="color:#6B7280;">
                    Reason: {{ $courier->rejection_reason }}
                </p>
            @endif
            <p class="mt-2 text-sm" style="color:#6B7280;">
                You may re-apply with updated information.
            </p>
            <a href="{{ route('courier.register') }}"
               class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white"
               style="background:#002b4d;">Re-apply</a>

        @else
            {{-- Suspended --}}
            <span class="text-5xl">🚫</span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#DC2626;">Account Suspended</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">
                Your sorting center account has been suspended. Please contact support.
            </p>
        @endif

        @if (session('success'))
            <div class="mt-6 rounded-xl border p-3 text-sm"
                 style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#fa4e1c;">
                ✓ {{ session('success') }}
            </div>
        @endif

    </div>
</div>
</x-layout>
