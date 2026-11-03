<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\GcashChecks;
use Illuminate\Http\Request;

/**
 * After a GCash payment is rejected, the buyer uploads the right receipt (and its Ref No.) on the order page.
 * The whole checkout's orders share it, and the payment waits for checking again.
 */
class GcashResubmitController extends Controller
{
    public function store(Request $request, int $id)
    {
        $order = Order::where('user_id', auth()->id())->findOrFail($id);
        if (! $order->isGcashReceipt() || $order->status !== Order::AWAITING_PAYMENT || $order->payment_status !== 'Rejected') {
            return back()->with('error', 'This order has no rejected GCash payment to fix.');
        }

        $request->validate([
            'payment_proof'     => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'payment_reference' => ['required', 'string', 'regex:/^[0-9 ]+$/', function ($attribute, $value, $fail) use ($order) {
                $digits = preg_replace('/\s+/', '', (string) $value);
                if (strlen($digits) !== 13) {
                    $fail('The GCash Ref No. has 13 numbers. Copy it from your receipt.');
                } elseif (Order::where('payment_method', Order::GCASH_RECEIPT)->where('payment_reference', $digits)
                    ->where('checkout_group', '!=', $order->checkout_group)->exists()) {
                    $fail('This GCash Ref No. was already used for another order. Check your receipt.');
                }
            }],
        ], [
            'payment_proof.required'     => 'Upload the screenshot of your GCash receipt.',
            'payment_proof.image'        => 'The GCash receipt must be a photo or screenshot (JPG, PNG or WebP).',
            'payment_proof.mimes'        => 'The GCash receipt must be a photo or screenshot (JPG, PNG or WebP).',
            'payment_proof.max'          => 'The GCash receipt must be 5 MB or smaller.',
            'payment_reference.required' => 'Enter the GCash Ref No. of your payment.',
            'payment_reference.regex'    => 'The GCash Ref No. can only have numbers (no letters).',
        ]);

        $orders = Order::where('checkout_group', $order->checkout_group)->where('status', Order::AWAITING_PAYMENT)
            ->where('payment_method', Order::GCASH_RECEIPT)->where('payment_status', 'Rejected')->get();
        $path = $request->file('payment_proof')->store('payment-proofs', 'local');   // earlier receipts are kept for the admin
        app(GcashChecks::class)->resubmit($orders, $path, preg_replace('/\s+/', '', $request->payment_reference));
        \App\Models\ActivityLog::note('GCash receipt uploaded again · orders ' . $orders->map->number()->implode(', '), $order);

        return back()->with('success', 'Thanks! Your new GCash receipt was sent. ALVY will check it soon.');
    }
}
