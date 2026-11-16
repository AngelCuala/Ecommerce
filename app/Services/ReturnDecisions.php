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
 *   - the whole order: it becomes Refunded (the seller's earning for it is removed).
 * The money itself is a Refund (App\Services\Refunds): the buyer says where to send it, and it is sent as soon as
 * the item is back with the seller (paid online: back through the gateway then).
 */
class ReturnDecisions
{
    /** Refund an approved return. $by names who approved it in the buyer's message ("The seller", "ALVY"). Returns the message. */
    public function approve(ReturnRequest $return, string $by = 'The seller'): string
    {
        $order = $return->order;
        $refunds = app(Refunds::class);

        if ($return->isPartial()) {
            $refund = DB::transaction(function () use ($order, $return, $by, $refunds) {
                foreach (OrderItem::where('order_id', $order->id)->whereIn('id', $return->item_ids)->get() as $item) {
                    $item->forceFill(['refunded_at' => now(), 'refund_amount' => ReturnRequest::itemRefund($item)])->save();
                }
                $order->forceFill(['status' => Order::DELIVERED])->saveQuietly();
                $order->events()->create(['status' => Order::DELIVERED, 'note' => 'Partial return of ₱' . number_format($return->refund_amount, 2) . ' approved', 'created_at' => now()]);
                $refund = $refunds->open($return);
                UserNotification::create(['user_id' => $order->user_id, 'title' => 'Partial return approved', 'type' => 'success',
                    'body' => "{$by} approved your return for order " . $order->number() . '. ' . Refunds::nextStep($order, (float) $return->refund_amount),
                    'link' => route('profile.orders.show', $order->id, false) . '#return']);

                return $refund;
            });
            $refunds->advance($refund);

            return 'Partial return approved. The buyer gets ₱' . number_format($return->refund_amount, 2) . ' back once the item is back with the seller.';
        }

        // The whole order. The refund exists first, so the order doesn't refund through the gateway now (Order::booted).
        $refund = $refunds->open($return);
        $order->update(['status' => Order::REFUNDED]);
        $refunds->advance($refund);

        return 'Return approved. The buyer is refunded once the item is back with the seller.';
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
                'body' => "{$ref}: " . ($refund ? 'you will be refunded ₱' . number_format((float) $return->refund_amount, 2) . ' once the item is back with the seller. ' : 'the return was not approved. ') . 'Reason: ' . $reason,
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
