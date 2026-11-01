<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Collection;

/** A payment provider (PayMongo, or the built-in test sandbox). */
interface PaymentGateway
{
    /** Open a checkout for the payment; fills provider_ref and checkout_url. */
    public function start(Payment $payment, Collection $orders): void;

    /**
     * Ask the provider what happened.
     *
     * @return array{status: string, payment_id: ?string, reason: ?string} status: pending | paid | failed | cancelled
     */
    public function fetch(Payment $payment): array;

    /** Send money back. True when the provider accepted the refund. */
    public function refund(Payment $payment, float $amount, string $reason): bool;
}
