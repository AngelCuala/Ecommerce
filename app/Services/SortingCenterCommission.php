<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Parcel;
use App\Models\Rider;
use Illuminate\Support\Collection;

/**
 * A sorting center's earnings, parcel by parcel:
 *
 *   share       the order's shipping fee, split evenly between the parcels of the order and then between
 *               the hubs that handled the parcel (the hub its rider picked it up for, the hub its rider
 *               delivered it for, and the hub holding it now): one hub keeps it all, two split 50/50
 *   commission  ALVY's cut of that share (config marketplace.sorting_center_commission_rate, %)
 *   rider pay   what the hub owes its own riders for this parcel (RiderWallet rates: pickup, delivery)
 *   net         share − commission − rider pay
 *
 * Status of a line:
 *   pending   on its way: the share may still change (e.g. a second hub joins after a transfer)
 *   earned    delivered: the share and commission are final
 *   reversed  the order was cancelled or refunded: no shipping fee is earned, but rider pay already
 *             paid out still counts against the hub
 *
 * Nothing is stored; every number comes from parcels, pickups and deliveries, so it always matches them.
 */
class SortingCenterCommission
{
    public const STATUSES = [
        'pending'  => 'Pending',
        'earned'   => 'Earned',
        'reversed' => 'Reversed',
    ];

    public static function rate(): float
    {
        return (float) config('marketplace.sorting_center_commission_rate', 10);
    }

    /**
     * Every parcel this hub handled that was created in the period, newest first.
     *
     * @return Collection<int, array{parcel: Parcel, hubs: int, fee: float, share: float, commission: float, rider_pay: float, net: float, status: string, role: string}>
     */
    public function lines(int $scId, ?array $between = null): Collection
    {
        $riderIds = Rider::where('sorting_center_id', $scId)->pluck('id');

        $parcels = Parcel::query()
            ->where(fn ($q) => $q->where('current_sorting_center_id', $scId)
                ->orWhereHas('pickups', fn ($p) => $p->whereIn('rider_id', $riderIds)->whereIn('status', ['picked_up', 'received']))
                ->orWhereHas('deliveries', fn ($d) => $d->whereIn('rider_id', $riderIds)->where('status', 'delivered')))
            ->when($between, fn ($q) => $q->whereBetween('created_at', $between))
            ->with(['order:id,status,shipping_fee,created_at', 'pickups.rider:id,sorting_center_id', 'deliveries.rider:id,sorting_center_id'])
            ->latest('id')
            ->get();

        $perOrder = Parcel::whereIn('order_id', $parcels->pluck('order_id')->filter()->unique())
            ->selectRaw('order_id, count(*) as n')->groupBy('order_id')->pluck('n', 'order_id');

        return $parcels->map(fn (Parcel $p) => $this->line($p, $scId, max(1, (int) ($perOrder[$p->order_id] ?? 1))));
    }

    private function line(Parcel $parcel, int $scId, int $parcelsInOrder): array
    {
        $pickups    = $parcel->pickups->whereIn('status', ['picked_up', 'received']);
        $deliveries = $parcel->deliveries->where('status', 'delivered');

        // Plain collections of hub IDs (an Eloquent collection can't merge plain values).
        $pickupHubs   = $pickups->toBase()->map(fn ($j) => $j->rider?->sorting_center_id)->filter();
        $deliveryHubs = $deliveries->toBase()->map(fn ($j) => $j->rider?->sorting_center_id)->filter();
        $hubs = $pickupHubs->merge($deliveryHubs)->push($parcel->current_sorting_center_id)
            ->filter()->map(fn ($id) => (int) $id)->unique();

        $status = $this->statusOf($parcel);
        $fee    = round((float) ($parcel->order?->shipping_fee ?? 0) / $parcelsInOrder, 2);
        $share  = $status === 'reversed' ? 0.0 : round($fee / max(1, $hubs->count()), 2);
        $commission = round($share * self::rate() / 100, 2);

        $mine = fn ($jobs) => $jobs->filter(fn ($j) => (int) $j->rider?->sorting_center_id === $scId)->count();
        $riderPay = round($mine($pickups) * RiderDispatch::PAY_PICKUP + $mine($deliveries) * RiderDispatch::PAY_DELIVERY, 2);

        $role = match (true) {
            $pickupHubs->contains($scId) && $deliveryHubs->contains($scId) => 'Pickup & delivery',
            $deliveryHubs->contains($scId)                                 => 'Delivery',
            $pickupHubs->contains($scId)                                   => 'Pickup',
            default                                                        => 'At this hub',
        };

        return [
            'parcel' => $parcel, 'hubs' => max(1, $hubs->count()), 'fee' => $fee, 'share' => $share,
            'commission' => $commission, 'rider_pay' => $riderPay,
            'net' => round($share - $commission - $riderPay, 2), 'status' => $status, 'role' => $role,
        ];
    }

    public function statusOf(Parcel $parcel): string
    {
        $order = $parcel->order?->status;

        return match (true) {
            in_array($order, Order::VOID_STATUSES, true) || $parcel->status === 'cancelled' => 'reversed',
            $parcel->status === 'delivered' || in_array($order, [Order::DELIVERED, Order::COMPLETED], true) => 'earned',
            default                                                                       => 'pending',
        };
    }

    /**
     * Totals for a set of lines.
     *
     * @return array{fees: float, commission: float, rider_pay: float, net: float, earned_net: float, pending_net: float, parcels: int}
     */
    public function summarize(Collection $lines): array
    {
        $t = ['fees' => 0.0, 'commission' => 0.0, 'rider_pay' => 0.0, 'net' => 0.0, 'earned_net' => 0.0, 'pending_net' => 0.0, 'parcels' => $lines->count()];
        foreach ($lines as $l) {
            $t['fees']       += $l['share'];
            $t['commission'] += $l['commission'];
            $t['rider_pay']  += $l['rider_pay'];
            $t['net']        += $l['net'];
            if ($l['status'] !== 'reversed') {
                $t[$l['status'] . '_net'] += $l['net'];
            }
        }

        return array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $t);
    }
}
