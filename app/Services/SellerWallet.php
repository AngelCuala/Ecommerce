<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutAccount;
use App\Models\Product;
use App\Models\SellerPayout;
use App\Models\SellerWalletEntry;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The seller wallet (PAYMENTS_SPEC §5, §7). It is a ledger, not a vault: the money stays in ALVY's bank until a
 * withdrawal. One line per delivered order with the seller's earning (the item price after their discounts, minus
 * ALVY's commission):
 *   on_hold    delivered, in the return window: shows its expected release date
 *   available  completed: the buyer confirmed the item is OK (released at once) or the window ended
 *   reversed   cancelled or refunded
 * Withdrawals lock the amount at once (processing); ALVY sends it and marks it completed (bank reference) or failed
 * (back in the available balance, and the seller is asked to check their account details).
 */
class SellerWallet
{
    /** Adds the missing lines and moves each line to its order's state. Safe to run any time (one line per order). */
    public function sync(int $sellerId): void
    {
        $productIds = Product::withTrashed()->where('seller_id', $sellerId)->pluck('id');
        if ($productIds->isEmpty()) {
            return;
        }
        $orders = Order::whereNotNull('delivered_at')->whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds))
            ->with(['items' => fn ($q) => $q->whereIn('product_id', $productIds)])->get();
        $lines = SellerWalletEntry::where('seller_id', $sellerId)->where('type', 'order_credit')->get()->keyBy('order_id');

        foreach ($orders as $order) {
            $earning = round((float) $order->items->whereNull('refunded_at')->sum('seller_earning'), 2);
            $status = match (true) {
                in_array($order->status, Order::VOID_STATUSES, true) || $earning <= 0 => 'reversed',
                $order->status === Order::COMPLETED                                   => 'available',
                default                                                               => 'on_hold',
            };
            $line = $lines->get($order->id);
            if (! $line) {
                SellerWalletEntry::create(['seller_id' => $sellerId, 'type' => 'order_credit', 'order_id' => $order->id,
                    'amount' => $status === 'reversed' ? round((float) $order->items->sum('seller_earning'), 2) : $earning,
                    'status' => $status, 'release_at' => $order->returnDeadline(), 'earned_at' => $order->delivered_at, 'note' => 'Order ' . $order->number()]);
                continue;
            }
            if ($line->status === 'available' && $status === 'reversed') {
                // Refunded after the money was available: a separate minus line, the order's line stays as it was.
                SellerWalletEntry::firstOrCreate(['seller_id' => $sellerId, 'type' => 'return_reversal', 'order_id' => $order->id],
                    ['amount' => -(float) $line->amount, 'status' => 'available', 'earned_at' => now(), 'note' => 'Order ' . $order->number() . ' refunded']);
                continue;
            }
            if ($line->status === 'available') {
                continue;   // final
            }
            $changes = ['status' => $status, 'release_at' => $order->returnDeadline()];
            if ($status !== 'reversed') {
                $changes['amount'] = $earning;   // a partly refunded order while still on hold
            }
            $line->fill($changes);
            if ($line->isDirty()) {
                $line->save();
            }
        }
    }

    /**
     * ['on_hold', 'available', 'processing' (withdrawals being sent), 'withdrawn' (completed), 'in_progress' (orders not
     * delivered yet: not in the wallet), and the older names pending, completed, requested, pending_payout].
     */
    public function balances(int $sellerId): array
    {
        $this->sync($sellerId);
        $lines = SellerWalletEntry::where('seller_id', $sellerId);
        $onHold = (float) (clone $lines)->where('status', 'on_hold')->sum('amount');
        $credited = (float) (clone $lines)->where('status', 'available')->sum('amount');
        $processing = (float) SellerPayout::where('seller_id', $sellerId)->where('status', 'pending')->sum('amount');
        $withdrawn = (float) SellerPayout::where('seller_id', $sellerId)->where('status', 'paid')->sum('amount');

        $productIds = Product::withTrashed()->where('seller_id', $sellerId)->pluck('id');
        $inProgress = (float) OrderItem::whereIn('product_id', $productIds)->whereNull('refunded_at')
            ->whereHas('order', fn ($q) => $q->whereNull('delivered_at')->whereNotIn('status', Order::NOT_SALES))->sum('seller_earning');

        $available = round(max(0, $credited - $processing - $withdrawn), 2);

        return [
            'on_hold' => round($onHold, 2), 'available' => $available, 'processing' => round($processing, 2), 'withdrawn' => round($withdrawn, 2),
            'in_progress' => round($inProgress, 2),
            // Older names (the app reads them).
            'pending' => round($onHold + $inProgress, 2), 'completed' => round($credited, 2), 'requested' => round($processing + $withdrawn, 2),
            'pending_payout' => round($processing, 2),
        ];
    }

    /** The balance check and the lock in one transaction (one withdrawal processing at a time). */
    public function requestPayout(int $sellerId, float $amount, ?PayoutAccount $account, string $token): SellerPayout
    {
        $this->sync($sellerId);

        return DB::transaction(function () use ($sellerId, $amount, $account, $token) {
            User::whereKey($sellerId)->lockForUpdate()->first();
            if ($done = SellerPayout::where('request_token', $token)->first()) {
                return $done;
            }
            if (SellerPayout::where('seller_id', $sellerId)->where('status', 'pending')->exists()) {
                throw new RuntimeException('You already have a withdrawal processing. You can withdraw again once ALVY sends it.');
            }
            $amount = round($amount, 2);
            $available = $this->balances($sellerId)['available'];
            if ($amount < SellerPayout::MINIMUM) {
                throw new RuntimeException('Enter an amount above ₱0.');
            }
            if ($amount > $available) {
                throw new RuntimeException('Not enough available balance: you have ₱' . number_format($available, 2) . '.');
            }
            $payout = SellerPayout::create(['seller_id' => $sellerId, 'amount' => $amount, 'status' => 'pending', 'request_token' => $token,
                'method' => $account?->method, 'bank_name' => $account?->bank_name, 'account_name' => $account?->account_name, 'account_number' => $account?->account_number]);
            ActivityLog::note('Withdrawal request ₱' . number_format($amount, 2) . ' to ' . ($payout->destination() ?? 'no account given'), $payout);

            return $payout;
        });
    }

    /** ALVY sent it from its bank: completed, with the bank reference; the seller gets a receipt. */
    public function markPaid(SellerPayout $payout, User $admin, string $reference): SellerPayout
    {
        return $this->process($payout, function (SellerPayout $p) use ($admin, $reference) {
            $p->update(['status' => 'paid', 'reference' => $reference, 'paid_at' => now(), 'processed_by' => $admin->id, 'processed_at' => now()]);
            UserNotification::create(['user_id' => $p->seller_id, 'type' => 'success', 'title' => 'Withdrawal completed',
                'body' => '₱' . number_format((float) $p->amount, 2) . ' was sent to your ' . ($p->destination() ?? 'account') . ". Bank reference: {$reference}.",
                'link' => route('seller.earnings')]);
        });
    }

    /** The transfer failed: the amount is back in the available balance; the seller checks their account details. */
    public function markFailed(SellerPayout $payout, User $admin, string $reason): SellerPayout
    {
        return $this->process($payout, function (SellerPayout $p) use ($admin, $reason) {
            $p->update(['status' => 'rejected', 'reject_reason' => $reason, 'processed_by' => $admin->id, 'processed_at' => now()]);
            UserNotification::create(['user_id' => $p->seller_id, 'type' => 'warning', 'title' => 'Withdrawal failed',
                'body' => '₱' . number_format((float) $p->amount, 2) . " is back in your available balance. Check your account details. Reason: {$reason}",
                'link' => route('seller.earnings')]);
        });
    }

    private function process(SellerPayout $payout, \Closure $do): SellerPayout
    {
        return DB::transaction(function () use ($payout, $do) {
            $p = SellerPayout::lockForUpdate()->findOrFail($payout->id);
            if ($p->status !== 'pending') {
                throw new RuntimeException('This withdrawal is already ' . strtolower($p->statusLabel()) . '.');
            }
            $do($p);
            ActivityLog::note('Seller withdrawal ₱' . number_format((float) $p->amount, 2) . ' ' . strtolower($p->statusLabel()), $p);

            return $p->fresh();
        });
    }
}
