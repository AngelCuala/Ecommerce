<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Parcel;
use App\Models\Rider;
use Illuminate\Support\Collection;

/**
 * A sorting center's earnings, parcel by parcel. The parcel's shipping fee is the order's shipping fee split evenly
 * between the parcels of the order. Since RiderDispatch::SHARE_SINCE it is split 30/30/40:
 *
 *   riders      30% to the rider who picked it up, 30% to the rider who delivered it (RiderDispatch::jobPay)
 *   share       40%, split evenly between every hub the parcel passed: the hubs at both ends of each linehaul
 *               trip it travelled on and the hub holding it (or that delivered it). A sorting center with two of
 *               those hubs gets two parts. Four hubs: each gets a quarter of the 40%.
 *   commission  ALVY's cut of that share (config marketplace.sorting_center_commission_rate, %)
 *   rider pay   this company's riders' 30% parts (paid from the riders' shares, not from the hub's)
 *   net         share − commission
 *
 * Parcels created before SHARE_SINCE keep the old rule: the whole fee split between the companies that handled
 * it, and net = share − commission − rider pay (₱30 a pickup, ₱50 a delivery).
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
                ->orWhereHas('deliveries', fn ($d) => $d->whereIn('rider_id', $riderIds)->where('status', 'delivered'))
                // It passed one of this company's hubs on a linehaul trip (its part of the hubs' 40%).
                ->orWhereHas('linehaulItems', fn ($i) => $i->whereNotIn('status', ['pending', 'removed'])
                    ->whereHas('trip', fn ($t) => $t->where('status', '!=', 'cancelled')
                        ->where(fn ($w) => $w->where('sorting_center_id', $scId)->orWhere('destination_sorting_center_id', $scId)))))
            ->when($between, fn ($q) => $q->whereBetween('created_at', $between))
            ->with(['order:id,status,shipping_fee,created_at', 'pickups.rider:id,sorting_center_id', 'deliveries.rider:id,sorting_center_id',
                'linehaulItems.trip:id,status,origin_hub_id,destination_hub_id', 'currentHub:id,sorting_center_id'])
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
        $mineJobs = fn ($jobs) => $jobs->filter(fn ($j) => (int) $j->rider?->sorting_center_id === $scId);

        if ($parcel->created_at && $parcel->created_at->lt(RiderDispatch::shareSince())) {
            // The old rule: the whole fee between the companies; the hub pays its riders out of it.
            $units = max(1, $hubs->count());
            $share = $status === 'reversed' ? 0.0 : round($fee / $units, 2);
            $commission = round($share * self::rate() / 100, 2);
            $riderPay = round($mineJobs($pickups)->count() * RiderDispatch::PAY_PICKUP + $mineJobs($deliveries)->count() * RiderDispatch::PAY_DELIVERY, 2);
            $net = round($share - $commission - $riderPay, 2);
        } else {
            // 30/30/40: the hubs' 40% split evenly between every hub the parcel passed.
            $path = $this->hubPath($parcel);
            foreach ($hubs as $company) {   // a company that handled it with no hub on record counts as one hub
                if (! $path->contains($company)) {
                    $path->push($company);
                }
            }
            $units = max(1, $path->count());
            $mineUnits = $path->filter(fn ($c) => $c === $scId)->count();
            $hubsPart = $fee * RiderDispatch::HUBS_SHARE / 100;
            $share = $status === 'reversed' ? 0.0 : round($hubsPart * $mineUnits / $units, 2);
            $commission = round($share * self::rate() / 100, 2);
            $riderPay = round($mineJobs($pickups)->sum(fn ($j) => RiderDispatch::pickupPay($parcel, $j->picked_up_at))
                + $mineJobs($deliveries)->sum(fn ($j) => RiderDispatch::deliveryPay($parcel, $j->delivered_at)), 2);
            $net = round($share - $commission, 2);
        }

        $role = match (true) {
            $pickupHubs->contains($scId) && $deliveryHubs->contains($scId) => 'Pickup & delivery',
            $deliveryHubs->contains($scId)                                 => 'Delivery',
            $pickupHubs->contains($scId)                                   => 'Pickup',
            default                                                        => 'At this hub',
        };

        return [
            'parcel' => $parcel, 'hubs' => $units, 'fee' => $fee, 'share' => $share,
            'commission' => $commission, 'rider_pay' => $riderPay,
            'net' => $net, 'status' => $status, 'role' => $role,
        ];
    }

    /**
     * The sorting center of every hub the parcel passed, one entry per hub: both ends of each linehaul trip it
     * travelled on (not one it was taken off, or a cancelled trip), and the hub holding it now.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function hubPath(Parcel $parcel): Collection
    {
        $hubIds = collect();
        foreach ($parcel->linehaulItems as $item) {
            if (in_array($item->status, ['pending', 'removed'], true) || ! $item->trip || $item->trip->status === 'cancelled') {
                continue;
            }
            $hubIds->push($item->trip->origin_hub_id, $item->trip->destination_hub_id);
        }
        $hubIds->push($parcel->current_hub_id);
        $hubIds = $hubIds->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $owner = \App\Models\LogisticsHub::whereIn('id', $hubIds)->pluck('sorting_center_id', 'id');

        return $hubIds->map(fn ($id) => (int) ($owner[$id] ?? 0))->filter()->values();
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
