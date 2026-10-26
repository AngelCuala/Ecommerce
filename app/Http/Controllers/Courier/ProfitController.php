<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Services\RiderDispatch;
use App\Services\RiderWallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * My Earnings: what the courier earned, from their logistics company's jobs (RiderWallet: pickups and
 * deliveries at the rider rates) plus any jobs from the older courier marketplace (deliveries.delivery_fee).
 */
class ProfitController extends Controller
{
    public function index(RiderWallet $wallet)
    {
        $rider   = Rider::where('user_id', auth()->id())->where('application_status', 'approved')->first();
        $courier = auth()->user()->courier;
        abort_unless($rider || ($courier && $courier->isApproved()), 403, 'An approved courier profile is required to use the courier portal.');

        $rows = $this->rows($wallet, $rider, $courier?->isApproved() ? $courier : null);
        $paid = $rows->where('pay', '>', 0);
        $sum  = fn (Collection $c) => round((float) $c->sum('pay'), 2);
        $since = fn (Carbon $from) => $paid->filter(fn ($r) => $r['at']->gte($from));

        // Work in progress: what open jobs will pay once completed.
        $pending = 0.0;
        if ($rider) {
            $pending += ParcelPickup::where('rider_id', $rider->id)->where('status', 'assigned')->count() * RiderDispatch::PAY_PICKUP
                + ParcelDelivery::where('rider_id', $rider->id)->whereIn('status', ParcelDelivery::OPEN)->count() * RiderDispatch::PAY_DELIVERY;
        }
        if ($courier?->isApproved()) {
            $pending += (float) Delivery::where('courier_id', $courier->id)->whereIn('status', ['accepted', 'picked_up', 'in_transit'])->sum('delivery_fee');
        }

        $stats = [
            'total'      => $sum($paid),
            'today'      => $sum($since(now()->startOfDay())),
            'week'       => $sum($since(now()->startOfWeek())),
            'month'      => $sum($since(now()->startOfMonth())),
            'pickups'    => $paid->where('type', 'Pickup')->count(),
            'deliveries' => $paid->where('type', 'Delivery')->count(),
            'average'    => $paid->count() ? round($sum($paid) / $paid->count(), 2) : 0.0,
            'pending'    => round($pending, 2),
        ];

        // The last six months, oldest first, for the bar chart.
        $months = collect(range(5, 0))->map(function ($ago) use ($paid, $sum) {
            $start = now()->startOfMonth()->subMonths($ago);
            $in = $paid->filter(fn ($r) => $r['at']->isSameMonth($start));
            return (object) ['label' => $start->format('M'), 'year' => $start->format('Y'), 'total' => $sum($in), 'jobs' => $in->count()];
        });

        return view('courier.profit', [
            'stats'  => $stats,
            'months' => $months,
            'peak'   => max(1, $months->max('total')),
            'recent' => $rows->take(15),
            'rider'  => $rider,
        ]);
    }

    /** Everything earned so far (the "Total earned" figure); the dashboard shows the same number. */
    public function totalEarned(RiderWallet $wallet, ?Rider $rider, $courier): float
    {
        return round((float) $this->rows($wallet, $rider, $courier)->where('pay', '>', 0)->sum('pay'), 2);
    }

    /** Every job with its pay, newest first: ['at', 'type', 'tracking', 'order', 'result', 'pay']. */
    private function rows(RiderWallet $wallet, ?Rider $rider, $courier): Collection
    {
        $rows = $rider ? $wallet->history($rider) : collect();
        if ($courier) {
            $rows = $rows->concat(Delivery::where('courier_id', $courier->id)->where('status', 'delivered')->whereNotNull('delivered_at')->with('order')->get()
                ->map(fn (Delivery $d) => ['at' => $d->delivered_at, 'type' => 'Delivery', 'tracking' => $d->order?->tracking_number,
                    'order' => '#' . str_pad((string) $d->order_id, 6, '0', STR_PAD_LEFT), 'result' => 'Delivered', 'pay' => (float) $d->delivery_fee, 'cod' => 0.0]));
        }

        return $rows->sortByDesc(fn ($r) => $r['at']->timestamp)->values();
    }
}
