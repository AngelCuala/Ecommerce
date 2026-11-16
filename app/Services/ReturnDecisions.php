<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Deciding a return: the seller approves or rejects it, and when the buyer escalates a rejection, an ALVY admin decides
 * the dispute. Both approvals refund the buyer the same way (approve()), so an admin's "Refund" really refunds:
 *   - only some items (A8): those items are marked refunded, the rest of the order stays delivered;
 *   - the whole order: it becomes Refunded (an online payment goes back through the gateway, see Order::booted);
 *   - cash on delivery: the amount is recorded as refunded to the buyer.
 */
class ReturnDecisions
{
    /** Refund an approved return. $by names who approved it in the buyer's message ("The seller", "ALVY"). Returns the message. */
    public function approve(ReturnRequest $return, string $by = 'The seller'): string
    {
        $order = $return->order;

        if ($return->isPartial()) {
            DB::transaction(function () use ($order, $return, $by) {
                foreach (OrderItem::where('order_id', $order->id)->whereIn('id', $return->item_ids)->get() as $item) {
                    $item->forceFill(['refunded_at' => now(), 'refund_amount' => ReturnRequest::itemRefund($item)])->save();
                }
                $order->forceFill(['status' => Order::DELIVERED])->saveQuietly();
                $order->events()->create(['status' => Order::DELIVERED, 'note' => 'Partial refund of ₱' . number_format($return->refund_amount, 2) . ' approved', 'created_at' => now()]);
                if ($order->isPaidOnline()) {
                    app(PaymentService::class)->refund($order, 'Partial return approved', (float) $return->refund_amount);
                } else {
                    $order->forceFill(['refunded_amount' => (float) $order->refunded_amount + (float) $return->refund_amount])->saveQuietly();
                }
                UserNotification::create(['user_id' => $order->user_id, 'title' => 'Partial refund approved', 'type' => 'success',
                    'body' => "{$by} approved your return for order " . $order->number() . '. ₱' . number_format($return->refund_amount, 2)
                        . ($order->isPaidOnline() ? ' goes back to your payment method.' : ' goes to your refund account.'),
                    'link' => route('profile.orders.show', $order->id, false)]);
            });

            return 'Partial return approved. The buyer will be refunded ₱' . number_format($return->refund_amount, 2) . '.';
        }

        // The whole order. A COD order's refund goes to the buyer's refund account (A31): record it like the gateway does.
        $order->update(['status' => Order::REFUNDED] + (! $order->isPaidOnline() ? ['refunded_amount' => (float) $return->refund_amount] : []));

        return 'Return approved. The buyer will be refunded.';
    }

    /** Return requests the buyer escalated, waiting for an admin. */
    public static function openDisputes()
    {
        return ReturnRequest::where('status', 'disputed');
    }

    /**
     * An admin decides a dispute: refund the buyer (overrides the seller's rejection) or close it without a refund
     * (the order completes). The reason is shown to the buyer and the seller. Refused (RuntimeException) when the
     * dispute was already decided, e.g. by another admin a moment ago.
     */
    public function decideDispute(ReturnRequest $return, User $admin, bool $refund, string $reason): string
    {
        return DB::transaction(function () use ($return, $admin, $refund, $reason) {
            $return = ReturnRequest::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($return->order_id)->lockForUpdate()->firstOrFail();
            if ($return->status !== 'disputed' || $order->status !== Order::IN_DISPUTE) {
                throw new RuntimeException('This dispute was already decided (' . $return->statusLabel() . ').');
            }

            $return->update([
                'status' => $refund ? 'approved' : 'closed', 'resolved_at' => now(),
                'admin_decision' => $refund ? 'refund' : 'close', 'admin_note' => $reason,
                'admin_decided_by' => $admin->id, 'admin_decided_at' => now(),
            ]);
            $return->setRelation('order', $order);

            if ($refund) {
                $message = $this->approve($return, 'ALVY');
                $this->collectAgain($return);
            } else {
                $order->update(['status' => Order::COMPLETED]);
                $message = 'Dispute closed without a refund; the order is completed.';
            }

            $ref = $order->number();
            UserNotification::create(['user_id' => $order->user_id, 'type' => $refund ? 'success' : 'info',
                'title' => $refund ? 'ALVY decided your dispute: refund approved' : 'ALVY decided your dispute: no refund',
                'body' => "{$ref}: " . ($refund ? 'you will be refunded ₱' . number_format((float) $return->refund_amount, 2) . '. ' : 'the return was not approved. ') . 'Reason: ' . $reason,
                'link' => route('profile.orders.show', $order->id, false)]);
            if ($return->seller_id && ! $refund) {   // a refund already tells the seller (ReturnRequest::booted)
                UserNotification::create(['user_id' => $return->seller_id, 'type' => 'info', 'title' => 'Dispute decided: no refund',
                    'body' => "{$ref}: ALVY sided with your rejection. Reason: {$reason}", 'link' => route('seller.orders.show', $order->id)]);
            }
            ActivityLog::note("Dispute on {$ref} " . ($refund ? 'refunded' : 'closed without a refund') . ": {$reason}", $order);

            return $message;
        });
    }

    /** The rejection cancelled the rider pickup: with the refund approved, the item is collected after all. */
    private function collectAgain(ReturnRequest $return): void
    {
        $shipment = ReturnShipment::where('return_request_id', $return->id)->latest('id')->first();
        if (! $shipment) {
            app(ReturnShipments::class)->open($return);
            return;
        }
        if ($shipment->status === 'cancelled' && ! $shipment->picked_up_at) {
            $shipment->update(['status' => 'requested', 'cancel_reason' => null]);
            if ($shipment->sorting_center_id) {
                app(ParcelService::class)->notify((int) $shipment->sorting_center_id, 'Return pickup back on',
                    ($return->order?->number() ?? 'An order') . ': ALVY approved the refund, so the item is collected after all. Assign a rider.', 'warning', route('sc.returns'));
            }
        }
    }
}
