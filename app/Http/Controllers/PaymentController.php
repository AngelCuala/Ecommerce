<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/** Paying online for a checkout: Pay now / Pay again, coming back from the gateway, and the test sandbox page. */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** "Pay now" / "Pay again" for an order in To Pay (pays the whole checkout it came from). */
    public function pay(int $orderId)
    {
        $order = Order::where('user_id', auth()->id())->findOrFail($orderId);

        // A payment the buyer may already have completed (e.g. closed the browser on the gateway page).
        foreach (Payment::where('checkout_group', $order->checkout_group)->where('status', 'pending')->get() as $open) {
            $this->payments->sync($open);
        }
        $order->refresh();

        if ($order->status !== Order::AWAITING_PAYMENT) {
            return redirect()->route('profile.orders.show', $order->id)->with(
                $order->status === Order::CANCELLED ? 'error' : 'success',
                $order->status === Order::CANCELLED ? 'This order was cancelled and can no longer be paid.' : 'This order is already paid.'
            );
        }
        if (! $order->canBePaid()) {
            return redirect()->route('profile.orders.show', $order->id)->with('error', 'The time to pay for this order has passed.');
        }
        if (! $this->payments->enabled()) {
            return redirect()->route('profile.orders.show', $order->id)->with('error', 'Online payment is not available right now. Please try again later.');
        }

        // Re-use a still-open checkout instead of starting a second one.
        $open = Payment::where('checkout_group', $order->checkout_group)->where('status', 'pending')->latest('id')->first();
        try {
            $payment = $open ?? $this->payments->start(auth()->user(), $order->checkout_group, strtolower($order->payment_method));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('profile.orders.show', $order->id)->with('error', 'We could not open the payment page. Please try again.');
        }

        return redirect()->away($payment->checkout_url);
    }

    /** Back from the gateway (paid, or the buyer pressed Cancel / Back). */
    public function back(Request $request, Payment $payment)
    {
        abort_unless((int) $payment->user_id === (int) auth()->id(), 404);

        $payment = $this->payments->sync($payment);
        if ($request->boolean('cancelled') && $payment->isPending()) {
            $payment->update(['status' => 'cancelled']);
        }

        $order = $payment->orders()->orderBy('id')->first();
        $to = $order ? route('profile.orders.show', $order->id) : route('profile.orders');

        return match ($payment->fresh()->status) {
            'paid'      => redirect($to)->with('success', 'Payment received — thank you! Your order is now with the seller.'),
            'failed'    => redirect($to)->with('error', 'Payment failed: ' . ($payment->failure_reason ?: 'it was declined') . '. You can pay again.'),
            'cancelled' => redirect($to)->with('error', 'You left the payment page, so nothing was charged. Your order is waiting in To Pay.'),
            default     => redirect($to)->with('success', 'We are still waiting for the payment to be confirmed.'),
        };
    }

    // ── TEST sandbox gateway (PAYMENT_GATEWAY=sandbox only) ──

    public function sandbox(Payment $payment)
    {
        abort_unless($payment->provider === 'sandbox' && (int) $payment->user_id === (int) auth()->id() && config('payments.gateway') === 'sandbox', 404);

        return view('payments.sandbox', ['payment' => $payment, 'orders' => $payment->orders()->get()]);
    }

    public function sandboxAction(Request $request, Payment $payment)
    {
        abort_unless($payment->provider === 'sandbox' && (int) $payment->user_id === (int) auth()->id() && config('payments.gateway') === 'sandbox', 404);
        $request->validate(['result' => 'required|in:pay,decline,cancel']);

        if ($payment->isPending()) {
            if ($request->result === 'pay') {
                // An order cancelled for not being paid in time can't be paid any more.
                if ($payment->orders()->where('status', Order::AWAITING_PAYMENT)->doesntExist()) {
                    $payment->update(['status' => 'failed', 'failure_reason' => 'the order was already cancelled']);
                } else {
                    $this->payments->markPaid($payment, 'sbx_pay_' . $payment->id);
                }
            } elseif ($request->result === 'decline') {
                $this->payments->markFailed($payment, 'card declined by the bank (test)');
            } else {
                return redirect()->route('payments.return', [$payment, 'cancelled' => 1]);
            }
        }

        return redirect()->route('payments.return', $payment);
    }
}
