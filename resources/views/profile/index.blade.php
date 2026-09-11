<x-layout title="My Profile — ALVY">
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    {{-- Page title --}}
    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1 space-y-5">

            {{-- Seller application status banner --}}
            @php $app = auth()->user()->sellerApplication; @endphp
            @if ($app)
                @if ($app->isPending())
                    <div class="rounded-2xl border p-4 text-sm" style="background:rgba(250,78,28,.06);border-color:rgba(250,78,28,.25);">
                        <strong style="color:#fa4e1c;">⏳ Seller application under review</strong>
                        <span style="color:#1a4d6e;"> — submitted {{ $app->created_at->diffForHumans() }}. We'll email you once it's reviewed.</span>
                    </div>
                @elseif ($app->isApproved())
                    <div class="rounded-2xl border p-4 text-sm" style="background:#ECFDF5;border-color:rgba(5,150,105,.25);">
                        <strong style="color:#059669;">✅ Your seller account is approved!</strong>
                        <a href="{{ route('seller.dashboard') }}" style="color:#059669;" class="ml-2 underline">Go to Seller Dashboard →</a>
                    </div>
                @elseif ($app->isRejected())
                    <div class="rounded-2xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);">
                        <strong style="color:#DC2626;">❌ Seller application rejected.</strong>
                        @if ($app->rejection_reason)
                            <span style="color:#d93d0e;"> Reason: {{ $app->rejection_reason }}</span>
                        @endif
                    </div>
                @endif
            @endif

            {{-- Become a Seller CTA --}}
            @if (auth()->user()->isBuyer())
                @php $app = auth()->user()->sellerApplication; @endphp
                @if (! $app || $app->isRejected())
                    <div class="flex items-center justify-between gap-4 rounded-2xl border p-5"
                         style="background:#FFF8F3;border-color:rgba(250,78,28,.25);">
                        <div>
                            <p class="font-display font-bold" style="color:#002b4d;">🏪 Want to sell on ALVY?</p>
                            <p class="mt-1 text-sm" style="color:#1a4d6e;">Apply to become a seller and start listing your books today.</p>
                        </div>
                        <a href="{{ route('seller.apply') }}"
                           class="shrink-0 rounded-full px-5 py-2.5 text-sm font-semibold transition"
                           style="background:#fa4e1c;color:#fff;"
                           onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                            Apply Now
                        </a>
                    </div>
                @endif
            @elseif (auth()->user()->isSeller())
                <div class="flex items-center justify-between gap-4 rounded-2xl border p-5"
                     style="background:#FFF8F3;border-color:rgba(250,78,28,.25);">
                    <div>
                        <p class="font-display font-bold" style="color:#002b4d;">🏪 Seller Dashboard</p>
                        <p class="mt-1 text-sm" style="color:#1a4d6e;">Manage your books, orders, and reports.</p>
                    </div>
                    <a href="{{ route('seller.dashboard') }}"
                       class="shrink-0 rounded-full px-5 py-2.5 text-sm font-semibold transition"
                       style="background:#fa4e1c;color:#fff;"
                       onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                        Go to Dashboard
                    </a>
                </div>
            @endif

        </div>
    </div>
</div>
</x-layout>
