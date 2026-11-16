<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\UserNotification;
use App\Support\AlvyGcash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * GCash paid to ALVY at checkout (payment_method gcash_receipt). One receipt covers a whole checkout
 * (every store's order in its checkout_group), so it is checked once:
 *
 *   approve   the amount arrived: every order becomes Pending (Paid) and its seller is told to prepare it.
 *             The seller's share then counts in their Earnings as usual, and the shipping fee goes to
 *             logistics as for any order; ALVY pays them out.
 *   reject    no payment, or the wrong amount: the orders are cancelled (stock and vouchers come back).
 *             If money did arrive, it is listed under Refunds to send.
 *
 * Refunds happen only here: money from a rejected payment, or an order that sold out before approval.
 * (A confirmed GCash order is not refunded later.) Also ALVY's own GCash QR.
 */
class GcashPaymentController extends Controller
{
    public const REJECT_REASONS = [
        'not_received' => 'No GCash payment with this Ref No. reached ALVY',
        'wrong_amount' => 'The amount sent does not match the order total',
        'unclear'      => 'The receipt is unclear or is not a GCash receipt',
    ];

    public function index()
    {
        $waiting = Order::where('status', Order::AWAITING_PAYMENT)->where('payment_method', Order::GCASH_RECEIPT)
            ->where('payment_status', 'For verification')
            ->with(['user:id,name,email', 'seller', 'items'])
            ->oldest()->get()->groupBy('checkout_group');

        $fixing = AlvyGcash::rejected()->with('user:id,name,email')->oldest('updated_at')->get()->groupBy('checkout_group');

        $refunds = Order::where('payment_method', Order::GCASH_RECEIPT)->where('payment_status', 'Refund pending')
            ->with(['user:id,name,email,contact_no'])->oldest('updated_at')->get();

        $recent = Order::where('payment_method', Order::GCASH_RECEIPT)->whereIn('payment_status', ['Paid', 'Refunded', 'Not received'])
            ->where('updated_at', '>=', now()->subDays(14))
            ->with('user:id,name')->latest('updated_at')->take(30)->get();

        return view('admin.gcash-payments.index', [
            'waiting'  => $waiting,
            'fixing'   => $fixing,
            'refunds'  => $refunds,
            'recent'   => $recent,
            'account'  => AlvyGcash::settings() + ['qr_url' => AlvyGcash::account()['qr_url'] ?? null],
            'reasons'  => self::REJECT_REASONS,
        ]);
    }

    public function approve(string $group)
    {
        $orders = $this->waitingOrders($group);
        if ($orders->isEmpty()) {
            return back()->with('error', 'That GCash payment was already checked.');
        }

        // Each order takes its stock now. One that sold out while the payment waited is cancelled instead,
        // and its money is listed under Refunds to send (the buyer is told why).
        $soldOut = [];
        DB::transaction(function () use ($orders, &$soldOut) {
            foreach ($orders as $order) {
                if ($item = $order->takeStock()) {
                    $soldOut[] = $order->number() . ' (' . $item . ')';
                    $order->forceFill(['payment_status' => 'Refund pending'])->saveQuietly();
                    $order->update(['status' => Order::CANCELLED,
                        'cancellation_reason' => 'Sold out while your GCash payment was being checked: ' . $item . '. ALVY will send this order\'s amount back to your GCash.']);
                    continue;
                }
                $order->update(['status' => Order::PENDING, 'payment_status' => 'Paid']);   // tells the buyer and the seller
            }
        });
        $total = round((float) ($orders->first()->payment_expected ?? $orders->sum('total_price')), 2);
        ActivityLog::note('GCash payment approved · ₱' . number_format($total, 2) . ' · Ref ' . $orders->first()->payment_reference
            . ' · orders ' . $orders->map->number()->implode(', ') . ($soldOut ? ' · sold out, to refund: ' . implode(', ', $soldOut) : ''), $orders->first());

        $sent = $orders->count() - count($soldOut);
        return back()->with($soldOut ? 'error' : 'success', 'GCash payment of ₱' . number_format($total, 2) . ' approved. '
            . ($sent ? ($sent > 1 ? "The {$sent} sellers were" : 'The seller was') . ' told to prepare the order. ' : '')
            . ($soldOut ? 'Sold out, so cancelled and listed under Refunds to send: ' . implode(', ', $soldOut) . '.' : ''));
    }

    public function reject(Request $request, string $group)
    {
        $data = $request->validate([
            'reason'   => ['required', 'in:' . implode(',', array_keys(self::REJECT_REASONS))],
            'note'     => 'nullable|string|max:200',
            'received' => 'nullable|boolean',
            'let_fix'  => 'nullable|boolean',
        ], ['reason.required' => 'Choose why the payment is rejected.']);

        $orders = $this->waitingOrders($group);
        if ($orders->isEmpty()) {
            return back()->with('error', 'That GCash payment was already checked.');
        }

        // Usually: the buyer is told why and how to fix it, and uploads the right receipt (24 hours, up to 3 tries).
        if ($request->boolean('let_fix')) {
            app(\App\Services\GcashChecks::class)->askToFix($orders, [$data['reason']], 'admin', $data['note'] ?? null);
            $cancelled = $orders->first()->fresh()->status === Order::CANCELLED;
            return back()->with('success', $cancelled
                ? 'GCash payment rejected. It was the buyer\'s last try, so the order was cancelled.'
                : 'GCash payment rejected. The buyer was told why and how to fix it, and has ' . \App\Services\GcashChecks::FIX_HOURS . ' hours to upload the right receipt.');
        }

        // Money that did arrive (e.g. the wrong amount) goes back to the buyer: listed under Refunds to send.
        $received = $request->boolean('received') && $data['reason'] !== 'not_received';
        $reason = 'GCash payment not confirmed: ' . self::REJECT_REASONS[$data['reason']] . (filled($data['note'] ?? null) ? ' — ' . $data['note'] : '');

        DB::transaction(function () use ($orders, $reason, $received) {
            foreach ($orders as $order) {
                $order->forceFill(['payment_status' => $received ? 'Refund pending' : 'Not received'])->saveQuietly();
                $order->update(['status' => Order::CANCELLED, 'cancellation_reason' => $reason]);   // stock and vouchers come back
            }
        });
        ActivityLog::note('GCash payment rejected · Ref ' . $orders->first()->payment_reference . ' · ' . $reason
            . ($received ? ' · to refund' : '') . ' · orders ' . $orders->map->number()->implode(', '), $orders->first());

        return back()->with('success', 'GCash payment rejected and the order cancelled.' . ($received ? ' Send the money back, then mark it refunded under Refunds to send.' : ''));
    }

    public function refunded(Request $request, Order $order)
    {
        $data = $request->validate(['refund_reference' => ['nullable', 'string', 'regex:/^\d{13}$/']],
            ['refund_reference.regex' => 'The GCash Ref No. is the 13 numbers on the refund receipt (numbers only).']);

        if (! $order->isGcashReceipt() || $order->payment_status !== 'Refund pending') {
            return back()->with('error', 'Order ' . $order->number() . ' has no GCash refund waiting.');
        }

        $amount = $this->refundAmount($order);
        $ref = filled($data['refund_reference'] ?? null) ? preg_replace('/\s+/', '', $data['refund_reference']) : null;
        $order->forceFill(['payment_status' => 'Refunded', 'refunded_amount' => (float) $order->refunded_amount + $amount])->saveQuietly();

        UserNotification::create([
            'user_id' => $order->user_id, 'type' => 'success', 'title' => 'GCash refund sent',
            'body'    => 'ALVY sent ₱' . number_format($amount, 2) . ' back to your GCash for order ' . $order->number() . ($ref ? ' (Ref No. ' . $ref . ').' : '.'),
            'link'    => route('profile.orders.show', $order->id, false),
        ]);
        ActivityLog::note('GCash refund sent · ₱' . number_format($amount, 2) . ' · order ' . $order->number() . ($ref ? ' · Ref ' . $ref : ''), $order);

        return back()->with('success', 'Order ' . $order->number() . ' marked as refunded. The buyer was told.');
    }

    /** ALVY's GCash: the QR buyers scan, the name and the number shown under it. */
    public function updateAccount(Request $request)
    {
        $data = $request->validate([
            'qr'     => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'name'   => ['required', 'string', 'max:60'],
            'number' => ['nullable', 'regex:/^09\d{9}$/'],
        ], [
            'qr.image'     => 'The QR must be an image (JPG, PNG or WebP).',
            'qr.mimes'     => 'The QR must be an image (JPG, PNG or WebP).',
            'number.regex' => 'Enter the GCash number as 11 digits starting with 09.',
        ]);

        $current = AlvyGcash::settings();
        $values = ['name' => $data['name'], 'number' => $data['number'] ?? null];
        if ($request->hasFile('qr')) {
            $values['qr_path'] = $request->file('qr')->store('gcash-qr', 'public');
            // The old QR goes, unless it is the shared demo image.
            if ($current['qr_path'] && $current['qr_path'] !== 'gcash-qr/demo-gcash-qr.png') {
                Storage::disk('public')->delete($current['qr_path']);
            }
        } elseif (! $current['qr_path']) {
            return back()->withInput()->withErrors(['qr' => 'Upload ALVY\'s GCash QR.']);
        }
        AlvyGcash::save($values);
        ActivityLog::note('ALVY GCash account updated · ' . $values['name']);

        return back()->with('success', 'ALVY\'s GCash account saved. Checkout shows it from now on.');
    }

    /** What to send back: the order total minus anything already refunded. */
    public static function refundAmount(Order $order): float
    {
        return round(max(0, (float) $order->total_price - (float) $order->refunded_amount), 2);
    }

    private function waitingOrders(string $group)
    {
        return Order::where('checkout_group', $group)->where('status', Order::AWAITING_PAYMENT)
            ->where('payment_method', Order::GCASH_RECEIPT)->where('payment_status', 'For verification')->get();
    }
}
