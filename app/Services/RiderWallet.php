<?php

namespace App\Services;

use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\RiderRemittance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A rider's job history, pay and COD cash (A24, A33):
 * ₱30 per completed pickup, ₱50 per successful delivery, ₱0 per failed attempt.
 * COD collected stays on the rider until the logistics company confirms a remittance.
 *
 * Pay is kept out of the COD cash: a remittance hands over the COD minus the rider's unpaid pay (earned since
 * NETTING_SINCE, when this started; older pay was settled by the companies directly). Pay above the COD
 * on hand stays owed to the rider and is kept from the next remittance.
 */
class RiderWallet
{
    public const NETTING_SINCE = '2026-11-04 11:00:00';

    /** Every job once: ['at', 'type', 'tracking', 'order', 'result', 'pay', 'cod'] newest first. */
    public function history(Rider $rider, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $rows = collect();

        foreach (ParcelPickup::where('rider_id', $rider->id)->whereIn('status', ['picked_up', 'received'])->with('parcel.order')->get() as $p) {
            $rows->push(['at' => $p->picked_up_at, 'type' => 'Pickup', 'tracking' => $p->parcel?->tracking_number, 'order' => $p->parcel?->order?->number(),
                'result' => $p->status === 'received' ? 'Delivered to hub' : 'Picked up', 'pay' => RiderDispatch::PAY_PICKUP, 'cod' => 0.0]);
        }
        foreach (ParcelDelivery::where('rider_id', $rider->id)->whereIn('status', ['delivered', 'failed', 'returned'])->with('parcel.order')->get() as $d) {
            $ok = $d->status === 'delivered';
            $rows->push(['at' => $ok ? $d->delivered_at : $d->updated_at, 'type' => 'Delivery', 'tracking' => $d->parcel?->tracking_number, 'order' => $d->parcel?->order?->number(),
                'result' => $ok ? 'Delivered' : 'Failed (' . ($d->failure_reason ?: $d->remarks ?: 'no reason') . ') · attempt ' . $d->attempt,
                'pay' => $ok ? RiderDispatch::PAY_DELIVERY : 0.0, 'cod' => (float) $d->cod_collected]);
        }

        return $rows->filter(fn ($r) => $r['at']
                && (! $from || $r['at']->gte($from))
                && (! $to || $r['at']->lte($to)))
            ->sortByDesc(fn ($r) => $r['at']->timestamp)->values();
    }

    public function pay(Rider $rider, CarbonInterface $from, CarbonInterface $to): float
    {
        return round($this->history($rider, $from, $to)->sum('pay'), 2);
    }

    public function codCollected(Rider $rider): float
    {
        return round((float) ParcelDelivery::where('rider_id', $rider->id)->where('status', 'delivered')->sum('cod_collected'), 2);
    }

    /** COD cash still with the rider: collected minus confirmed remittances (cash handed over + pay kept from it). */
    public function codBalance(Rider $rider): float
    {
        $confirmed = RiderRemittance::where('rider_id', $rider->id)->where('status', 'confirmed');
        return round($this->codCollected($rider) - (float) (clone $confirmed)->sum('amount') - (float) $confirmed->sum('pay_kept'), 2);
    }

    /** Pay earned since netting began that hasn't been kept out of a remittance yet (pending ones count as kept). */
    public function unpaidPay(Rider $rider): float
    {
        $earned = $this->pay($rider, \Illuminate\Support\Carbon::parse(self::NETTING_SINCE, config('app.business_timezone')), now()->addMinute());
        $kept = (float) RiderRemittance::where('rider_id', $rider->id)->whereIn('status', ['pending', 'confirmed'])->sum('pay_kept');

        return round(max(0, $earned - $kept), 2);
    }

    /**
     * The next remittance, worked out: ['cod' => COD on hand, 'pay' => pay kept out of it, 'remit' => cash to hand over,
     * 'owed' => pay still owed to the rider after this one].
     */
    public function toRemit(Rider $rider): array
    {
        $cod = $this->codBalance($rider);
        $unpaid = $this->unpaidPay($rider);
        $pay = round(min($unpaid, max(0, $cod)), 2);

        return ['cod' => $cod, 'pay' => $pay, 'remit' => round(max(0, $cod - $pay), 2), 'owed' => round($unpaid - $pay, 2)];
    }

    public function pendingRemittance(Rider $rider): float
    {
        return round((float) RiderRemittance::where('rider_id', $rider->id)->where('status', 'pending')->sum('amount'), 2);
    }

    /** COD deliveries whose cash makes up the balance (newest first). */
    public function codOrders(Rider $rider): Collection
    {
        return ParcelDelivery::where('rider_id', $rider->id)->where('status', 'delivered')->where('cod_collected', '>', 0)
            ->with('parcel.order')->latest('delivered_at')->get();
    }
}
