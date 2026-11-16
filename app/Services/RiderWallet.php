<?php

namespace App\Services;

use App\Models\ParcelDelivery;
use App\Models\PayoutAccount;
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
 * 30% of the parcel's shipping fee per completed pickup and 30% per successful delivery, ₱0 per failed attempt
 * (₱30 / ₱50 for jobs before RiderDispatch::SHARE_SINCE).
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
                'result' => $p->status === 'received' ? 'Delivered to hub' : 'Picked up', 'pay' => RiderDispatch::pickupPay($p->parcel, $p->picked_up_at), 'cod' => 0.0]);
        }
        foreach (ParcelDelivery::where('rider_id', $rider->id)->whereIn('status', ['delivered', 'failed', 'returned'])->with('parcel.order')->get() as $d) {
            $ok = $d->status === 'delivered';
            $rows->push(['at' => $ok ? $d->delivered_at : $d->updated_at, 'type' => 'Delivery', 'tracking' => $d->parcel?->tracking_number, 'order' => $d->parcel?->order?->number(),
                'result' => $ok ? 'Delivered' : 'Failed (' . ($d->failure_reason ?: $d->remarks ?: 'no reason') . ') · attempt ' . $d->attempt,
                'pay' => $ok ? RiderDispatch::deliveryPay($d->parcel, $d->delivered_at) : 0.0, 'cod' => (float) $d->cod_collected]);
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
        return round($this->codCollected($rider) - (float) (clone $confirmed)->sum('amount') - (float) (clone $confirmed)->sum('pay_kept')
            - (float) $confirmed->sum('deducted'), 2);
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

    /** Pay earned for "Send pay" (every paid job from walletSince until the ledger started; later jobs are ledger lines). */
    public function walletEarned(Rider $rider): float
    {
        $until = $this->ledgerSince()->lt(now()) ? $this->ledgerSince()->copy()->subSecond() : now()->addMinute();

        return $until->gt($this->walletSince($rider)) ? $this->pay($rider, $this->walletSince($rider), $until) : 0.0;
    }

    /** Money in the wallet (a pending cash-out is already taken out; a rejected one is back). */
    public function walletBalance(Rider $rider): float
    {
        return round((float) RiderWalletEntry::where('rider_id', $rider->id)->counted()->sum('amount'), 2);
    }

    // ── GCash cash-out ──────────────────────────────────────────

    /** Smallest withdrawal: none (any amount above ₱0 up to the available balance), and no fee. */
    public const CASHOUT_MIN = 0.01;

    public function pendingCashout(Rider $rider): ?RiderWalletEntry
    {
        return RiderWalletEntry::where('rider_id', $rider->id)->where('type', 'cashout')->where('status', 'pending')->first();
    }

    /**
     * The rider withdraws to GCash, Maya or a bank: the balance check and the lock (a −amount line, Processing) happen in
     * one transaction, so the available balance drops at once and can't be withdrawn twice (one request at a time).
     */
    public function requestCashout(Rider $rider, float $amount, string $gcashNumber, string $gcashName, string $method = 'gcash', ?string $bankName = null): RiderWalletEntry
    {
        $this->syncCredits($rider);

        return DB::transaction(function () use ($rider, $amount, $gcashNumber, $gcashName, $method, $bankName) {
            $rider = Rider::lockForUpdate()->findOrFail($rider->id);
            if (! $rider->sorting_center_id) {
                throw new RuntimeException('You have no logistics company to pay you out.');
            }
            if ($this->pendingCashout($rider)) {
                throw new RuntimeException('You already have a withdrawal processing. Wait until ' . $rider->companyName() . ' sends it.');
            }
            $amount = round($amount, 2);
            $balance = $this->walletBalance($rider);
            if ($amount < self::CASHOUT_MIN) {
                throw new RuntimeException('Enter an amount above ₱0.');
            }
            if ($amount > $balance) {
                throw new RuntimeException('Not enough available balance: you have ₱' . number_format($balance, 2) . '.');
            }
            $where = $method === 'bank' ? ($bankName ?: 'Bank') : (PayoutAccount::METHODS[$method] ?? 'GCash');

            $entry = RiderWalletEntry::create(['rider_id' => $rider->id, 'sorting_center_id' => $rider->sorting_center_id, 'type' => 'cashout',
                'status' => 'pending', 'amount' => -$amount, 'gcash_number' => $gcashNumber, 'gcash_name' => $gcashName,
                'payout_method' => $method, 'bank_name' => $method === 'bank' ? $bankName : null,
                'note' => "To $where $gcashNumber", 'created_by' => $rider->user_id]);

            UserNotification::create(['user_id' => $rider->sorting_center_id, 'type' => 'warning', 'title' => 'Rider withdrawal request',
                'body' => "{$rider->full_name} asked to withdraw ₱" . number_format($amount, 2) . " to $where {$gcashNumber} ({$gcashName}).",
                'link' => route('sc.rider-pay')]);
            ActivityLog::note('Withdrawal request ₱' . number_format($amount, 2) . " to $where " . $gcashNumber, $rider);

            return $entry;
        });
    }

    /** The hub sent the money by GCash: it records the GCash reference number. */
    public function markCashoutPaid(RiderWalletEntry $entry, User $hub, string $reference): RiderWalletEntry
    {
        return $this->processCashout($entry, $hub, function (RiderWalletEntry $e, Rider $rider) use ($hub, $reference) {
            $e->update(['status' => 'paid', 'reference' => $reference, 'processed_by' => $hub->id, 'processed_at' => now()]);
            UserNotification::create(['user_id' => $rider->user_id, 'type' => 'success', 'title' => 'Withdrawal completed',
                'body' => '₱' . number_format(abs((float) $e->amount), 2) . ' was sent to your ' . $e->destination() . ". Reference: {$reference}.",
                'link' => route('courier.rider.wallet')]);
        });
    }

    /** The hub can't send it (e.g. wrong GCash number): the money goes back into the wallet. */
    public function rejectCashout(RiderWalletEntry $entry, User $hub, string $reason): RiderWalletEntry
    {
        return $this->processCashout($entry, $hub, function (RiderWalletEntry $e, Rider $rider) use ($hub, $reason) {
            $e->update(['status' => 'rejected', 'reject_reason' => $reason, 'processed_by' => $hub->id, 'processed_at' => now()]);
            UserNotification::create(['user_id' => $rider->user_id, 'type' => 'warning', 'title' => 'Withdrawal failed',
                'body' => '₱' . number_format(abs((float) $e->amount), 2) . " is back in your wallet. Check your account details. Reason: {$reason}",
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

        $until = $this->ledgerSince()->lt(now()) ? $this->ledgerSince()->copy()->subSecond() : now()->addMinute();

        return $until->gt($from) ? $this->history($rider, $from, $until)->where('pay', '>', 0)->values() : collect();
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

    // ── Ledger (PAYMENTS_SPEC §6) ───────────────────────────────

    /** When the ledger started (the same switch as the 30/30/40 split). */
    public function ledgerSince(): CarbonInterface
    {
        return RiderDispatch::shareSince();
    }

    /**
     * Adds the missing ledger lines and sets which COD lines are held. Safe to run any time (each job is one line):
     *  - a pickup: once the hub received the parcel
     *  - a prepaid delivery: once delivered
     *  - a COD delivery: once the hub confirmed the remittance that covers it; held while a shortage is open
     */
    public function syncCredits(Rider $rider): void
    {
        if ($rider->isTruck() || ! $rider->sorting_center_id) {
            return;
        }
        $since = $this->ledgerSince();
        if ($since->gt(now())) {
            return;
        }
        $have = RiderWalletEntry::where('rider_id', $rider->id)->whereIn('type', ['pickup_credit', 'delivery_credit'])
            ->get(['type', 'source_type', 'source_id'])->map(fn ($e) => "{$e->type}:{$e->source_type}:{$e->source_id}")->flip();
        $add = function (string $type, string $sourceType, $job, float $pay, $at, string $status, string $note) use ($rider, $have) {
            if ($pay <= 0 || isset($have["$type:$sourceType:{$job->id}"])) {
                return;
            }
            RiderWalletEntry::firstOrCreate(['rider_id' => $rider->id, 'type' => $type, 'source_type' => $sourceType, 'source_id' => $job->id], [
                'sorting_center_id' => $rider->sorting_center_id, 'parcel_id' => $job->parcel_id, 'earned_at' => $at,
                'status' => $status, 'amount' => $pay, 'jobs' => 1, 'note' => $note]);
        };

        $pickups = ParcelPickup::where('rider_id', $rider->id)->where('status', 'received')->where('received_at', '>=', $since)->with('parcel.order')->get();
        foreach ($pickups as $p) {
            $add('pickup_credit', 'pickup', $p, RiderDispatch::pickupPay($p->parcel, $p->received_at), $p->received_at, 'available',
                'Pickup ' . ($p->parcel?->tracking_number ?? '') . ' received at the hub');
        }

        $remittances = RiderRemittance::where('rider_id', $rider->id)->where('status', 'confirmed')->orderBy('id')->get(['id', 'created_at']);
        $deliveries = ParcelDelivery::where('rider_id', $rider->id)->where('status', 'delivered')->where('delivered_at', '>=', $since)->with('parcel.order')->get();
        foreach ($deliveries as $d) {
            $pay = RiderDispatch::deliveryPay($d->parcel, $d->delivered_at);
            if ((float) $d->cod_collected <= 0) {
                $add('delivery_credit', 'delivery', $d, $pay, $d->delivered_at, 'available', 'Delivery ' . ($d->parcel?->tracking_number ?? '') . ' (prepaid)');
            } elseif ($remittances->first(fn ($r) => $r->created_at->gte($d->delivered_at))) {
                $add('delivery_credit', 'delivery_cod', $d, $pay, $d->delivered_at, 'available', 'Delivery ' . ($d->parcel?->tracking_number ?? '') . ' (COD remitted)');
            }
        }

        // Held: COD lines covered by a remittance that is still short (the rider pays the gap or the hub deducts it).
        $open = $this->openShortage($rider);
        $cod = RiderWalletEntry::where('rider_id', $rider->id)->where('type', 'delivery_credit')->where('source_type', 'delivery_cod');
        if ($open) {
            (clone $cod)->where('earned_at', '<=', $open->created_at)->where('status', '!=', 'held')->update(['status' => 'held']);
            (clone $cod)->where('earned_at', '>', $open->created_at)->where('status', 'held')->update(['status' => 'available']);
        } else {
            (clone $cod)->where('status', 'held')->update(['status' => 'available']);
        }
    }

    /** The latest confirmed remittance, if it is short and not settled (a later full remittance settles earlier ones). */
    public function openShortage(Rider $rider): ?RiderRemittance
    {
        $last = RiderRemittance::where('rider_id', $rider->id)->where('status', 'confirmed')->latest('id')->first();

        return $last && $last->shortfall() > 0 ? $last : null;
    }

    /** ['available', 'held', 'expected', 'cash_to_remit', 'to_send' (older jobs for "Send pay")]. */
    public function balances(Rider $rider): array
    {
        $this->syncCredits($rider);

        return [
            'available'     => $this->walletBalance($rider),
            'held'          => round((float) RiderWalletEntry::where('rider_id', $rider->id)->where('status', 'held')->sum('amount'), 2),
            'expected'      => round((float) $this->expectedJobs($rider)->sum('pay'), 2),
            'cash_to_remit' => max(0.0, $this->codBalance($rider)),
            'to_send'       => $this->toSend($rider),
        ];
    }

    /** Jobs done since the ledger started that are not in the wallet yet (COD deliveries waiting for the remittance). */
    public function expectedJobs(Rider $rider): Collection
    {
        if ($this->ledgerSince()->gt(now())) {
            return collect();
        }
        $credited = RiderWalletEntry::where('rider_id', $rider->id)->where('type', 'delivery_credit')->pluck('source_id')->flip();

        return ParcelDelivery::where('rider_id', $rider->id)->where('status', 'delivered')->where('cod_collected', '>', 0)
            ->where('delivered_at', '>=', $this->ledgerSince())->with('parcel.order')->latest('delivered_at')->get()
            ->reject(fn ($d) => isset($credited[$d->id]))
            ->map(fn ($d) => ['at' => $d->delivered_at, 'tracking' => $d->parcel?->tracking_number, 'pay' => RiderDispatch::deliveryPay($d->parcel, $d->delivered_at),
                'cod' => (float) $d->cod_collected])->values();
    }

    /**
     * The hub counted the cash of a remittance: the counted amount is what was received. Short: the gap stays on the
     * rider's cash to remit and the COD deliveries it covers are held until the rider pays it or the hub deducts it.
     */
    public function confirmRemittance(RiderRemittance $remittance, User $actor, ?float $counted = null): RiderRemittance
    {
        return DB::transaction(function () use ($remittance, $actor, $counted) {
            $r = RiderRemittance::lockForUpdate()->findOrFail($remittance->id);
            if ($r->status !== 'pending') {
                throw new RuntimeException('This remittance is already confirmed.');
            }
            $due = round((float) $r->expected_amount - (float) $r->pay_kept, 2);
            $counted = round($counted ?? (float) $r->amount, 2);
            if ($counted < 0 || $counted > $due) {
                throw new RuntimeException('Enter the cash you counted: ₱0 to ₱' . number_format($due, 2) . '.');
            }
            $r->update(['amount' => $counted, 'counted_amount' => $counted, 'status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $rider = $r->rider;
            $this->syncCredits($rider);

            $short = $r->shortfall();
            UserNotification::create(['user_id' => $rider->user_id, 'title' => $short > 0 ? 'COD remittance short' : 'COD remittance confirmed',
                'body' => 'Your remittance was counted: ₱' . number_format($counted, 2) . '.'
                    . ($short > 0 ? ' ₱' . number_format($short, 2) . ' is short: hand it over, or ' . $rider->companyName() . ' takes it from your earnings. The pay for those deliveries is on hold until then.'
                        : ' The pay for those deliveries is in your wallet.'),
                'type' => $short > 0 ? 'warning' : 'success', 'link' => route('courier.rider.wallet')]);
            ActivityLog::change('Pending ₱' . number_format((float) $remittance->amount, 2), 'Confirmed ₱' . number_format($counted, 2),
                $short > 0 ? 'Short by ₱' . number_format($short, 2) : null);
            ActivityLog::note('Confirmed ₱' . number_format($counted, 2) . ' COD from ' . $rider->full_name . ($short > 0 ? ' (short ₱' . number_format($short, 2) . ')' : ''), $r);

            return $r->fresh();
        });
    }

    /** The hub doesn't accept a remittance (e.g. the cash wasn't handed over): it doesn't count; the rider remits again. */
    public function rejectRemittance(RiderRemittance $remittance, User $actor, string $reason): RiderRemittance
    {
        return DB::transaction(function () use ($remittance, $actor, $reason) {
            $r = RiderRemittance::lockForUpdate()->findOrFail($remittance->id);
            if ($r->status !== 'pending') {
                throw new RuntimeException('This remittance was already ' . ($r->status === 'confirmed' ? 'accepted' : 'rejected') . '.');
            }
            $r->update(['status' => 'rejected', 'reject_reason' => $reason, 'rejected_by' => $actor->id, 'rejected_at' => now()]);
            $rider = $r->rider;
            UserNotification::create(['user_id' => $rider->user_id, 'type' => 'warning', 'title' => 'COD remittance rejected',
                'body' => 'Your remittance ' . $r->receiptNumber() . ' of ₱' . number_format((float) $r->amount, 2) . " was not accepted: {$reason}. The COD is still yours to remit: send it again.",
                'link' => route('courier.rider.cod')]);
            ActivityLog::note('Rejected COD remittance ' . $r->receiptNumber() . ' from ' . $rider->full_name . ": $reason", $r);

            return $r->fresh();
        });
    }

    /** The hub takes an open shortage from the rider's earnings: a −shortage line, and the held pay is released. */
    public function deductShortage(RiderRemittance $remittance, User $actor): float
    {
        return DB::transaction(function () use ($remittance, $actor) {
            $r = RiderRemittance::lockForUpdate()->findOrFail($remittance->id);
            $rider = $r->rider;
            $open = $this->openShortage($rider);
            if (! $open || $open->id !== $r->id) {
                throw new RuntimeException('This remittance has no open shortage.');
            }
            $short = $r->shortfall();
            RiderWalletEntry::create(['rider_id' => $rider->id, 'sorting_center_id' => $rider->sorting_center_id, 'type' => 'shortage_adjustment',
                'source_type' => 'remittance', 'source_id' => $r->id, 'earned_at' => now(), 'status' => 'available', 'amount' => -$short,
                'note' => 'Shortage on remittance ' . $r->receiptNumber() . ' taken from earnings', 'created_by' => $actor->id]);
            $r->update(['deducted' => round((float) $r->deducted + $short, 2), 'settled_by' => $actor->id, 'settled_at' => now()]);
            $this->syncCredits($rider);
            UserNotification::create(['user_id' => $rider->user_id, 'type' => 'info', 'title' => 'Shortage taken from your earnings',
                'body' => '₱' . number_format($short, 2) . ' (remittance ' . $r->receiptNumber() . ') was taken from your wallet. Your held pay is available again.',
                'link' => route('courier.rider.wallet')]);
            ActivityLog::note('Deducted ₱' . number_format($short, 2) . ' shortage from ' . $rider->full_name . "'s earnings", $r);

            return $short;
        });
    }

    /** COD deliveries whose cash makes up the balance (newest first). */
    public function codOrders(Rider $rider): Collection
    {
        return ParcelDelivery::where('rider_id', $rider->id)->where('status', 'delivered')->where('cod_collected', '>', 0)
            ->with('parcel.order')->latest('delivered_at')->get();
    }
}
