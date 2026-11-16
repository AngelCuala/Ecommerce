<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\HubRemittance;
use App\Models\HubRemittanceItem;
use App\Models\ParcelDelivery;
use App\Models\Rider;
use App\Models\RiderRemittance;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\SortingCenters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The hub remits the item price to ALVY with a report (PAYMENTS_SPEC §3.1 step 5, §3.2). A delivery is ready once
 * the money is with the hub or ALVY:
 *   COD      the hub confirmed the rider's remittance that covers it: due = COD collected − the parcel's shipping fee
 *   prepaid  delivered (ALVY was paid at checkout): due = − the parcel's shipping fee (ALVY owes the hub)
 * The report has one line per delivery; ALVY matches each line to its order (a COD collected that differs from the
 * order's COD is flagged), records what it received, and confirms it or flags it.
 */
class HubRemittances
{
    /** Deliveries by this company's riders that are ready to go on a remittance (newest first). */
    public function ready(int $scId): Collection
    {
        $riderIds = Rider::where('sorting_center_id', $scId)->pluck('id');
        $since = RiderDispatch::shareSince();
        if ($riderIds->isEmpty() || $since->gt(now())) {
            return collect();
        }
        $sent = HubRemittanceItem::pluck('parcel_delivery_id')->flip();
        $confirmed = RiderRemittance::whereIn('rider_id', $riderIds)->where('status', 'confirmed')->get(['rider_id', 'created_at'])->groupBy('rider_id');

        return ParcelDelivery::whereIn('rider_id', $riderIds)->where('status', 'delivered')->where('delivered_at', '>=', $since)
            ->with('parcel.order')->latest('delivered_at')->get()
            ->reject(fn ($d) => isset($sent[$d->id]))
            ->filter(fn ($d) => (float) $d->cod_collected <= 0
                || ($confirmed->get($d->rider_id) ?? collect())->contains(fn ($r) => $r->created_at->gte($d->delivered_at)))
            ->map(fn ($d) => $this->line($d))->values();
    }

    /** One report line for a delivery. */
    public function line(ParcelDelivery $d): array
    {
        $cod = (float) $d->cod_collected > 0;
        $fee = RiderDispatch::parcelFee($d->parcel);

        return [
            'delivery' => $d, 'parcel_delivery_id' => $d->id, 'parcel_id' => $d->parcel_id, 'order_id' => $d->parcel?->order_id,
            'tracking_number' => $d->parcel?->tracking_number, 'order_number' => $d->parcel?->order?->number(), 'delivered_at' => $d->delivered_at,
            'cod' => $cod, 'cod_expected' => round((float) $d->cod_amount, 2), 'cod_collected' => round((float) $d->cod_collected, 2),
            'shipping_fee' => $fee, 'amount_due' => round(($cod ? (float) $d->cod_collected : 0) - $fee, 2),
            'mismatch' => $cod && abs((float) $d->cod_collected - (float) $d->cod_amount) >= 0.01,
        ];
    }

    /** The hub sends the report (and deposits the amount to ALVY's bank, with its reference). */
    public function submit(User $sc, User $actor, ?string $depositReference): HubRemittance
    {
        return DB::transaction(function () use ($sc, $actor, $depositReference) {
            User::whereKey($sc->id)->lockForUpdate()->first();
            $lines = $this->ready($sc->id);
            if ($lines->isEmpty()) {
                throw new RuntimeException('Nothing is ready to remit: COD deliveries are ready once you confirm the rider\'s remittance.');
            }
            $amount = round($lines->sum('amount_due'), 2);
            if ($amount > 0 && ! $depositReference) {
                throw new RuntimeException('Enter the reference of your deposit to ALVY\'s bank.');
            }
            $r = HubRemittance::create(['sorting_center_id' => $sc->id, 'amount' => $amount, 'status' => 'submitted',
                'deposit_reference' => $depositReference, 'submitted_by' => $actor->id]);
            foreach ($lines as $l) {
                $r->items()->create(collect($l)->only(['parcel_delivery_id', 'parcel_id', 'order_id', 'tracking_number', 'cod', 'cod_expected', 'cod_collected',
                    'shipping_fee', 'amount_due', 'mismatch'])->all());
            }
            $name = SortingCenters::displayName($sc);
            foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
                UserNotification::create(['user_id' => $adminId, 'type' => 'info', 'title' => 'Hub remittance to check',
                    'body' => "$name sent remittance {$r->reference()}: " . ($amount >= 0 ? '₱' . number_format($amount, 2) . ' to ALVY' : 'ALVY owes ₱' . number_format(-$amount, 2))
                        . ' for ' . $lines->count() . ' delivery(ies).', 'link' => route('admin.hub-remittances.show', $r)]);
            }
            ActivityLog::note('Remittance ' . $r->reference() . ' to ALVY: ₱' . number_format($amount, 2), $r);

            return $r;
        });
    }

    /**
     * ALVY checks it: received is what arrived (negative: what ALVY paid the hub). It is confirmed when that equals
     * the report and no line is a mismatch; otherwise it is flagged with the reason.
     */
    public function review(HubRemittance $remittance, User $admin, float $received): HubRemittance
    {
        return DB::transaction(function () use ($remittance, $admin, $received) {
            $r = HubRemittance::lockForUpdate()->findOrFail($remittance->id);
            if ($r->status !== 'submitted') {
                throw new RuntimeException("{$r->reference()} was already checked.");
            }
            $received = round($received, 2);
            $diff = round($received - (float) $r->amount, 2);
            $mismatches = $r->items()->where('mismatch', true)->count();
            $problems = array_filter([
                abs($diff) >= 0.01 ? ($diff < 0 ? '₱' . number_format(-$diff, 2) . ' short' : '₱' . number_format($diff, 2) . ' over') : null,
                $mismatches ? "$mismatches order(s) where the COD collected doesn't match the order" : null,
            ]);
            $r->update(['received_amount' => $received, 'status' => $problems ? 'flagged' : 'confirmed', 'note' => $problems ? implode('; ', $problems) : null,
                'confirmed_by' => $admin->id, 'confirmed_at' => now()]);
            UserNotification::create(['user_id' => $r->sorting_center_id, 'type' => $problems ? 'warning' : 'success',
                'title' => $problems ? "Remittance {$r->reference()} flagged" : "Remittance {$r->reference()} confirmed",
                'body' => $problems ? 'ALVY found: ' . implode('; ', $problems) . '. Contact ALVY to settle it.' : 'ALVY received it and every order matches.',
                'link' => route('sc.alvy-remittances')]);
            ActivityLog::note('Hub remittance ' . $r->reference() . ' ' . $r->status . ($problems ? ': ' . implode('; ', $problems) : ''), $r);

            return $r->fresh();
        });
    }
}
