<?php

namespace App\Services;

use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\RiderJobOffer;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who does each rider job. Riders are assigned per barangay, so jobs are given, not offered:
 *  - the logistics company presses Auto-assign (all pickups, all deliveries, or one parcel): each job goes
 *    straight to an Online, Active rider of the company who covers the job's barangay (the seller's for a
 *    pickup, the buyer's for a delivery), the least busy one when there are several
 *  - a job whose barangay has no available rider waits for the company to assign it manually
 *  - logistics can always assign or reassign manually; riders have no job limit and cannot decline
 * A job is the pickup of a parcel (seller → hub) or one delivery attempt (hub → buyer).
 * (Older 60-second offers still in the database expire on their own; no new offers are made.)
 */
class RiderDispatch
{
    public const OFFER_SECONDS = 60;
    public const MAX_OFFERS    = 3;

    /** Rider pay (A33). */
    public const PAY_PICKUP   = 30.00;
    public const PAY_DELIVERY = 50.00;

    /** The job the parcel is waiting for: 'pickup', 'delivery' or null. */
    public function jobKind(Parcel $parcel): ?string
    {
        if ($parcel->transfer_status === 'outgoing' || ($parcel->order && $parcel->order->status === \App\Models\Order::CANCELLED)) {
            return null;   // moving to another hub, or nothing to deliver any more
        }
        if ($parcel->status === 'pickup_approved' && ! $parcel->pickups()->whereIn('status', ParcelPickup::OPEN)->exists()) {
            return RiderJobOffer::PICKUP;
        }
        if ($parcel->status === 'sorted' && ! $parcel->deliveries()->whereIn('status', ParcelDelivery::OPEN)->exists()) {
            return RiderJobOffer::DELIVERY;
        }
        return null;
    }

    /** Which attempt a delivery job is (pickups are always 1). */
    public function attempt(Parcel $parcel, string $kind): int
    {
        return $kind === RiderJobOffer::DELIVERY ? $parcel->deliveries()->count() + 1 : 1;
    }

    /** Riders of this company who can take a job right now. */
    /**
     * The notification sent to the rider by the last assignManually() call, or null when none could be
     * created. The hub's message only says the rider was notified when this is set.
     */
    public ?UserNotification $lastNotification = null;

    /** Riders work for a sorting center; only centers inside the service area (Laguna) dispatch them. */
    public function inServiceArea(int $scId): bool
    {
        return ! \App\Support\ServiceArea::enabled()
            || \App\Support\ServiceArea::coversLocality(User::whereKey($scId)->value('assigned_municipality_code'));
    }

    public function eligibleRiders(int $scId): Collection
    {
        if (! $this->inServiceArea($scId)) {
            return collect();
        }
        return Rider::where('sorting_center_id', $scId)->where('application_status', 'approved')->where('is_active', true)
            ->orderBy('id')->get()
            ->filter(fn (Rider $r) => $r->isOnlineNow())
            ->values();
    }

    /** Why this rider can't be given a job, or null when they can. */
    public function ineligibleReason(Rider $rider, int $scId): ?string
    {
        return match (true) {
            (int) $rider->sorting_center_id !== $scId => 'This rider belongs to another logistics company.',
            ! $this->inServiceArea($scId)            => 'This sorting center is outside the service area (' . \App\Support\ServiceArea::label() . ' only), so it cannot assign riders.',
            ! $rider->isApproved()                    => "{$rider->full_name} is not an approved rider.",
            $rider->isSuspended()                     => "{$rider->full_name} is suspended.",
            ! $rider->isOnlineNow()                   => "{$rider->full_name} is offline.",
            default                                   => null,
        };
    }

    /** Offers of this job round that were declined or let expire. */
    private function missedOffers(Parcel $parcel, string $kind, int $attempt): int
    {
        return RiderJobOffer::where('parcel_id', $parcel->id)->where('kind', $kind)->where('attempt', $attempt)
            ->whereIn('status', ['declined', 'expired'])->count();
    }

