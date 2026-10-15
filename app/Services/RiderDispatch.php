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
 * Who does each rider job (assumptions A14, A34):
 *  - auto-assign offers a job to the nearest Online, Active rider of the same company with
 *    fewer than 3 active jobs; the offer expires after 60 seconds
 *  - a declined or expired offer goes to the next rider; after 3 the job is Unassigned
 *    (manual assignment only)
 *  - logistics can assign manually, only to riders who could take an offer
 * A job is the pickup of a parcel (seller → hub) or one delivery attempt (hub → buyer).
 */
class RiderDispatch
{
    public const OFFER_SECONDS = 60;
    public const MAX_OFFERS    = 3;
    public const MAX_ACTIVE    = 3;

    /** Rider pay (A33). */
    public const PAY_PICKUP   = 30.00;
    public const PAY_DELIVERY = 50.00;

    /** The job the parcel is waiting for: 'pickup', 'delivery' or null. */
    public function jobKind(Parcel $parcel): ?string
    {
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
    public function eligibleRiders(int $scId): Collection
    {
        return Rider::where('sorting_center_id', $scId)->where('application_status', 'approved')->where('is_active', true)
            ->orderBy('id')->get()
            ->filter(fn (Rider $r) => $r->isOnlineNow() && $r->activeJobsCount() < self::MAX_ACTIVE)
            ->values();
    }

    /** Why this rider can't be given a job, or null when they can. */
    public function ineligibleReason(Rider $rider, int $scId): ?string
    {
        return match (true) {
            (int) $rider->sorting_center_id !== $scId => 'This rider belongs to another logistics company.',
            ! $rider->isApproved()                    => "{$rider->full_name} is not an approved rider.",
            $rider->isSuspended()                     => "{$rider->full_name} is suspended.",
            ! $rider->isOnlineNow()                   => "{$rider->full_name} is offline.",
            $rider->activeJobsCount() >= self::MAX_ACTIVE => "{$rider->full_name} already has " . self::MAX_ACTIVE . ' active jobs.',
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
                Parcel::whereKey($pickup->parcel_id)->update(['unassigned_at' => now()]);
                $n++;
            }
            foreach (ParcelDelivery::where('rider_id', $rider->id)->where('status', 'assigned')->get() as $delivery) {
                Parcel::whereKey($delivery->parcel_id)->update(['status' => 'sorted', 'unassigned_at' => now()]);
                $delivery->delete();   // the attempt never started
                $n++;
            }
            RiderJobOffer::where('rider_id', $rider->id)->where('status', 'pending')->update(['status' => 'cancelled']);
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
            // Nearest first: a rider covering the parcel's barangay, then the least busy.
            ->sortBy(fn (Rider $r) => [($kind === RiderJobOffer::DELIVERY && $parcel->area_id && (int) $r->area_id === (int) $parcel->area_id) ? 0 : 1, $r->activeJobsCount(), $r->id])
            ->first();
        if (! $rider) {
            return null;
        }

        $offer = RiderJobOffer::create([
            'parcel_id' => $parcel->id, 'kind' => $kind, 'attempt' => $attempt, 'rider_id' => $rider->id,
            'sorting_center_id' => $scId, 'status' => 'pending', 'expires_at' => now()->addSeconds(self::OFFER_SECONDS),
        ]);
        $this->notify($rider->user_id, 'New ' . $kind . ' job offer',
            ucfirst($kind) . ' of ' . $parcel->tracking_number . '. Accept within ' . self::OFFER_SECONDS . ' seconds.', route('courier.rider'));

        return $offer;
    }

    /** Offers past their 60 seconds expire, and each job goes to the next rider. */
    public function expireStale(): int
    {
        $stale = RiderJobOffer::where('status', 'pending')->where('expires_at', '<=', now())->get();
        foreach ($stale as $offer) {
            $offer->update(['status' => 'expired']);
        }
        foreach ($stale->pluck('parcel_id')->unique() as $parcelId) {
            if ($parcel = Parcel::find($parcelId)) {
                $this->offer($parcel);
            }
        }
        return $stale->count();
    }

    /** The rider accepts an offer. Throws \RuntimeException with a message for the rider when it can't be done. */
    public function accept(RiderJobOffer $offer, Rider $rider): void
    {
        // Too late: the offer expires and goes to the next rider (done outside the transaction below).
        $offer->refresh();
        if ($offer->status === 'pending' && $offer->expires_at->isPast()) {
            $offer->update(['status' => 'expired']);
            $this->offer($offer->parcel);
            throw new \RuntimeException('This offer expired after ' . self::OFFER_SECONDS . ' seconds and went to the next rider.');
        }

        DB::transaction(function () use ($offer, $rider) {
            $parcel = Parcel::lockForUpdate()->findOrFail($offer->parcel_id);
            $offer  = RiderJobOffer::lockForUpdate()->findOrFail($offer->id);

            if ($offer->status === 'accepted') {
                throw new \RuntimeException('You already accepted this job.');
            }
            if ($offer->status === 'expired') {
                throw new \RuntimeException('This offer expired after ' . self::OFFER_SECONDS . ' seconds and went to the next rider.');
            }
            if ($offer->status !== 'pending' || $this->jobKind($parcel) !== $offer->kind) {
                throw new \RuntimeException('This job was already assigned to another rider.');
            }
            if ($rider->activeJobsCount() >= self::MAX_ACTIVE) {
                throw new \RuntimeException('You already have ' . self::MAX_ACTIVE . ' active jobs. Finish one before accepting another.');
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
        $this->offer($offer->parcel);
    }

    /** Logistics assigns the parcel's waiting job to a rider. Throws \RuntimeException with the reason when not allowed. */
    public function assignManually(Parcel $parcel, Rider $rider, User $by): string
    {
        return DB::transaction(function () use ($parcel, $rider, $by) {
            $parcel = Parcel::lockForUpdate()->findOrFail($parcel->id);
            $kind = $this->jobKind($parcel);
            if (! $kind) {
                throw new \RuntimeException("{$parcel->tracking_number} is already assigned to a rider.");
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

        if ($kind === RiderJobOffer::PICKUP) {
            ParcelPickup::create([
                'parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'sorting_center_id' => $parcel->current_sorting_center_id,
                'assigned_by' => $by, 'status' => 'assigned',
            ]);
            $svc->notifySeller($parcel, 'Rider on the way for pickup',
                "Rider {$rider->full_name} will pick up {$parcel->tracking_number}. Please have it packed and labeled.");
            return;
        }

        $order = $parcel->order;
        $cod = $order && strtoupper((string) $order->payment_method) === 'COD' && $order->payment_status !== 'Paid' ? (float) $order->total_price : 0.0;
        ParcelDelivery::create([
            'parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'area_id' => $parcel->area_id, 'assigned_by' => $by,
            'status' => 'assigned', 'attempt' => $this->attempt($parcel, $kind), 'cod_amount' => $cod,
        ]);
        $parcel->update(['status' => 'assigned']);
        $svc->syncOrder($parcel);
    }

    private function notify(?int $userId, string $title, string $body, ?string $link = null): void
    {
        if ($userId) {
            UserNotification::create(['user_id' => $userId, 'title' => $title, 'body' => $body, 'type' => 'info', 'link' => $link]);
        }
    }
}
