<?php

namespace App\Services;

use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\ActivityLog;
use App\Models\RiderRemittance;
use App\Models\RiderWalletEntry;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\SortingCenters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * A rider's job history, pay and COD cash (A24, A33):
 * ₱30 per completed pickup, ₱50 per successful delivery, ₱0 per failed attempt.
 * COD collected stays on the rider until the logistics company confirms a remittance.
 *
 * E-wallet (from the rider's wallet_since): the hub sends the rider's pay into their wallet with "Send pay", and the
 * rider remits all the COD they collect.
 *
 * Before that, pay was kept out of the COD cash: a remittance handed over the COD minus the rider's unpaid pay
 * (earned from NETTING_SINCE up to wallet_since; older pay was settled by the companies directly). Pay above the
 * COD on hand stayed owed to the rider and is kept from the next remittance.
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

    /** Pay earned between NETTING_SINCE and the wallet that hasn't been kept out of a remittance yet (pending ones count as kept). */
    public function unpaidPay(Rider $rider): float
    {
        $since = \Illuminate\Support\Carbon::parse(self::NETTING_SINCE, config('app.business_timezone'));
        $until = $this->walletSince($rider)->copy()->subSecond();
        $earned = $until->gt($since) ? $this->pay($rider, $since, $until) : 0.0;
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

    // ── E-wallet ────────────────────────────────────────────────

    /** From when the rider's pay goes to the wallet (riders created before it existed: when it was switched on). */
    public function walletSince(Rider $rider): CarbonInterface
    {
        return $rider->wallet_since ?? $rider->created_at ?? now();
    }

    /** Pay earned for the wallet so far (every paid job since walletSince). */
    public function walletEarned(Rider $rider): float
    {
        return $this->pay($rider, $this->walletSince($rider), now()->addMinute());
    }

    /** Money in the wallet (a pending cash-out is already taken out; a rejected one is back). */
    public function walletBalance(Rider $rider): float
    {
        return round((float) RiderWalletEntry::where('rider_id', $rider->id)->counted()->sum('amount'), 2);
    }

    // ── GCash cash-out ──────────────────────────────────────────

    /** Smallest cash-out (GCash transfers below this aren't worth the hub's time). */
    public const CASHOUT_MIN = 100.00;

    public function pendingCashout(Rider $rider): ?RiderWalletEntry
    {
        return RiderWalletEntry::where('rider_id', $rider->id)->where('type', 'cashout')->where('status', 'pending')->first();
    }

    /** The rider asks for money out of the wallet to their GCash; it leaves the balance at once (one request at a time). */
    public function requestCashout(Rider $rider, float $amount, string $gcashNumber, string $gcashName): RiderWalletEntry
    {
        return DB::transaction(function () use ($rider, $amount, $gcashNumber, $gcashName) {
            $rider = Rider::lockForUpdate()->findOrFail($rider->id);
            if (! $rider->sorting_center_id) {
                throw new RuntimeException('You have no logistics company to pay you out.');
            }
            if ($this->pendingCashout($rider)) {
                throw new RuntimeException('You already have a cash-out waiting. Wait until ' . $rider->companyName() . ' sends it.');
            }
            $amount = round($amount, 2);
            $balance = $this->walletBalance($rider);
            if ($amount < self::CASHOUT_MIN) {
                throw new RuntimeException('The smallest cash-out is ₱' . number_format(self::CASHOUT_MIN, 2) . '.');
            }
            if ($amount > $balance) {
                throw new RuntimeException('You only have ₱' . number_format($balance, 2) . ' in your wallet.');
            }

            $entry = RiderWalletEntry::create(['rider_id' => $rider->id, 'sorting_center_id' => $rider->sorting_center_id, 'type' => 'cashout',
                'status' => 'pending', 'amount' => -$amount, 'gcash_number' => $gcashNumber, 'gcash_name' => $gcashName,
                'note' => 'To GCash ' . $gcashNumber, 'created_by' => $rider->user_id]);

            UserNotification::create(['user_id' => $rider->sorting_center_id, 'type' => 'warning', 'title' => 'Rider cash-out request',
                'body' => "{$rider->full_name} asked to cash out ₱" . number_format($amount, 2) . " to GCash {$gcashNumber} ({$gcashName}).",
                'link' => route('sc.rider-pay')]);
            ActivityLog::note('Cash-out request ₱' . number_format($amount, 2) . ' to GCash ' . $gcashNumber, $rider);

            return $entry;
        });
    }

    /** The hub sent the money by GCash: it records the GCash reference number. */
    public function markCashoutPaid(RiderWalletEntry $entry, User $hub, string $reference): RiderWalletEntry
    {
        return $this->processCashout($entry, $hub, function (RiderWalletEntry $e, Rider $rider) use ($hub, $reference) {
            $e->update(['status' => 'paid', 'reference' => $reference, 'processed_by' => $hub->id, 'processed_at' => now()]);
            UserNotification::create(['user_id' => $rider->user_id, 'type' => 'success', 'title' => 'Cash-out sent',
                'body' => '₱' . number_format(abs((float) $e->amount), 2) . " was sent to your GCash {$e->gcash_number}. Reference: {$reference}.",
                'link' => route('courier.rider.wallet')]);
        });
    }

    /** The hub can't send it (e.g. wrong GCash number): the money goes back into the wallet. */
    public function rejectCashout(RiderWalletEntry $entry, User $hub, string $reason): RiderWalletEntry
    {
        return $this->processCashout($entry, $hub, function (RiderWalletEntry $e, Rider $rider) use ($hub, $reason) {
            $e->update(['status' => 'rejected', 'reject_reason' => $reason, 'processed_by' => $hub->id, 'processed_at' => now()]);
            UserNotification::create(['user_id' => $rider->user_id, 'type' => 'warning', 'title' => 'Cash-out rejected',
                'body' => '₱' . number_format(abs((float) $e->amount), 2) . " is back in your wallet. Reason: {$reason}",
                'link' => route('courier.rider.wallet')]);
        });
    }

    private function processCashout(RiderWalletEntry $entry, User $hub, \Closure $do): RiderWalletEntry
    {
        return DB::transaction(function () use ($entry, $hub, $do) {
            $e = RiderWalletEntry::lockForUpdate()->findOrFail($entry->id);
            if ($e->type !== 'cashout' || (int) $e->sorting_center_id !== (int) $hub->id) {
                throw new RuntimeException('This cash-out is not for your sorting center.');
            }
            if ($e->status !== 'pending') {
                throw new RuntimeException('This cash-out was already ' . ($e->status === 'paid' ? 'paid' : 'rejected') . '.');
            }
            $rider = Rider::findOrFail($e->rider_id);
            $do($e, $rider);
            ActivityLog::note('Cash-out ₱' . number_format(abs((float) $e->amount), 2) . ' ' . $e->status . ' for ' . $rider->full_name, $rider);

            return $e->fresh();
        });
    }

    /** Pay the hub has sent in so far. */
    public function walletSent(Rider $rider): float
    {
        return round((float) RiderWalletEntry::where('rider_id', $rider->id)->where('type', 'pay')->sum('amount'), 2);
    }

    /** Pay earned but not sent to the wallet yet. */
    public function toSend(Rider $rider): float
    {
        return round(max(0, $this->walletEarned($rider) - $this->walletSent($rider)), 2);
    }

    /** The paid jobs not sent yet: those since the last "Send pay" (or since the wallet started), newest first. */
    public function unsentJobs(Rider $rider): Collection
    {
        $since = $this->walletSince($rider);
        $last = RiderWalletEntry::where('rider_id', $rider->id)->where('type', 'pay')->latest('id')->value('created_at');
        $from = $last && $last->gt($since) ? $last->copy()->addSecond() : $since;

        return $this->history($rider, $from, now()->addMinute())->where('pay', '>', 0)->values();
    }

    /** The hub sends everything the rider has earned and not been sent yet into the rider's wallet. */
    public function sendPay(Rider $rider, User $hub, User $actor): RiderWalletEntry
    {
        return DB::transaction(function () use ($rider, $hub, $actor) {
            $rider = Rider::lockForUpdate()->findOrFail($rider->id);
            if ((int) $rider->sorting_center_id !== (int) $hub->id) {
                throw new RuntimeException('This rider is not one of your riders.');
            }
            $amount = $this->toSend($rider);
            if ($amount <= 0) {
                throw new RuntimeException("{$rider->full_name} has no pay waiting to be sent.");
            }
            $jobs = $this->unsentJobs($rider)->count();
            $entry = RiderWalletEntry::create(['rider_id' => $rider->id, 'sorting_center_id' => $hub->id, 'type' => 'pay',
                'amount' => $amount, 'jobs' => $jobs, 'note' => 'Pay for ' . $jobs . ' ' . Str::plural('job', $jobs), 'created_by' => $actor->id]);

            UserNotification::create(['user_id' => $rider->user_id, 'type' => 'success', 'title' => 'Pay received',
                'body' => '₱' . number_format($amount, 2) . ' was sent to your wallet by ' . SortingCenters::displayName($hub) . '.',
                'link' => route('courier.rider.wallet')]);
            ActivityLog::note('Sent ₱' . number_format($amount, 2) . ' pay to ' . $rider->full_name . "'s wallet", $rider);

            return $entry;
        });
    }

    /** COD deliveries whose cash makes up the balance (newest first). */
    public function codOrders(Rider $rider): Collection
    {
        return ParcelDelivery::where('rider_id', $rider->id)->where('status', 'delivered')->where('cod_collected', '>', 0)
            ->with('parcel.order')->latest('delivered_at')->get();
    }
}
