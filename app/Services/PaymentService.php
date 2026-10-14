<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\SandboxGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Online payments for a checkout (all its store orders are paid together):
 * start → the buyer pays at the gateway → we check the result (on return, on "Pay again",
 * and before an unpaid order is auto-cancelled), so a payment is never taken twice.
 */
class PaymentService
{
    /** "sandbox" | "paymongo" | null (online payment off). */
    public function gatewayName(): ?string
    {
        return match (config('payments.gateway')) {
            'sandbox'  => 'sandbox',
            'paymongo' => config('payments.paymongo.secret_key') ? 'paymongo' : null,
            default    => null,
        };
    }

    public function enabled(): bool
    {
        return $this->gatewayName() !== null;
    }

    /** [code => label] of online methods offered at checkout (empty when off). */
    public function methods(): array
    {
        return $this->enabled() ? config('payments.methods') : [];
    }

    private function gateway(string $provider): PaymentGateway
    {
        return $provider === 'paymongo' ? app(PayMongoGateway::class) : app(SandboxGateway::class);
    }

    /** Open a gateway checkout for the checkout's unpaid orders. */
    public function start(User $user, string $checkoutGroup, string $method): Payment
    {
        $orders = Order::where('checkout_group', $checkoutGroup)->where('user_id', $user->id)
            ->where('status', Order::AWAITING_PAYMENT)->get();

        $payment = Payment::create([
            'user_id' => $user->id, 'checkout_group' => $checkoutGroup, 'method' => $method,
            'provider' => $this->gatewayName() ?? 'sandbox', 'amount' => round($orders->sum('total_price'), 2), 'status' => 'pending',
        ]);
        $this->gateway($payment->provider)->start($payment, $orders);

        return $payment->fresh();
    }

    /** Ask the gateway what happened to a pending payment and record it. */
    public function sync(Payment $payment): Payment
    {
        if (! $payment->isPending()) {
            return $payment;
        }
        try {
            $result = $this->gateway($payment->provider)->fetch($payment);
        } catch (\Throwable $e) {
            Log::warning('Could not check payment ' . $payment->id . ': ' . $e->getMessage());
            return $payment;
        }

        match ($result['status']) {
            'paid'      => $this->markPaid($payment, $result['payment_id']),
            'failed'    => $this->markFailed($payment, $result['reason'] ?? 'The payment was declined.'),
            'cancelled' => $payment->update(['status' => 'cancelled']),
            default     => null,
        };

        return $payment->fresh();
    }

    /** The gateway took the money: the orders move to To Ship (the sellers are told now). */
    public function markPaid(Payment $payment, ?string $providerPaymentId = null): void
    {
        $late = DB::transaction(function () use ($payment, $providerPaymentId) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($locked->isPaid()) {
                return collect();
            }
            $locked->update(['status' => 'paid', 'paid_at' => now(), 'provider_payment_id' => $providerPaymentId ?? $locked->provider_payment_id]);

            $late = collect();
            foreach (Order::where('checkout_group', $locked->checkout_group)->lockForUpdate()->get() as $order) {
                if ($order->status === Order::AWAITING_PAYMENT) {
                    $order->update(['status' => Order::PENDING, 'payment_status' => 'Paid']);
                } elseif ($order->status === Order::CANCELLED && $order->payment_status !== 'Paid') {
                    $late->push($order);   // paid after it was cancelled for not paying in time
                }
            }
            return $late;
        });

        // Money that arrived for an already-cancelled order goes straight back.
        foreach ($late as $order) {
            $order->update(['payment_status' => 'Paid']);
            $this->refund($order->fresh(), 'Paid after the order was cancelled for not being paid in time.');
        }
    }

    /** Declined: the orders stay in To Pay with "Pay again". */
    public function markFailed(Payment $payment, string $reason): void
    {
        $payment->update(['status' => 'failed', 'failure_reason' => $reason]);
        Order::where('checkout_group', $payment->checkout_group)->where('status', Order::AWAITING_PAYMENT)
            ->update(['payment_status' => 'Failed']);
    }

    /** Refund an order paid online (cancelled before pickup, or return approved). */
    public function refund(Order $order, string $reason = 'Order cancelled'): void
    {
        $amount = round((float) $order->total_price - (float) $order->refunded_amount, 2);
        $payment = Payment::where('checkout_group', $order->checkout_group)->where('status', 'paid')->latest('id')->first();
        if ($amount <= 0 || ! $payment) {
            return;
        }

        $ok = $this->gateway($payment->provider)->refund($payment, $amount, $reason . ' (' . $order->number() . ')');
        $order->forceFill([
            'refunded_amount' => $ok ? (float) $order->refunded_amount + $amount : $order->refunded_amount,
            'payment_status'  => $ok ? 'Refunded' : 'Refund pending',
        ])->saveQuietly();
    }

    /** Unpaid online orders past their time: check the gateway once more, then cancel them. */
    public function cancelOverdue(): int
    {
        $count = 0;
        Order::where('status', Order::AWAITING_PAYMENT)->whereNotNull('pay_by')->where('pay_by', '<=', now())
            ->get()->groupBy('checkout_group')->each(function ($orders, $group) use (&$count) {
                foreach (Payment::where('checkout_group', $group)->where('status', 'pending')->get() as $payment) {
                    $this->sync($payment);
                }
                foreach ($orders as $order) {
                    $order->refresh();
                    if ($order->status === Order::AWAITING_PAYMENT) {
                        $order->update(['status' => Order::CANCELLED, 'cancellation_reason' => 'Not paid in time (unpaid)']);
                        $count++;
                    }
                }
                Payment::where('checkout_group', $group)->where('status', 'pending')->update(['status' => 'expired']);
            });

        return $count;
    }
}
