<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sending an approved return's money to the buyer. The money moves only when the item is back with the seller:
 *
 *   approved  → awaiting_details  the buyer says where to send it (GCash, Maya or a bank account)
 *             → waiting_for_item  sent as soon as the rider hands the item back to the seller
 *             → ready_to_send     the item is back: every admin is told, it is first on Admin → Refunds (urgent)
 *             → sent              an admin sent it and typed the transfer's reference number; the buyer is told
 *
 * The item back before the details: the refund waits for the details, then is ready at once.
 * Paid online (card, GCash online, Maya): no details; it goes back through the gateway when the item is back
 * (if the gateway refuses, an admin asks the buyer for details and sends it by hand).
 * Wrong details (the transfer bounced, the name doesn't match): the admin sends it back to the buyer to fix.
 */
class Refunds
{
    /** Approved: the refund is created (not sent). Call advance() once the order is updated. */
    public function open(ReturnRequest $return): Refund
    {
        $order = $return->order;

        return Refund::firstOrCreate(['return_request_id' => $return->id], [
            'order_id' => $order->id, 'user_id' => $order->user_id, 'amount' => (float) $return->refund_amount,
            'method'   => $order->isPaidOnline() ? 'original' : null,
            'status'   => $order->isPaidOnline() ? 'waiting_for_item' : 'awaiting_details',
        ]);
    }

    /** What happens next, for the buyer's "approved" message. */
    public static function nextStep(Order $order, float $amount): string
    {
        $money = '₱' . number_format($amount, 2);

        return $order->isPaidOnline()
            ? "{$money} goes back to your " . $order->paymentMethodLabel() . ' as soon as the item is back with the seller.'
            : "Tell us where to send your {$money} (GCash, Maya or a bank account) on the order page. ALVY sends it as soon as the item is back with the seller.";
    }

    /** The buyer's details. Refused (RuntimeException) once they are in. */
    public function saveDetails(Refund $refund, array $data): void
    {
        DB::transaction(function () use ($refund, $data) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'awaiting_details') {
                throw new RuntimeException('Your refund details are already in.');
            }
            $refund->update([
                'method' => $data['method'], 'account_name' => trim($data['account_name']),
                'account_number' => preg_replace('/\D/', '', $data['account_number']),
                'bank_name' => $data['method'] === 'bank' ? $data['bank_name'] : null,
                'details_at' => now(), 'status' => 'waiting_for_item', 'admin_note' => null,
            ]);
            ActivityLog::note('Refund details given for ' . $refund->order->number() . ' · ' . $refund->destination(), $refund->order);
            $this->advance($refund);
        });
    }

    /** The return trip moved: the item back with the seller (or lost on the way) lets the refund go. */
    public function shipmentMoved(ReturnShipment $rs): void
    {
        if ($refund = Refund::where('return_request_id', $rs->return_request_id)->first()) {
            $this->advance($refund);
        }
    }

    /** Moves the refund on when the item is back: ready to send now (or sent through the gateway). */
    public function advance(Refund $refund): void
    {
        if (in_array($refund->status, ['ready_to_send', 'sent'], true) || ! $this->itemIsBack($refund)) {
            return;
        }
        $order = $refund->order;
        $n = $order->number();
        $first = ! $refund->item_back_at;
        if ($first) {
            $refund->update(['item_back_at' => now()]);
        }

        if ($refund->status === 'awaiting_details') {
            if ($first) {
                $this->toBuyer($refund, 'Enter where to send your refund', "{$n}: the item is back with the seller. Tell us where to send your ₱"
                    . number_format((float) $refund->amount, 2) . ' and ALVY sends it right away.', 'warning');
            }
            return;
        }

        if ($refund->method === 'original') {
            $before = (float) $order->refunded_amount;
            app(PaymentService::class)->refund($order, 'Return approved', (float) $refund->amount);
            if ((float) $order->fresh()->refunded_amount > $before) {
                $this->markSent($refund, null, null, false);
                return;
            }
            $refund->update(['admin_note' => 'The payment gateway refused the refund. Ask the buyer where to send it.']);
        }

        $refund->update(['status' => 'ready_to_send', 'ready_at' => now()]);
        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            UserNotification::create(['user_id' => $adminId, 'type' => 'warning', 'title' => 'Urgent: refund to send',
                'body' => "{$n}: the returned item is back with the seller. Send ₱" . number_format((float) $refund->amount, 2) . ' to the buyer now.',
                'link' => route('admin.refunds.index', [], false)]);
        }
        if ($refund->method !== 'original') {
            $this->toBuyer($refund, 'Your refund is being sent', "{$n}: the item is back with the seller. ALVY is sending ₱"
                . number_format((float) $refund->amount, 2) . ' to your ' . $refund->destination() . '.', 'info');
        }
    }

    /** Sent. $bookAmount: false when the gateway already recorded it on the order. */
    public function markSent(Refund $refund, ?User $admin, ?string $reference, bool $bookAmount = true): void
    {
        DB::transaction(function () use ($refund, $admin, $reference, $bookAmount) {
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status === 'sent') {
                throw new RuntimeException('This refund was already sent.');
            }
            if ($admin && $refund->status !== 'ready_to_send') {
                throw new RuntimeException('This refund is not ready to send (' . $refund->statusLabel() . ').');
            }
            $refund->update(['status' => 'sent', 'sent_at' => now(), 'sent_by' => $admin?->id, 'reference' => $reference]);
            $order = Order::whereKey($refund->order_id)->lockForUpdate()->first();
            if ($bookAmount) {
                $order->forceFill(['refunded_amount' => round((float) $order->refunded_amount + (float) $refund->amount, 2)])->saveQuietly();
            }
            $n = $order->number();
            $this->toBuyer($refund, 'Refund sent', "{$n}: ALVY sent ₱" . number_format((float) $refund->amount, 2) . ' to your ' . $refund->destination()
                . ($reference ? " (Ref No. {$reference})." : '.'), 'success');
            ActivityLog::note("Refund sent for {$n} · ₱" . number_format((float) $refund->amount, 2) . ' · ' . $refund->destination()
                . ($reference ? " · Ref {$reference}" : '') . ($admin ? '' : ' · through the payment gateway'), $order);
        });
    }

    /** The details didn't work: back to the buyer to fix, with the reason. */
    public function sendBack(Refund $refund, User $admin, string $reason): void
    {
        if (! in_array($refund->status, ['waiting_for_item', 'ready_to_send'], true)) {
            throw new RuntimeException('This refund can\'t be sent back (' . $refund->statusLabel() . ').');
        }
        $refund->update(['status' => 'awaiting_details', 'method' => null, 'account_name' => null, 'account_number' => null, 'bank_name' => null,
            'details_at' => null, 'ready_at' => null, 'admin_note' => $reason]);
        $this->toBuyer($refund, 'Fix your refund details', $refund->order->number() . ": ALVY couldn't send your refund. {$reason} Enter your details again.", 'warning');
        ActivityLog::note('Refund details sent back to the buyer for ' . $refund->order->number() . ": {$reason}", $refund->order);
    }

    /** The item reached the seller, went missing on the way (not the buyer's fault), or was never collected. */
    public function itemIsBack(Refund $refund): bool
    {
        $rs = ReturnShipment::where('return_request_id', $refund->return_request_id)->latest('id')->first();

        return ! $rs || in_array($rs->status, ['returned', 'missing'], true) || ($rs->status === 'cancelled' && ! $rs->picked_up_at);
    }

    private function toBuyer(Refund $refund, string $title, string $body, string $type): void
    {
        UserNotification::create(['user_id' => $refund->user_id, 'title' => $title, 'body' => $body, 'type' => $type,
            'link' => route('profile.orders.show', $refund->order_id, false) . '#return']);
    }
}
