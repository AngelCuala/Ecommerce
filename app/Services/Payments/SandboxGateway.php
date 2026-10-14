<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * TEST gateway built into ALVY: a page with "Pay", "Decline" and "Cancel" buttons.
 * No money moves. Only active when PAYMENT_GATEWAY=sandbox.
 */
class SandboxGateway implements PaymentGateway
{
    public function start(Payment $payment, Collection $orders): void
    {
        $payment->update([
            'provider_ref' => 'SBX-' . strtoupper(Str::random(12)),
            'checkout_url' => route('payments.sandbox', $payment),
        ]);
    }

    /** The sandbox page writes the result on the payment itself. */
    public function fetch(Payment $payment): array
    {
        return ['status' => $payment->status, 'payment_id' => $payment->provider_payment_id, 'reason' => $payment->failure_reason];
    }

    public function refund(Payment $payment, float $amount, string $reason): bool
    {
        return true;
    }
}
