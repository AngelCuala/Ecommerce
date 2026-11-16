<?php

namespace App\Services;

use App\Http\Controllers\Courier\RiderController;
use App\Models\ActivityLog;
use App\Models\Parcel;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Models\Rider;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Return Items: when a buyer asks to return an order, the hub that delivered it collects the item the same way as a
 * seller pickup, and brings it back to the seller:
 *   1. the hub assigns a rider;  2. the rider accepts, then at the buyer takes a photo and scans the waybill;
 *   3. the hub checks and receives it;  4. a rider brings it to the seller and hands it over.
 * The seller is told at every step (and when the buyer is refunded, see ReturnRequest). The refund decision itself
 * stays with the return request (seller / ALVY), not with this trip.
 */
class ReturnShipments
{
    public function __construct(private ParcelService $parcels) {}

    /** A new return request: the hub that delivered the order gets the pickup job. */
    public function open(ReturnRequest $return): ?ReturnShipment
    {
        if (ReturnShipment::where('return_request_id', $return->id)->exists()) {
            return null;
        }
        $order = $return->order;
        $parcel = Parcel::where('order_id', $return->order_id)
            ->when($return->seller_id, fn ($q) => $q->where('seller_id', $return->seller_id))
            ->latest('id')->first();
        $hubId = $parcel?->current_sorting_center_id;

        $rs = ReturnShipment::create([
            'return_request_id' => $return->id, 'order_id' => $return->order_id, 'parcel_id' => $parcel?->id,
            'sorting_center_id' => $hubId, 'status' => 'requested',
        ]);

        $ref = $order?->number() ?? ('#' . $return->order_id);
        if ($hubId) {
            $this->parcels->notify((int) $hubId, 'New return pickup', "{$ref}: the buyer is returning it ({$return->reason}). Assign a rider to collect it.", 'warning', route('sc.returns'));
        }
        if ($return->seller_id) {
            $this->parcels->notify((int) $return->seller_id, 'Item coming back', "{$ref}: the buyer asked to return it ({$return->reason}). A rider will collect it and bring it back to you.", 'warning', route('seller.orders.show', $return->order_id));
        }
        $this->parcels->notify((int) $return->user_id, 'Return pickup booked', "{$ref}: a rider will collect the item from you. Keep it packed with its waybill.", 'info', route('profile.orders.show', $return->order_id));
        ActivityLog::note("Return pickup opened for {$ref}", $order);

        return $rs;
    }

    /** The return was withdrawn (rejected / closed) before the rider collected it: the trip is cancelled. */
    public function cancelIfNotCollected(ReturnRequest $return, string $reason): void
    {
        $rs = ReturnShipment::where('return_request_id', $return->id)->whereIn('status', ['requested', 'pickup_assigned'])->first();
        if (! $rs) {
            return;
        }
        $rider = $rs->pickupRider;
        $rs->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
        if ($rider) {
            $this->tell($rider, 'Return pickup cancelled', 'The return pickup for ' . $rs->order?->number() . ' was cancelled.');
        }
    }

    private function guardHub(ReturnShipment $rs, User $hub): void
    {
        if ((int) $rs->sorting_center_id !== (int) $hub->id) {
            throw new RuntimeException('This return belongs to another sorting center.');
        }
    }

    private function riderFor(ReturnShipment $rs, User $hub, int $riderId): Rider
    {
        $rider = Rider::where('sorting_center_id', $hub->id)->find($riderId);
        if (! $rider) {
            throw new RuntimeException('Choose one of your own riders.');
        }
        if ($why = app(RiderDispatch::class)->ineligibleReason($rider, (int) $hub->id, true)) {
            throw new RuntimeException($why);
        }
        if ($rs->parcel?->isBulky() && ! $rider->canCarryBulky()) {
            throw new RuntimeException('This item is bulky (' . $rs->parcel->bulkyReason() . '): assign a car or van rider.');
        }

        return $rider;
    }

    private function tell(Rider $rider, string $title, string $body): void
    {
        UserNotification::create(['user_id' => $rider->user_id, 'title' => $title, 'body' => $body, 'type' => 'info', 'link' => route('courier.rider')]);
    }

    /** Step 1: the hub picks the rider who collects it from the buyer (or changes them before pickup). */
    public function assignPickup(ReturnShipment $rs, User $hub, int $riderId): Rider
    {
        $this->guardHub($rs, $hub);
        if (! in_array($rs->status, ['requested', 'pickup_assigned'], true)) {
            throw new RuntimeException('This return was already collected (' . $rs->statusLabel() . ').');
        }
        $rider = $this->riderFor($rs, $hub, $riderId);
        $previous = $rs->pickupRider;
        $rs->update(['status' => 'pickup_assigned', 'pickup_rider_id' => $rider->id, 'pickup_assigned_at' => now(), 'acknowledged_at' => null]);
        if ($previous && $previous->id !== $rider->id) {
            $this->tell($previous, 'Return pickup reassigned', 'The return pickup for ' . $rs->order?->number() . ' was given to another rider.');
        }
        $this->tell($rider, 'Return pickup', 'Collect the return of ' . $rs->order?->number() . ' from ' . ($rs->order?->full_name ?? 'the buyer') . ': ' . $rs->buyerAddress());
        $this->parcels->notify((int) $rs->returnRequest->user_id, 'Rider assigned for your return', "Rider {$rider->full_name} will collect the item for " . $rs->order?->number() . '.', 'info', route('profile.orders.show', $rs->order_id));

        return $rider;
    }