    /** 3 riders declined or let it expire (or its rider was suspended): the job waits for manual assignment. */
    public function isUnassigned(Parcel $parcel): bool
    {
        $kind = $this->jobKind($parcel);
        return $kind !== null && ($parcel->unassigned_at !== null || $this->missedOffers($parcel, $kind, $this->attempt($parcel, $kind)) >= self::MAX_OFFERS);
    }

    /**
     * A suspended rider's jobs not yet picked up go back to the Unassigned queue (A34);
     * parcels the rider already picked up stay with them.
     */
    public function releaseJobs(Rider $rider): int
    {
        return DB::transaction(function () use ($rider) {
            $n = 0;
            foreach (ParcelPickup::where('rider_id', $rider->id)->where('status', 'assigned')->get() as $pickup) {
                $pickup->update(['status' => 'cancelled', 'remarks' => 'Rider suspended.']);
                if ($p = Parcel::find($pickup->parcel_id)) {
                    app(RiderNotifier::class)->cancelled($p, $rider, RiderJobOffer::PICKUP, $pickup->id, 'Your rider account was suspended');
                }
                Parcel::whereKey($pickup->parcel_id)->update(['unassigned_at' => now()]);
                $n++;
            }
            foreach (ParcelDelivery::where('rider_id', $rider->id)->where('status', 'assigned')->get() as $delivery) {
                Parcel::whereKey($delivery->parcel_id)->update(['status' => 'sorted', 'unassigned_at' => now()]);
                $delivery->delete();   // the attempt never started
                $n++;
            }
            RiderJobOffer::where('rider_id', $rider->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            // Returns not yet handed back go back to the Return-to-Sender queue.
            $n += Parcel::where('rts_rider_id', $rider->id)->where('status', 'return_to_sender')->whereNull('returned_at')
                ->update(['rts_rider_id' => null, 'rts_assigned_at' => null]);
            return $n;
        });
    }

    public function pendingOffer(Parcel $parcel): ?RiderJobOffer
    {
        return RiderJobOffer::where('parcel_id', $parcel->id)->where('status', 'pending')->where('expires_at', '>', now())->latest('id')->first();
    }

    /**
     * Auto-assign: offer the parcel's waiting job to the next eligible rider.
     * Returns the offer, or null when nobody can take it (or it is now Unassigned).
     */
    public function offer(Parcel $parcel): ?RiderJobOffer
    {
        $this->expireStale();
        $parcel->refresh();

        $kind = $this->jobKind($parcel);
        $scId = (int) $parcel->current_sorting_center_id;
        if (! $kind || ! $scId) {
            return null;
        }
        if ($open = $this->pendingOffer($parcel)) {
            return $open;
        }

        $attempt = $this->attempt($parcel, $kind);
        if ($parcel->unassigned_at || $this->missedOffers($parcel, $kind, $attempt) >= self::MAX_OFFERS) {
            return null;   // Unassigned queue
        }

        $alreadyAsked = RiderJobOffer::where('parcel_id', $parcel->id)->where('kind', $kind)->where('attempt', $attempt)->pluck('rider_id')->all();
        $rider = $this->eligibleRiders($scId)
            ->reject(fn (Rider $r) => in_array($r->id, $alreadyAsked, true))
            // Nearest first: a rider covering the barangay of the job (the buyer's for a delivery, the seller's
            // pickup address for a pickup), then the least busy.
            ->sortBy(fn (Rider $r) => [$this->isNear($r, $parcel, $kind) ? 0 : 1, $r->activeJobsCount(), $r->id])
            ->first();
        if (! $rider) {
            return null;
        }

        $offer = RiderJobOffer::create([
            'parcel_id' => $parcel->id, 'kind' => $kind, 'attempt' => $attempt, 'rider_id' => $rider->id,
            'sorting_center_id' => $scId, 'status' => 'pending', 'expires_at' => now()->addSeconds(self::OFFER_SECONDS),
        ]);
        app(RiderNotifier::class)->offered($offer, $parcel, $rider, self::OFFER_SECONDS);

        return $offer;
    }

    /** Older offers past their 60 seconds expire (the job then waits for Auto-assign or a manual assignment). */
    public function expireStale(): int
    {
        $stale = RiderJobOffer::where('status', 'pending')->where('expires_at', '<=', now())->get();
        foreach ($stale as $offer) {
            $offer->update(['status' => 'expired']);
        }
        return $stale->count();
    }

    /** The rider accepts an offer. Throws \RuntimeException with a message for the rider when it can't be done. */
    public function accept(RiderJobOffer $offer, Rider $rider): void
    {
        // Too late: the offer expires and the job waits for the hub (done outside the transaction below).
        $offer->refresh();
        if ($offer->status === 'pending' && $offer->expires_at->isPast()) {
            $offer->update(['status' => 'expired']);
            throw new \RuntimeException('This offer expired. The hub will assign the job.');
        }

        DB::transaction(function () use ($offer, $rider) {
            $parcel = Parcel::lockForUpdate()->findOrFail($offer->parcel_id);
            $offer  = RiderJobOffer::lockForUpdate()->findOrFail($offer->id);

            if ($offer->status === 'accepted') {
                throw new \RuntimeException('You already accepted this job.');
            }
            if ($offer->status === 'expired') {
                throw new \RuntimeException('This offer expired. The hub will assign the job.');
            }
            if ($offer->status !== 'pending' || $this->jobKind($parcel) !== $offer->kind) {
                throw new \RuntimeException('This job was already assigned to another rider.');
            }

            $offer->update(['status' => 'accepted', 'responded_at' => now()]);
            $this->assign($parcel, $offer->kind, $rider, null);
        });
    }

    public function decline(RiderJobOffer $offer): void
    {
        if ($offer->status !== 'pending') {
            return;
        }
        $offer->update(['status' => $offer->expires_at->isPast() ? 'expired' : 'declined', 'responded_at' => now()]);
    }

    /** The available rider who covers the job's barangay (least busy first), or null when there is none. */
    public function barangayRider(Parcel $parcel, ?string $kind = null, ?Collection $eligible = null): ?Rider
    {
        $kind ??= $this->jobKind($parcel);
        $scId = (int) $parcel->current_sorting_center_id;
        if (! $kind || ! $scId) {
            return null;
        }

        return ($eligible ?? $this->eligibleRiders($scId))
            ->filter(fn (Rider $r) => $this->isNear($r, $parcel, $kind))
            ->sortBy(fn (Rider $r) => [$r->activeJobsCount(), $r->id])
            ->first();
    }

    /**
     * Auto-assign one parcel: its waiting job goes straight to the barangay's rider (no offer to accept).
     * Returns the rider, or null when the barangay has no available rider (the job waits for manual assignment).
     */
    public function autoAssign(Parcel $parcel, User $by): ?Rider
    {
        $this->expireStale();
        $rider = $this->barangayRider($parcel->fresh());
        if (! $rider) {
            return null;
        }
        $this->assignManually($parcel, $rider, $by);

        return $rider;
    }

    /**
     * Auto-assign every waiting job of one kind ('pickup' or 'delivery') at the company to its barangay's rider.
     * Returns ['assigned' => n, 'no_rider' => n, 'riders' => [name => n], 'notified' => n].
     */
    public function autoAssignAll(User $by, string $kind): array
    {
        $this->expireStale();
        $result = ['assigned' => 0, 'no_rider' => 0, 'riders' => [], 'notified' => 0];
        $eligible = $this->eligibleRiders((int) $by->id);
        foreach ($this->waiting((int) $by->id, $kind) as $parcel) {
            $rider = $this->barangayRider($parcel, $kind, $eligible);
            if (! $rider) {
                $result['no_rider']++;
                continue;
            }
            try {
                $this->assignManually($parcel, $rider, $by);
            } catch (\RuntimeException) {
                $result['no_rider']++;   // taken meanwhile, or no longer waiting
                continue;
            }
            $result['assigned']++;
            $result['notified'] += $this->lastNotification ? 1 : 0;
            $result['riders'][$rider->full_name] = ($result['riders'][$rider->full_name] ?? 0) + 1;
        }

        return $result;
    }

    /** The company's parcels waiting for a rider (for one kind of job, or both), oldest first. */
    public function waiting(int $scId, ?string $kind = null): Collection
    {
        $statuses = match ($kind) {
            RiderJobOffer::PICKUP   => ['pickup_approved'],
            RiderJobOffer::DELIVERY => ['sorted'],
            default                 => ['pickup_approved', 'sorted'],
        };

        return Parcel::where('current_sorting_center_id', $scId)->whereIn('status', $statuses)
            ->with(['seller', 'order', 'area'])->oldest()->get()
            ->filter(fn (Parcel $p) => ($k = $this->jobKind($p)) !== null && ($kind === null || $k === $kind))
            ->values();
    }

    /** The rider covers the barangay where the job starts. */
    private function isNear(Rider $rider, Parcel $parcel, string $kind): bool
    {
        if ($kind === RiderJobOffer::DELIVERY) {
            return $parcel->area_id && (int) $rider->area_id === (int) $parcel->area_id;
        }
        $barangay = $rider->area?->name;
        return $barangay && $parcel->pickup_address && stripos($parcel->pickup_address, $barangay) !== false;
    }

    public function assignManually(Parcel $parcel, Rider $rider, User $by): string
    {
        $this->lastNotification = null;

        return DB::transaction(function () use ($parcel, $rider, $by) {
            $parcel = Parcel::lockForUpdate()->findOrFail($parcel->id);
            $kind = $this->jobKind($parcel);
            if (! $kind) {
                throw new \RuntimeException(in_array($parcel->status, ['pickup_approved', 'sorted'], true)
                    ? "{$parcel->tracking_number} is already assigned to a rider."
                    : "{$parcel->tracking_number} is not ready for a rider ({$parcel->statusLabel()}).");
            }
            if ($reason = $this->ineligibleReason($rider, (int) $by->id)) {
                throw new \RuntimeException($reason);
            }
            RiderJobOffer::where('parcel_id', $parcel->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            $this->assign($parcel, $kind, $rider, $by->id);

            return $kind;
        });
    }

    /** Create the rider's job (call inside a transaction with the parcel locked). */
    private function assign(Parcel $parcel, string $kind, Rider $rider, ?int $by): void
    {
        $svc = app(ParcelService::class);
        $parcel->forceFill(['unassigned_at' => null])->save();
        if ($parcel->order_id) {
            $parcel->timeline('Rider ' . $rider->full_name . ' assigned for ' . $kind);
        }

        // A rider who accepted an offer already knows; a hub assignment is announced to the rider.
        $notify = fn (int $jobId) => $by !== null
            ? $this->lastNotification = app(RiderNotifier::class)->assigned($parcel->fresh(), $rider, $kind, $jobId)
            : null;

        if ($kind === RiderJobOffer::PICKUP) {
            $job = ParcelPickup::create([
                'parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'sorting_center_id' => $parcel->current_sorting_center_id,
                'assigned_by' => $by, 'status' => 'assigned',
            ]);
            $notify($job->id);
            app(ShipmentTracker::class)->record($parcel, 'pickup_rider_assigned', ['pickup_id' => $job->id, 'dedupe_key' => 'rider-job:pickup:' . $job->id . ':assigned-event']);
            $svc->notifySeller($parcel, 'Rider on the way for pickup',
                "Rider {$rider->full_name} will pick up {$parcel->tracking_number}. Please have it packed and labeled.");
            return;
        }

        $job = ParcelDelivery::create($this->deliveryJob($parcel) + [
            'parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'area_id' => $parcel->area_id, 'assigned_by' => $by, 'status' => 'assigned',
        ]);
        $parcel->update(['status' => 'assigned']);
        $notify($job->id);
        $svc->syncOrder($parcel);
    }

    /**
     * What every new delivery job of this parcel must carry, however it is assigned: which attempt it
     * is (the 3rd failed one sends the parcel back, A13) and the cash to collect for an unpaid COD order.
     */
    public function deliveryJob(Parcel $parcel): array
    {
        $order = $parcel->order;
        $cod = $order && strtoupper((string) $order->payment_method) === 'COD' && $order->payment_status !== 'Paid' ? (float) $order->total_price : 0.0;

        return ['attempt' => $this->attempt($parcel, RiderJobOffer::DELIVERY), 'cod_amount' => $cod];
    }
}
