<?php

namespace App\Services;

use App\Models\Parcel;
use App\Models\Rider;
use App\Models\RiderJobOffer;
use App\Models\UserNotification;
use Illuminate\Database\QueryException;

/**
 * In-app notifications to riders about their jobs: assigned, offered, taken away (reassigned) and
 * cancelled. Each carries the parcel reference (waybill + tracking), when it happened, where to
 * pick up and where it goes.
 *
 * Each job event is stored once (user_notifications.dedupe_key), so a retried request or a double
 * click never notifies twice. Every method returns the notification that exists for the event, or
 * null when it could not be created; callers only tell the hub "rider notified" when it isn't null.
 */
class RiderNotifier
{
    /** A job was given to the rider (manual assignment, reassignment, or a hub's direct assignment). */
    public function assigned(Parcel $parcel, Rider $rider, string $kind, int $jobId): ?UserNotification
    {
        return $this->send($rider, "rider-job:{$kind}:{$jobId}:assigned",
            'New ' . $kind . ' job: ' . $this->ref($parcel),
            'Assigned ' . now()->format('M j, g:i A') . '. ' . $this->route($parcel, $kind) . ' Open My Jobs to acknowledge it.',
            'info', route('courier.rider'));
    }

    /** A timed job offer (auto-assign). */
    public function offered(RiderJobOffer $offer, Parcel $parcel, Rider $rider, int $seconds): ?UserNotification
    {
        return $this->send($rider, "rider-offer:{$offer->id}",
            'New ' . $offer->kind . ' job offer: ' . $this->ref($parcel),
            ucfirst($offer->kind) . ' offered ' . now()->format('M j, g:i A') . '. ' . $this->route($parcel, $offer->kind) . " Accept within {$seconds} seconds.",
            'info', route('courier.rider'));
    }

    /** The job went to another rider. */
    public function reassignedAway(Parcel $parcel, Rider $rider, string $kind, int $jobId): ?UserNotification
    {
        return $this->send($rider, "rider-job:{$kind}:{$jobId}:reassigned",
            'Job reassigned: ' . $this->ref($parcel),
            'The ' . $kind . ' of ' . $this->ref($parcel) . ' was given to another rider (' . now()->format('M j, g:i A') . '). You no longer need to do it.',
            'warning', route('courier.rider'));
    }

    /** The job was cancelled (order cancelled, parcel lost/damaged, rider suspended…). */
    public function cancelled(Parcel $parcel, Rider $rider, string $kind, int $jobId, string $reason): ?UserNotification
    {
        return $this->send($rider, "rider-job:{$kind}:{$jobId}:cancelled",
            'Job cancelled: ' . $this->ref($parcel),
            'The ' . $kind . ' of ' . $this->ref($parcel) . ' was cancelled (' . now()->format('M j, g:i A') . '). ' . rtrim($reason, '.') . '. Do not ' . ($kind === RiderJobOffer::PICKUP ? 'pick it up' : 'deliver it') . '.',
            'warning', route('courier.rider'));
    }

    private function ref(Parcel $parcel): string
    {
        return $parcel->waybill_number && $parcel->waybill_number !== $parcel->tracking_number
            ? $parcel->waybill_number . ' (' . $parcel->tracking_number . ')'
            : (string) $parcel->tracking_number;
    }

    private function route(Parcel $parcel, string $kind): string
    {
        $to = $parcel->dropoff_address
            ?: trim(implode(', ', array_filter([$parcel->destination_municipality, $parcel->destination_province])));
        $from = $kind === RiderJobOffer::PICKUP
            ? ($parcel->pickup_address ?: 'the seller')
            : ($parcel->currentSortingCenter?->name ?? 'the sorting center');

        return 'Pickup: ' . $from . '. Destination: ' . ($to ?: 'see the job details') . '.';
    }

    private function send(Rider $rider, string $key, string $title, string $body, string $type, string $link): ?UserNotification
    {
        if (! $rider->user_id) {
            return null;
        }
        try {
            return UserNotification::firstOrCreate(
                ['user_id' => $rider->user_id, 'dedupe_key' => $key],
                ['title' => $title, 'body' => $body, 'type' => $type, 'link' => $link],
            );
        } catch (QueryException $e) {
            // Two requests raced on the unique key: the other one created it.
            return UserNotification::where('user_id', $rider->user_id)->where('dedupe_key', $key)->first();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Rider notification {$key} failed: " . $e->getMessage());
            return null;
        }
    }
}