    /** Step 2a: the rider accepts the job. */
    public function acknowledge(ReturnShipment $rs, Rider $rider): void
    {
        if ((int) $rs->pickup_rider_id !== (int) $rider->id || $rs->status !== 'pickup_assigned') {
            throw new RuntimeException('This return pickup is not waiting for you.');
        }
        $rs->forceFill(['acknowledged_at' => $rs->acknowledged_at ?? now()])->save();
    }

    /** Step 2b: at the buyer: photo of the item and the waybill scanned. */
    public function confirmPickup(ReturnShipment $rs, Rider $rider, string $code, ?UploadedFile $photo): void
    {
        DB::transaction(function () use ($rs, $rider, $code, $photo) {
            $rs = ReturnShipment::lockForUpdate()->findOrFail($rs->id);
            if ((int) $rs->pickup_rider_id !== (int) $rider->id || $rs->status !== 'pickup_assigned') {
                throw new RuntimeException('This return pickup is not waiting for you (' . $rs->statusLabel() . ').');
            }
            if (! $rs->acknowledged_at) {
                throw new RuntimeException('Accept this return pickup first, then confirm it at the buyer.');
            }
            $match = $rs->parcel ? RiderController::scanMatches($code, $rs->parcel) : strtoupper(trim($code)) === strtoupper($rs->code());
            if (! $match) {
                throw new \InvalidArgumentException('This waybill does not match this return. Check the label on the parcel.');
            }
            $proof = $photo?->store('proof-of-pickup', \App\Support\ProtectedFile::DISK);
            $rs->update(['status' => 'picked_up', 'picked_up_at' => now(), 'pickup_proof_path' => $proof]);

            $ref = $rs->order?->number();
            $this->parcels->notify((int) $rs->returnRequest->user_id, 'Return picked up', "{$ref}: rider {$rider->full_name} collected the item.", 'info', route('profile.orders.show', $rs->order_id));
            if ($rs->returnRequest->seller_id) {
                $this->parcels->notify((int) $rs->returnRequest->seller_id, 'Return picked up', "{$ref}: the returned item was collected from the buyer and is on its way to the hub.", 'info', route('seller.orders.show', $rs->order_id));
            }
            if ($rs->sorting_center_id) {
                $this->parcels->notify((int) $rs->sorting_center_id, 'Return on the way to the hub', "{$ref}: {$rider->full_name} collected the return. Receive it when it arrives.", 'info', route('sc.returns', ['tab' => 'hub']));
            }
            ActivityLog::note("Return of {$ref} picked up by {$rider->full_name}", $rs->order);
        });
    }

    /** Step 3: the hub checked the item and has it. */
    public function receive(ReturnShipment $rs, User $hub, User $actor): void
    {
        $this->guardHub($rs, $hub);
        if ($rs->status !== 'picked_up') {
            throw new RuntimeException($rs->status === 'pickup_assigned' ? 'The rider has not collected it from the buyer yet.' : 'This return is already ' . strtolower($rs->statusLabel()) . '.');
        }
        $rs->update(['status' => 'at_hub', 'received_at' => now(), 'received_by' => $actor->id]);
        if ($rs->returnRequest->seller_id) {
            $this->parcels->notify((int) $rs->returnRequest->seller_id, 'Return at the hub', $rs->order?->number() . ': the returned item is at the hub. A rider will bring it to you.', 'info', route('seller.orders.show', $rs->order_id));
        }
    }

    /** Step 4a: a rider brings it back to the seller. */
    public function assignToSeller(ReturnShipment $rs, User $hub, int $riderId): Rider
    {
        $this->guardHub($rs, $hub);
        if (! in_array($rs->status, ['at_hub', 'to_seller'], true)) {
            throw new RuntimeException($rs->status === 'picked_up' ? 'Receive it at the hub first.' : 'This return is ' . strtolower($rs->statusLabel()) . '.');
        }
        $rider = $this->riderFor($rs, $hub, $riderId);
        $rs->update(['status' => 'to_seller', 'seller_rider_id' => $rider->id, 'seller_assigned_at' => now()]);
        $this->tell($rider, 'Return to seller', 'Bring the returned item of ' . $rs->order?->number() . ' back to the seller: ' . $rs->sellerAddress());
        if ($rs->returnRequest->seller_id) {
            $this->parcels->notify((int) $rs->returnRequest->seller_id, 'Returned item on the way', $rs->order?->number() . ": rider {$rider->full_name} is bringing the returned item to you.", 'info', route('seller.orders.show', $rs->order_id));
        }

        return $rider;
    }

    /** Step 4b: handed to the seller. */
    public function handedToSeller(ReturnShipment $rs, Rider $rider, ?UploadedFile $photo): void
    {
        if ((int) $rs->seller_rider_id !== (int) $rider->id || $rs->status !== 'to_seller') {
            throw new RuntimeException('This return is not with you (' . $rs->statusLabel() . ').');
        }
        $proof = $photo?->store('proof-of-delivery', \App\Support\ProtectedFile::DISK);
        $rs->update(['status' => 'returned', 'returned_at' => now(), 'returned_proof_path' => $proof]);
        $ref = $rs->order?->number();
        if ($rs->returnRequest->seller_id) {
            $this->parcels->notify((int) $rs->returnRequest->seller_id, 'Returned item delivered', "{$ref}: the returned item was handed back to you.", 'info', route('seller.orders.show', $rs->order_id));
        }
        $this->parcels->notify((int) $rs->returnRequest->user_id, 'Return completed', "{$ref}: the item is back with the seller.", 'info', route('profile.orders.show', $rs->order_id));
        ActivityLog::note("Return of {$ref} handed back to the seller by {$rider->full_name}", $rs->order);
    }
}
