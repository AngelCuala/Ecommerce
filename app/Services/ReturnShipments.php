<?php

namespace App\Services;

use App\Http\Controllers\Courier\RiderController;
use App\Models\ActivityLog;
use App\Models\Parcel;
use App\Models\ReturnRequest;
use App\Models\LinehaulTrip;
use App\Models\LinehaulTripParcel;
use App\Models\LogisticsHub;
use App\Models\ReturnScanEvent;
use App\Models\ReturnShipment;
use App\Models\SellerApplication;
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
 *   3. at the hub, like any parcel: sort (the next stop), check (condition), scan the waybill for the record, send:
 *      - the seller is in this hub's town: a rider brings it to the seller and hands it over;
 *      - another town: a hub-to-hub trip to the hub of the seller's town (Handover QR at both ends); that hub
 *        sorts, checks and scans it again, and its rider brings it to the seller.
 * Every step is a line in return_scan_events (who, where, when, condition, photo).
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
        $rs->update(['status' => 'at_hub', 'received_at' => now(), 'received_by' => $actor->id, 'current_hub_id' => $this->hubOf($hub)?->id]);
        $this->event($rs, 'arrived', $actor);
        if ($rs->returnRequest->seller_id) {
            $this->parcels->notify((int) $rs->returnRequest->seller_id, 'Return at the hub', $rs->order?->number() . ': the returned item is at the hub. It is checked and sent back to you.', 'info', route('seller.orders.show', $rs->order_id));
        }
    }

    // ── At the hub: sort → check → scan for the record → send ─────────────

    /** The company's hub (where a returned item is kept). */
    public function hubOf(User $sc): ?LogisticsHub
    {
        return LogisticsHub::where('sorting_center_id', $sc->id)->orderBy('id')->first();
    }

    /**
     * Where the item goes from this company: ['stop' => 'seller'] when this company serves the seller's town (or no
     * company does), or ['stop' => 'hub', 'company', 'hub'] for the hub of the seller's town (a hub-to-hub trip).
     */
    public function nextStopFrom(ReturnShipment $rs, User $sc): array
    {
        $app = SellerApplication::where('user_id', $rs->returnRequest?->seller_id)->first();
        $town = $app?->municipality;
        $company = null;
        if ($app?->municipality_code || $town) {
            $company = User::where('role', 'sorting_center')->where('approval_status', 'approved')
                ->when($app?->municipality_code, fn ($q) => $q->where('assigned_municipality_code', $app->municipality_code),
                    fn ($q) => $q->where('assigned_municipality', $town))
                ->orderBy('id')->first();
        }
        $hub = $company && (int) $company->id !== (int) $sc->id ? $this->hubOf($company) : null;

        return $hub ? ['stop' => 'hub', 'company' => $company, 'hub' => $hub, 'town' => $town]
            : ['stop' => 'seller', 'company' => $sc, 'hub' => null, 'town' => $town];
    }

    private function step(ReturnShipment $rs, string $step): void
    {
        $now = $rs->stepAtHub();
        if ($now === null) {
            throw new RuntimeException('This return is ' . strtolower($rs->statusLabel()) . ': there is nothing to do at the hub.');
        }
        if ($now !== $step) {
            $names = ['sort' => 'Sort it', 'check' => 'Check it', 'scan' => 'Scan its waybill for the record', 'send' => 'Send it on'];
            throw new RuntimeException("Do the steps in order: {$names[$now]} first.");
        }
    }

    /** Step 1: sort. A picked-up item is received at the hub at the same time. */
    public function sort(ReturnShipment $rs, User $hub, User $actor): array
    {
        $this->guardHub($rs, $hub);
        if ($rs->status === 'picked_up') {
            $this->receive($rs, $hub, $actor);
            $rs->refresh();
        }
        $this->step($rs, 'sort');
        if (! $rs->current_hub_id) {   // received before the hub was recorded (the old Check & receive)
            $rs->update(['current_hub_id' => $this->hubOf($hub)?->id]);
        }
        $next = $this->nextStopFrom($rs, $hub);
        $rs->update(['next_stop' => $next['stop'], 'next_hub_id' => $next['hub']?->id, 'hub_sorted_at' => now()]);
        $this->event($rs, 'sorted', $actor, $next['stop'] === 'hub'
            ? 'Next: ' . $next['hub']->code . ' (' . \App\Support\SortingCenters::nameOf($next['company']) . ', the seller\'s town)'
            : 'Next: the seller, from this hub');

        return $next;
    }

    /** Step 2: check the item. Anything but Good needs a photo. */
    public function check(ReturnShipment $rs, User $hub, User $actor, ?string $condition, ?string $note, ?UploadedFile $photo): void
    {
        $this->guardHub($rs, $hub);
        $this->step($rs, 'check');
        if (! array_key_exists((string) $condition, ReturnShipment::CONDITIONS)) {
            throw new \InvalidArgumentException('Choose the condition of the item.');
        }
        if ($condition !== 'good' && ! $photo) {
            throw new \InvalidArgumentException('Take a photo of the problem (JPG or PNG).');
        }
        $path = $photo?->store('returns', \App\Support\ProtectedFile::DISK);
        $note = trim((string) $note) ?: null;
        $rs->update(['hub_checked_at' => now(), 'hub_condition' => $condition, 'hub_check_note' => $note, 'hub_check_photo' => $path]);
        $this->event($rs, 'checked', $actor, $note, $condition, $path);
        if ($condition !== 'good' && $rs->returnRequest?->seller_id) {
            $this->parcels->notify((int) $rs->returnRequest->seller_id, 'Returned item checked: ' . strtolower(ReturnShipment::CONDITIONS[$condition]),
                $rs->order?->number() . ': the hub found the returned item ' . strtolower(ReturnShipment::CONDITIONS[$condition]) . ($note ? " ({$note})" : '') . '. A photo is on record.', 'warning', route('seller.orders.show', $rs->order_id));
        }
    }

    /** Step 3: scan the waybill for the record (it must be this return's). */
    public function scan(ReturnShipment $rs, User $hub, User $actor, string $code): void
    {
        $this->guardHub($rs, $hub);
        $this->step($rs, 'scan');
        $match = $rs->parcel ? RiderController::scanMatches($code, $rs->parcel) : strtoupper(trim($code)) === strtoupper($rs->code());
        if (! $match) {
            throw new \InvalidArgumentException('This waybill does not match this return. Check the label on the item.');
        }
        $rs->update(['hub_scanned_at' => now()]);
        $this->event($rs, 'scanned', $actor, $rs->code());
    }

    /**
     * Step 4 (another town): onto a hub-to-hub trip to the next hub. An open trip on that route is used if there is one
     * (not departed, not sealed); otherwise a new one is created. Returns the trip.
     */
    public function sendToHub(ReturnShipment $rs, User $hub, User $actor): LinehaulTrip
    {
        $this->guardHub($rs, $hub);
        $this->step($rs, 'send');
        if ($rs->next_stop !== 'hub' || ! $rs->next_hub_id) {
            throw new RuntimeException('This return goes to the seller from this hub: assign a rider instead.');
        }
        $linehaul = app(Linehaul::class);

        return DB::transaction(function () use ($rs, $hub, $actor, $linehaul) {
            $trip = LinehaulTrip::where('sorting_center_id', $hub->id)->where('origin_hub_id', $rs->current_hub_id)
                ->where('destination_hub_id', $rs->next_hub_id)->whereIn('status', LinehaulTrip::BEFORE_DEPARTURE)
                ->whereNull('sealed_at')->latest('id')->first()
                ?? $linehaul->createTrip($hub, ['origin_hub_id' => $rs->current_hub_id, 'destination_hub_id' => $rs->next_hub_id]);
            $linehaul->addReturn($hub, $trip, $rs);
            $rs->update(['linehaul_trip_id' => $trip->id]);
            $this->event($rs, 'loaded', $actor, "On {$trip->code} to " . $trip->destination?->code);
            ActivityLog::note('Return of ' . $rs->order?->number() . " put on {$trip->code} ({$trip->route()})", $rs->order);

            return $trip;
        });
    }

    // ── On the trip (called by App\Services\Linehaul) ─────────────────────

    public function departed(LinehaulTripParcel $item, LinehaulTrip $trip): void
    {
        $rs = $item->returnShipment;
        if (! $rs) {
            return;
        }
        $rs->update(['status' => 'in_transit']);
        $this->event($rs, 'departed', null, $trip->code . ' · ' . $trip->route());
        if ($rs->returnRequest?->seller_id) {
            $this->parcels->notify((int) $rs->returnRequest->seller_id, 'Returned item on the way', $rs->order?->number() . ": on a hub-to-hub trip to {$trip->destination?->code}.", 'info', route('seller.orders.show', $rs->order_id));
        }
    }

    /** Received at the next hub: it is now that company's return, and the steps start again there. */
    public function arrivedFromTrip(LinehaulTripParcel $item, LinehaulTrip $trip, ?string $damagePhoto = null): void
    {
        $rs = $item->returnShipment;
        if (! $rs) {
            return;
        }
        $receiver = $trip->receiverId();
        $rs->update([
            'status' => 'at_hub', 'sorting_center_id' => $receiver, 'current_hub_id' => $trip->destination_hub_id, 'received_at' => now(),
            'received_by' => auth()->id(), 'linehaul_trip_id' => null,
            'next_stop' => null, 'next_hub_id' => null, 'hub_sorted_at' => null, 'hub_checked_at' => null, 'hub_condition' => null,
            'hub_check_note' => null, 'hub_check_photo' => null, 'hub_scanned_at' => null,
        ]);
        $this->event($rs, 'received', auth()->user(), "From {$trip->code} ({$trip->route()})" . ($damagePhoto ? ' · damaged at unloading' : ''), $damagePhoto ? 'damaged' : null, $damagePhoto);
        $this->parcels->notify($receiver, 'Return arrived', $rs->order?->number() . ": the returned item arrived on {$trip->code}. Sort it, check it, scan it and send it to the seller.", 'warning', route('sc.returns', ['tab' => 'process']));
    }

    public function missingOnTrip(LinehaulTripParcel $item, LinehaulTrip $trip): void
    {
        $rs = $item->returnShipment;
        if (! $rs) {
            return;
        }
        $rs->update(['status' => 'missing', 'linehaul_trip_id' => null]);
        $this->event($rs, 'missing', null, "Not found when {$trip->code} arrived at " . $trip->destination?->code);
        foreach (array_filter([(int) $trip->sorting_center_id, (int) $rs->returnRequest?->seller_id]) as $who) {
            $this->parcels->notify($who, 'Returned item missing in transit', $rs->order?->number() . ": the returned item was not found when {$trip->code} arrived.", 'warning');
        }
    }

    /** Taken off the trip before it left: back at the hub, ready to send again. */
    public function takenOffTrip(LinehaulTripParcel $item, LinehaulTrip $trip): void
    {
        $rs = $item->returnShipment;
        if (! $rs) {
            return;
        }
        $rs->update(['status' => 'at_hub', 'linehaul_trip_id' => null]);
        $this->event($rs, 'removed', auth()->user(), "Taken off {$trip->code}");
    }

    private function event(ReturnShipment $rs, string $type, ?User $actor, ?string $note = null, ?string $condition = null, ?string $photo = null): void
    {
        ReturnScanEvent::create([
            'return_shipment_id' => $rs->id, 'sorting_center_id' => $rs->sorting_center_id, 'hub_id' => $rs->current_hub_id,
            'type' => $type, 'note' => $note ? mb_substr($note, 0, 300) : null, 'condition' => $condition, 'photo_path' => $photo,
            'actor_id' => $actor?->id, 'created_at' => now(),
        ]);
    }

    /** Step 4a: a rider brings it back to the seller. */
    public function assignToSeller(ReturnShipment $rs, User $hub, int $riderId): Rider
    {
        $this->guardHub($rs, $hub);
        if (! in_array($rs->status, ['picked_up', 'at_hub', 'to_seller'], true)) {
            throw new RuntimeException('This return is ' . strtolower($rs->statusLabel()) . '.');
        }
        if ($rs->status !== 'to_seller') {
            $this->step($rs, 'send');
            if ($rs->next_stop !== 'seller') {
                throw new RuntimeException('The seller is in another town: send it to ' . ($rs->nextHub?->code ?? 'the next hub') . ' on a hub-to-hub trip.');
            }
        }
        $rider = $this->riderFor($rs, $hub, $riderId);
        $first = $rs->status !== 'to_seller';
        $rs->update(['status' => 'to_seller', 'seller_rider_id' => $rider->id, 'seller_assigned_at' => now()]);
        if ($first) {
            $this->event($rs, 'to_seller', auth()->user(), $rider->full_name);
        }
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
