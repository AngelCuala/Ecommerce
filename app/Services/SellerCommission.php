<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Commission on a seller's sales, line by line. Every order item stores the rate in force when it
 * was bought (commission_rate), the commission ALVY keeps (commission_amount) and what the seller
 * gets (seller_earning), so nothing here is recalculated from today's rate.
 *
 * Status of a line's commission:
 *   pending    order placed (paid online or COD), not completed yet: may still change
 *   finalized  order Completed: the commission is final
 *   refunded   the item (or the whole order) was refunded: no commission is kept
 *   reversed   the order was cancelled: no commission is charged
 *   unpaid     online order not paid yet: not a sale yet
 *
 * Totals count only pending + finalized lines: the same rule as Sales Reports and the admin
 * Analytics (orders not in Order::NOT_SALES, items not refunded), so the numbers always match.
 */
class SellerCommission
{
    public const STATUSES = [
        'pending'   => 'Pending',
        'finalized' => 'Finalized',
        'refunded'  => 'Refunded',
        'reversed'  => 'Reversed',
        'unpaid'    => 'Unpaid',
    ];

    /** The seller's order lines (deleted listings included: their sales happened), with order and product. */
    public function lines(int $sellerId, ?array $between = null): Builder
    {
        return OrderItem::query()
            ->whereIn('product_id', Product::withTrashed()->where('seller_id', $sellerId)->select('id'))
            ->when($between, fn ($q) => $q->whereHas('order', fn ($o) => $o->whereBetween('created_at', $between)))
            ->with(['order:id,status,created_at,user_id', 'product' => fn ($p) => $p->withTrashed()->select('id', 'title')]);
    }

    /** Restrict a lines() query to one commission status. */
    public function whereStatus(Builder $q, string $status): Builder
    {
        $order = fn (callable $scope) => fn ($w) => $w->whereNull('refunded_at')->whereHas('order', $scope);

        return match ($status) {
            'refunded'  => $q->where(fn ($w) => $w->whereNotNull('refunded_at')->orWhereHas('order', fn ($o) => $o->where('status', Order::REFUNDED))),
            'reversed'  => $q->where($order(fn ($o) => $o->where('status', Order::CANCELLED))),
            'unpaid'    => $q->where($order(fn ($o) => $o->where('status', Order::AWAITING_PAYMENT))),
            'finalized' => $q->where($order(fn ($o) => $o->where('status', Order::COMPLETED))),
            'pending'   => $q->where($order(fn ($o) => $o->whereNotIn('status', array_merge(Order::NOT_SALES, [Order::COMPLETED])))),
            default     => $q,
        };
    }

    public function statusOf(OrderItem $item): string
    {
        $status = $item->order?->status;

        return match (true) {
            $item->refunded_at !== null || $status === Order::REFUNDED => 'refunded',
            $status === Order::CANCELLED                               => 'reversed',
            $status === Order::AWAITING_PAYMENT                        => 'unpaid',
            $status === Order::COMPLETED                               => 'finalized',
            default                                                    => 'pending',
        };
    }

    /** What the buyer paid for the line (after the seller's own vouchers and promotions). */
    public static function gross(OrderItem $item): float
    {
        return round((float) $item->price * (int) $item->quantity - (float) $item->seller_discount - (float) $item->promo_discount, 2);
    }

    /**
     * Totals for a set of lines.
     *
     * @return array{gross: float, refunds: float, commission: float, net: float, pending_commission: float, finalized_commission: float, lines: int}
     */
    public function summarize(Collection $items): array
    {
        $t = ['gross' => 0.0, 'refunds' => 0.0, 'commission' => 0.0, 'net' => 0.0, 'pending_commission' => 0.0, 'finalized_commission' => 0.0, 'lines' => 0];
        foreach ($items as $item) {
            $status = $this->statusOf($item);
            if ($status === 'refunded') {
                $t['refunds'] += self::gross($item);
                continue;
            }
            if ($status !== 'pending' && $status !== 'finalized') {
                continue;   // cancelled or unpaid: never a sale, no commission
            }
            $t['lines']++;
            $t['gross']      += self::gross($item);
            $t['commission'] += (float) $item->commission_amount;
            $t['net']        += (float) $item->seller_earning;
            $t[$status . '_commission'] += (float) $item->commission_amount;
        }

        return array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $t);
    }
}
