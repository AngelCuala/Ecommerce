<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\CourierChecklist;
use App\Models\LinehaulTrip;
use App\Models\LinehaulTripParcel;
use App\Models\LogisticsHub;
use App\Models\Parcel;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hub-to-hub (linehaul) trips (A38–A41). The company creates a trip from one of its hubs to another with parcels
 * waiting at the origin, and assigns an On Duty courier. The courier accepts, scans every parcel while loading,
 * seals and departs within 500 m of the origin; the parcels are In Transit. At the destination (within 500 m) hub
 * staff check the seal and scan each parcel in; closing receiving pays the courier.
 */
class Linehaul
{
    /** Pay per completed trip, by the courier's vehicle (A41). */
    public const PAY = ['Van' => 600.00, '4-Wheeler Closed Van Truck' => 1000.00, '6-Wheeler Truck' => 1500.00];

    public const SEAL_PATTERN = '/^BH-\d{7}$/';

    public const DECLINE_REASONS = ['Vehicle problem', 'Not feeling well', 'Too far', 'Other'];

    public const DELAY_REASONS = ['Heavy traffic', 'Vehicle breakdown', 'Weather', 'Road closure', 'Other'];

    /** Parcel statuses that mean "waiting at the hub" (received or sorted, not yet with a rider). */
    public const AT_HUB = ['picked_up', 'sorted'];

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function notify(?int $userId, string $title, string $body, string $type = 'linehaul'): void
    {
        if ($userId) {
            UserNotification::create(['user_id' => $userId, 'title' => $title, 'body' => $body, 'type' => $type]);
        }
    }

    public static function payFor(Courier $courier): float
    {
        return self::PAY[$courier->vehicle_type] ?? 0.0;
    }

    private function at(LogisticsHub $hub, ?float $lat, ?float $lng, string $doing): void
    {
        if ($lat === null || $lng === null) {
            $this->fail('location', "Allow location so ALVY can check you are at {$hub->code} to $doing.");
        }
        $m = $hub->distanceTo($lat, $lng);
        if ($m !== null && $m > LogisticsHub::GEOFENCE_METERS) {
            $this->fail('location', "You must be at {$hub->code} (within " . LogisticsHub::GEOFENCE_METERS . " m) to $doing. You are about " . number_format($m / 1000, 1) . ' km away.');
        }
    }

    // ── Duty ─────────────────────────────────────────────────────

    /**
     * Go On Duty: location and a passed pre-trip checklist. An item marked Not OK needs a note (10–300), keeps the
     * courier Off Duty and tells the company. $items = ['Tires' => 'ok'|'not_ok', …].
     */
    public function goOnDuty(Courier $c, array $items, ?string $note, ?float $lat, ?float $lng): string
    {
        if ($c->isSuspended()) {
            $this->fail('duty', 'Your courier account is suspended by ' . $c->hubName() . '. You cannot go On Duty.');
        }
        if ($c->on_duty) {
            return 'You are already On Duty.';
        }
        if ($lat === null || $lng === null) {
            $this->fail('location', 'ALVY needs your location to put you On Duty. Allow location for the app in your phone\'s Settings, then try again.');
        }
        $answers = [];
        foreach (CourierChecklist::ITEMS as $item) {
            $v = $items[$item] ?? null;
            if (! in_array($v, ['ok', 'not_ok'], true)) {
                $this->fail('items', 'Answer every checklist item (OK or Not OK) before going On Duty.');
            }
            $answers[$item] = $v;
        }
        $bad = array_keys(array_filter($answers, fn ($v) => $v === 'not_ok'));
        $note = trim((string) $note);
        if ($bad && (mb_strlen($note) < 10 || mb_strlen($note) > 300)) {
            $this->fail('note', 'Add a note about ' . implode(', ', $bad) . ' (10 to 300 characters).');
        }

        CourierChecklist::create(['courier_id' => $c->id, 'items' => $answers, 'passed' => ! $bad, 'note' => $bad ? $note : null, 'lat' => $lat, 'lng' => $lng]);
        $c->forceFill(['last_lat' => $lat, 'last_lng' => $lng, 'last_seen_at' => now()]);
        if ($bad) {
            $c->save();
            $this->notify($c->sorting_center_id, 'Pre-trip checklist failed', $c->fullName() . ' (' . $c->plate_number . '): ' . implode(', ', $bad) . ' Not OK — "' . $note . '". The courier stays Off Duty.');
            return 'Your vehicle did not pass the checklist (' . implode(', ', $bad) . '). You stay Off Duty and ' . $c->hubName() . ' was told.';
        }
        $c->forceFill(['on_duty' => true, 'duty_since' => now(), 'checklist_at' => now()])->save();

        return 'You are On Duty. Trips assigned to you will show here.';
    }

    public function goOffDuty(Courier $c): string
    {
        $trip = $c->activeTrip();
        if ($trip && in_array($trip->status, ['loading', 'in_transit', 'arrived'], true)) {
            $this->fail('duty', "Hand over {$trip->code} at {$trip->destination->code} before going Off Duty (it is {$trip->label()}).");
        }
        if ($trip) {
            $this->fail('duty', "You have {$trip->code} ({$trip->label()}). Decline it or finish it before going Off Duty.");
        }
        $c->forceFill(['on_duty' => false, 'duty_since' => null])->save();

        return 'You are Off Duty. You will not get trips until you go On Duty again.';
    }

    /** The courier app's location (every minute or so while On Duty); kept on the trip while In Transit. */
    public function ping(Courier $c, float $lat, float $lng): void
    {
        $c->forceFill(['last_lat' => $lat, 'last_lng' => $lng, 'last_seen_at' => now()])->save();
        $trip = $c->activeTrip();
        if ($trip && $trip->status === 'in_transit') {
            $trip->locations()->create(['lat' => $lat, 'lng' => $lng, 'recorded_at' => now()]);
        }
    }

    // ── Logistics: trips ─────────────────────────────────────────

    /** Parcels waiting at the origin hub that are not on another trip. */
    public function eligibleParcels(User $sc, LogisticsHub $origin): Collection
    {
        $busy = LinehaulTripParcel::whereIn('status', ['pending', 'loaded'])
            ->whereHas('trip', fn ($q) => $q->whereNotIn('status', ['cancelled', ...LinehaulTrip::DONE]))->pluck('parcel_id');

        return Parcel::where('current_sorting_center_id', $sc->id)->whereIn('status', self::AT_HUB)
            ->where(fn ($q) => $q->whereNull('current_hub_id')->orWhere('current_hub_id', $origin->id))
            ->whereNotIn('id', $busy)->orderBy('id')->get();
    }

    public function createTrip(User $sc, array $data): LinehaulTrip
    {
        $origin = LogisticsHub::where('sorting_center_id', $sc->id)->find($data['origin_hub_id'] ?? 0);
        // Linehaul is hub to hub between sorting centers: the destination is another sorting center's hub nearer the buyer
        // (it accepts the trip first).
        $dest = LogisticsHub::where('sorting_center_id', '!=', $sc->id)->find($data['destination_hub_id'] ?? 0);
        if (! $origin) {
            $this->fail('origin_hub_id', 'Choose one of your hubs as the origin.');
        }
        if (! $dest) {
            $this->fail('destination_hub_id', 'Choose the sorting center hub the trip goes to.');
        }
        if ($origin->is($dest)) {
            $this->fail('destination_hub_id', 'The destination must be a different hub.');
        }
        $ids = array_map('intval', (array) ($data['parcel_ids'] ?? []));
        $eligible = $this->eligibleParcels($sc, $origin)->keyBy('id');
        $wrong = array_diff($ids, $eligible->keys()->all());
        if ($wrong) {
            $this->fail('parcel_ids', 'These parcels are not waiting at ' . $origin->code . ': ' . Parcel::whereIn('id', $wrong)->pluck('tracking_number')->implode(', ') . '.');
        }

        return DB::transaction(function () use ($sc, $origin, $dest, $ids, $data) {
            $cross = (int) $dest->sorting_center_id !== (int) $sc->id;
            $trip = LinehaulTrip::create([
                'code' => LinehaulTrip::nextCode(), 'sorting_center_id' => $sc->id, 'origin_hub_id' => $origin->id, 'destination_hub_id' => $dest->id,
                'destination_sorting_center_id' => $dest->sorting_center_id, 'handover_status' => $cross ? 'pending' : null,
                'status' => 'scheduled', 'scheduled_departure_at' => $data['scheduled_departure_at'] ?? null,
            ]);
            foreach ($ids as $id) {
                $trip->items()->create(['parcel_id' => $id, 'status' => 'pending']);
                Parcel::whereKey($id)->whereNull('current_hub_id')->update(['current_hub_id' => $origin->id]);
            }
            $trip->log('created', null, ['new_value' => $trip->route() . ', ' . count($ids) . ' parcel(s)', 'courier_id' => null]);
            if ($cross) {
                $this->notify((int) $dest->sorting_center_id, "Incoming trip {$trip->code} needs your acceptance",
                    \App\Support\SortingCenters::nameOf($sc) . " is sending trip {$trip->code} to {$dest->code} ({$trip->route()}"
                    . ($ids ? ', ' . count($ids) . ' parcel(s) so far' : '') . '). Accept or reject it in Linehaul Trips.');
            }

            return $trip;
        });
    }

    /** Couriers who can take the trip now: the company's Active couriers, On Duty, no trip, enough capacity. */
    public function assignable(User $sc, ?LinehaulTrip $trip = null): Collection
    {
        $weight = $trip?->weightKg() ?? 0;
        Courier::syncCompany((int) $sc->id);

        return Courier::where('sorting_center_id', $sc->id)->whereNotNull('home_hub_id')->where('status', 'approved')
            ->where('on_duty', true)->where('last_seen_at', '>', now()->subMinutes(Courier::ONLINE_MINUTES))
            ->whereDoesntHave('trips', fn ($q) => $q->whereIn('status', LinehaulTrip::ACTIVE)->when($trip, fn ($q) => $q->whereKeyNot($trip->id)))
            ->where(fn ($q) => $q->whereNull('load_capacity_kg')->orWhere('load_capacity_kg', '>=', $weight))
            ->orderBy('first_name')->get();
    }

    /** Assign (or reassign) the trip to a courier. In Transit only after a Vehicle breakdown report. */
    public function assign(User $sc, LinehaulTrip $trip, int $courierId, ?string $reason = null): LinehaulTrip
    {
        return DB::transaction(function () use ($sc, $trip, $courierId, $reason) {
            $trip = LinehaulTrip::whereKey($trip->id)->lockForUpdate()->first();
            $courier = Courier::where('sorting_center_id', $sc->id)->whereNotNull('home_hub_id')->find($courierId);
            if (! $courier) {
                $this->fail('courier_id', 'Choose one of your couriers.');
            }
            if ((int) $trip->courier_id === $courier->id) {
                $this->fail('courier_id', "{$trip->code} is already assigned to {$courier->fullName()}.");
            }
            $inTransit = $trip->status === 'in_transit';
            if (! in_array($trip->status, LinehaulTrip::BEFORE_DEPARTURE, true) && ! $inTransit) {
                $this->fail('courier_id', "{$trip->code} is {$trip->label()} and can no longer be reassigned.");
            }
            if ($inTransit && ! $trip->events()->where('type', 'breakdown')->exists()) {
                $this->fail('courier_id', "{$trip->code} is In Transit. It can be reassigned only after the courier reports a vehicle breakdown.");
            }
            $problem = match (true) {
                $courier->status !== 'approved'                  => "{$courier->fullName()} is not active (" . $courier->status . ').',
                ! $courier->isOnDutyNow()                        => "{$courier->fullName()} is Off Duty (or the app is offline) and cannot be assigned.",
                (bool) $courier->activeTrip()                    => "{$courier->fullName()} already has a trip ({$courier->activeTrip()->code}).",
                $courier->load_capacity_kg && $trip->weightKg() > $courier->load_capacity_kg
                                                                 => "{$trip->code} weighs {$trip->weightKg()} kg, more than {$courier->fullName()}'s {$courier->load_capacity_kg} kg.",
                default                                          => null,
            };
            if ($problem) {
                $this->fail('courier_id', $problem);
            }

            $previous = $trip->courier;
            $trip->fill(['courier_id' => $courier->id, 'assigned_at' => now()]);
            if ($inTransit) {
                // The new courier scans every parcel again before continuing (A41).
                $trip->needs_rescan = true;
                $trip->items()->where('status', 'loaded')->update(['status' => 'pending', 'loaded_at' => null]);
            } else {
                $trip->fill(['status' => 'assigned', 'accepted_at' => null, 'loading_at' => null]);
                $trip->items()->where('status', 'loaded')->update(['status' => 'pending', 'loaded_at' => null]);
            }
            $trip->save();
            $trip->setRelation('courier', $courier);   // $previous loaded the old one
            $trip->log($previous ? 'reassigned' : 'assigned', $reason, ['old_value' => $previous?->fullName(), 'new_value' => $courier->fullName(), 'courier_id' => $courier->id]);

            if ($previous) {
                // The previous courier's history shows the trip as Reassigned (₱0.00).
                $trip->log('reassigned_away', $reason, ['courier_id' => $previous->id, 'old_value' => $previous->fullName(), 'new_value' => $courier->fullName()]);
                $this->notify($previous->user_id, "{$trip->code} was reassigned", "{$trip->code} ({$trip->route()}) was given to another courier" . ($reason ? ": $reason" : '.') . ' It no longer pays you.');
            }
            $this->notify($courier->user_id, "New trip {$trip->code}", "{$trip->route()}, {$trip->onBoard()->count()} parcel(s), pays ₱" . number_format(self::payFor($courier), 2)
                . ($inTransit ? '. Scan every parcel again before continuing.' : '. Open it to accept or decline.'));

            return $trip;
        });
    }

    /** Take a parcel off a trip before departure: it stays At Hub at the origin with no trip. */
    public function removeParcel(LinehaulTrip $trip, Parcel $parcel): LinehaulTrip
    {
        if (! in_array($trip->status, LinehaulTrip::BEFORE_DEPARTURE, true)) {
            $this->fail('parcel', "{$trip->code} already departed: parcels can no longer be removed.");
        }
        $item = $trip->onBoard()->where('parcel_id', $parcel->id)->first() ?? $this->fail('parcel', "{$parcel->tracking_number} is not on {$trip->code}.");
        $item->update(['status' => 'removed', 'loaded_at' => null]);
        $parcel->update(['current_hub_id' => $trip->origin_hub_id]);
        $trip->log('parcel_removed', null, ['old_value' => $parcel->tracking_number]);

        return $trip;
    }

    public function cancel(LinehaulTrip $trip, string $reason): LinehaulTrip
    {
        if (! in_array($trip->status, LinehaulTrip::BEFORE_DEPARTURE, true)) {
            $this->fail('reason', "{$trip->code} already departed and cannot be cancelled.");
        }
        $courier = $trip->courier;
        $trip->items()->whereIn('status', ['pending', 'loaded'])->update(['status' => 'removed']);
        $trip->update(['status' => 'cancelled']);
        $trip->log('cancelled', $reason);
        $this->notify($courier?->user_id, "{$trip->code} was cancelled", $reason);
        if ($trip->crossCompany() && $trip->handover_status !== 'rejected') {
            $this->notify($trip->receiverId(), "Incoming trip {$trip->code} was cancelled", $reason);
        }

        return $trip;
    }

    // ── Courier: the trip ────────────────────────────────────────

    /** The courier's own trip, or 404 (another courier's or another company's trip). */
    public function courierTrip(Courier $c, int $id): LinehaulTrip
    {
        $trip = LinehaulTrip::where('sorting_center_id', $c->sorting_center_id)->find($id);
        abort_unless($trip && (int) $trip->courier_id === $c->id, 404, 'This trip is not assigned to you.');

        return $trip;
    }

    public function accept(Courier $c, int $id): LinehaulTrip
    {
        return DB::transaction(function () use ($c, $id) {
            $trip = LinehaulTrip::where('sorting_center_id', $c->sorting_center_id)->lockForUpdate()->find($id);
            abort_unless($trip, 404, 'This trip is not assigned to you.');
            if ((int) $trip->courier_id !== $c->id) {
                $this->fail('trip', "{$trip->code} was already assigned to another courier.");
            }
            if ($trip->status !== 'assigned') {
                $this->fail('trip', "{$trip->code} is {$trip->label()}: there is nothing to accept.");
            }
            $trip->update(['status' => 'accepted', 'accepted_at' => now()]);
            $trip->log('accepted');

            return $trip;
        });
    }

    public function decline(Courier $c, int $id, ?string $reason): LinehaulTrip
    {
        $trip = $this->courierTrip($c, $id);
        if (! in_array($trip->status, ['assigned', 'accepted'], true)) {
            $this->fail('reason', "{$trip->code} is {$trip->label()} and can no longer be declined.");
        }
        if (! in_array($reason, self::DECLINE_REASONS, true)) {
            $this->fail('reason', 'Choose why you are declining (' . implode(', ', self::DECLINE_REASONS) . ').');
        }
        $trip->update(['status' => 'scheduled', 'courier_id' => null, 'assigned_at' => null, 'accepted_at' => null]);
        $trip->log('declined', $reason, ['courier_id' => $c->id, 'old_value' => $c->fullName()]);
        $this->notify($trip->sorting_center_id, "{$trip->code} was declined", "{$c->fullName()} declined {$trip->code} ({$trip->route()}): $reason. It is Unassigned again.");

        return $trip;
    }

    public function startLoading(Courier $c, int $id): LinehaulTrip
    {
        $trip = $this->courierTrip($c, $id);
        if ($trip->status !== 'accepted') {
            $this->fail('trip', $trip->status === 'assigned' ? "Accept {$trip->code} first." : "{$trip->code} is {$trip->label()}.");
        }
        $this->handoverAccepted($trip);
        $trip->update(['status' => 'loading', 'loading_at' => now()]);
        $trip->log('loading');

        return $trip;
    }

    /** Scan (or type) a waybill while loading — or again after a reassignment in transit. */
    public function scan(Courier $c, int $id, string $tracking): array
    {
        $trip = $this->courierTrip($c, $id);
        $rescan = $trip->status === 'in_transit' && $trip->needs_rescan;
        if ($trip->status !== 'loading' && ! $rescan) {
            $this->fail('tracking', $trip->status === 'accepted' ? 'Tap Start Loading first.' : "{$trip->code} is {$trip->label()}: nothing to scan.");
        }
        $tracking = strtoupper(trim($tracking));
        $parcel = Parcel::where('tracking_number', $tracking)->first() ?? $this->fail('tracking', "Parcel $tracking was not found.");
        $item = $trip->onBoard()->where('parcel_id', $parcel->id)->first() ?? $this->fail('tracking', "$tracking is not in this trip.");
        if ($item->status === 'loaded') {
            $this->fail('tracking', "$tracking is already loaded.");
        }
        $item->update(['status' => 'loaded', 'loaded_at' => now()]);
        [$done, $all] = [$trip->onBoard()->where('status', 'loaded')->count(), $trip->onBoard()->count()];
        if ($rescan && $done === $all) {
            $trip->update(['needs_rescan' => false]);
            $trip->log('rescanned', null, ['new_value' => "$all/$all"]);
        }

        return ['message' => "$tracking loaded ($done/$all).", 'loaded' => $done, 'total' => $all];
    }

    public function depart(Courier $c, int $id, ?string $seal, ?float $lat, ?float $lng): LinehaulTrip
    {
        $trip = $this->courierTrip($c, $id);
        if ($trip->status !== 'loading') {
            $this->fail('trip', "{$trip->code} is {$trip->label()}: it can depart only while Loading.");
        }
        $this->handoverAccepted($trip);
        if (! $trip->onBoard()->exists()) {
            $this->fail('trip', "{$trip->code} has no parcels yet. Add parcels before departing.");
        }
        $missing = $trip->onBoard()->where('status', '!=', 'loaded')->with('parcel')->get()->pluck('parcel.tracking_number');
        if ($missing->isNotEmpty()) {
            $this->fail('trip', 'Scan every parcel before departing. Not loaded yet: ' . $missing->implode(', ') . '.');
        }
        $seal = strtoupper(trim((string) $seal));
        if (! preg_match(self::SEAL_PATTERN, $seal)) {
            $this->fail('seal_number', 'Enter the seal number as BH- followed by 7 digits (e.g. BH-0004521).');
        }
        $this->at($trip->origin, $lat, $lng, 'depart');

        DB::transaction(function () use ($trip, $seal, $lat, $lng) {
            $trip->update(['status' => 'in_transit', 'departed_at' => now(), 'seal_number' => $seal]);
            $trip->log('departed', null, ['new_value' => $seal]);
            $trip->locations()->create(['lat' => $lat, 'lng' => $lng, 'recorded_at' => now()]);
            foreach ($trip->onBoard()->with('parcel')->get() as $item) {
                $p = $item->parcel;
                $p->linehaulStep = true;
                $p->update(['status' => 'linehaul', 'current_hub_id' => null]);
                $p->timeline("In Transit ({$trip->route()})");
                app(ParcelService::class)->syncOrder($p);
            }
        });

        return $trip;
    }

    /** reason from the list and a new ETA later than now (a Vehicle breakdown may carry a photo). */
    public function delay(Courier $c, int $id, ?string $reason, ?string $eta, ?UploadedFile $photo = null): LinehaulTrip
    {
        $trip = $this->courierTrip($c, $id);
        if ($trip->status !== 'in_transit') {
            $this->fail('reason', "{$trip->code} is {$trip->label()}: delays are reported while In Transit.");
        }
        if (! in_array($reason, self::DELAY_REASONS, true)) {
            $this->fail('reason', 'Choose the reason for the delay (' . implode(', ', self::DELAY_REASONS) . ').');
        }
        try {
            // A typed time is Philippine time (a value with its own offset keeps it).
            $at = $eta ? \Carbon\Carbon::parse($eta, config('app.business_timezone')) : null;
        } catch (\Throwable) {
            $at = null;
        }
        if (! $at || ! $at->isFuture()) {
            $this->fail('eta', 'The new ETA must be later than the current time.');
        }
        $path = $photo?->store('linehaul-incidents', 'local');
        $trip->update(['delayed' => true, 'eta_at' => $at, 'delay_reason' => $reason]);
        $trip->log($reason === 'Vehicle breakdown' ? 'breakdown' : 'delay', $reason, ['new_value' => $at->toDateTimeString(), 'photo_path' => $path]);
        $this->notifyBoth($trip, $reason === 'Vehicle breakdown' ? "Vehicle breakdown on {$trip->code}" : "{$trip->code} is delayed",
            "{$c->fullName()} ({$trip->route()}): $reason. New ETA " . $at->format('M j, g:i A') . ($reason === 'Vehicle breakdown' ? '. The sending company can reassign the trip to another On Duty courier.' : '.'));

        return $trip;
    }

    public function arrived(Courier $c, int $id, ?float $lat, ?float $lng): LinehaulTrip
    {
        $trip = $this->courierTrip($c, $id);
        if ($trip->status !== 'in_transit') {
            $this->fail('trip', "{$trip->code} is {$trip->label()}.");
        }
        if ($trip->needs_rescan) {
            $this->fail('trip', 'Scan every parcel of ' . $trip->code . ' again before continuing (' . $trip->onBoard()->where('status', 'loaded')->count() . '/' . $trip->onBoard()->count() . ' scanned).');
        }
        $this->at($trip->destination, $lat, $lng, 'mark Arrived');
        $trip->update(['status' => 'arrived', 'arrived_at' => now(), 'delayed' => false]);
        $trip->log('arrived');
        $this->notifyBoth($trip, "{$trip->code} arrived at {$trip->destination->code}", "Check the seal ({$trip->seal_number}) and receive the {$trip->onBoard()->count()} parcel(s).");

        return $trip;
    }

    /** The courier's note about a discrepancy (also after the trip is completed). */
    public function courierNote(Courier $c, int $id, ?string $note): LinehaulTrip
    {
        $trip = LinehaulTrip::where('sorting_center_id', $c->sorting_center_id)
            ->where(fn ($q) => $q->where('courier_id', $c->id)->orWhere('paid_courier_id', $c->id))->find($id);
        abort_unless($trip, 404, 'This trip is not yours.');
        $note = trim((string) $note);
        if (mb_strlen($note) < 10 || mb_strlen($note) > 300) {
            $this->fail('note', 'Write the note in 10 to 300 characters.');
        }
        $trip->update(['courier_note' => $note, 'needs_courier_note' => false]);
        $trip->log('courier_note', $note);

        return $trip;
    }

    // ── Destination hub: receiving ───────────────────────────────

    /** The seal at the destination; a different number needs a note and flags Seal Discrepancy. */
    public function confirmSeal(LinehaulTrip $trip, ?string $seal, ?string $note): LinehaulTrip
    {
        if ($trip->status !== 'arrived') {
            $this->fail('seal_number', "{$trip->code} is {$trip->label()}: the seal is checked when it has arrived.");
        }
        $seal = strtoupper(trim((string) $seal));
        if (! preg_match(self::SEAL_PATTERN, $seal)) {
            $this->fail('seal_number', 'Enter the seal number as BH- followed by 7 digits (e.g. BH-0004521).');
        }
        if ($seal === $trip->seal_number) {
            $trip->update(['received_seal' => $seal]);
            $trip->log('seal_ok', null, ['new_value' => $seal]);
            return $trip;
        }
        $note = trim((string) $note);
        if (mb_strlen($note) < 10 || mb_strlen($note) > 300) {
            $this->fail('note', "Seal $seal does not match the manifest ({$trip->seal_number}). Add a note (10 to 300 characters) to continue receiving.");
        }
        $trip->update(['received_seal' => $seal, 'seal_discrepancy' => true, 'seal_note' => $note, 'needs_courier_note' => true]);
        $trip->log('seal_discrepancy', $note, ['old_value' => $trip->seal_number, 'new_value' => $seal]);
        $this->notifyBoth($trip, "Seal discrepancy on {$trip->code}", "Manifest {$trip->seal_number}, found $seal: $note");

        return $trip;
    }

    /** Scan a parcel in at a hub: it must be on a trip that arrived at this hub, with the seal checked. */
    public function receiveScan(User $sc, int $hubId, string $tracking): array
    {
        $hub = LogisticsHub::where('sorting_center_id', $sc->id)->find($hubId) ?? $this->fail('hub_id', 'Choose the hub you are receiving at.');
        $tracking = strtoupper(trim($tracking));
        $parcel = Parcel::where('tracking_number', $tracking)->first() ?? $this->fail('tracking', "Parcel $tracking was not found.");
        $item = LinehaulTripParcel::where('parcel_id', $parcel->id)->whereIn('status', ['pending', 'loaded'])
            ->whereHas('trip', fn ($q) => $q->where(fn ($w) => $w->where('destination_sorting_center_id', $sc->id)
                ->orWhere(fn ($o) => $o->whereNull('destination_sorting_center_id')->where('sorting_center_id', $sc->id)))
                ->whereIn('status', ['in_transit', 'arrived']))->first()
            ?? $this->fail('tracking', "$tracking is not on a trip coming to {$hub->code}.");
        $trip = $item->trip;
        if ((int) $trip->destination_hub_id !== $hub->id) {
            $this->fail('tracking', "$tracking belongs to {$trip->destination->code} ({$trip->code}), not {$hub->code}.");
        }
        if ($trip->status !== 'arrived') {
            $this->fail('tracking', "{$trip->code} has not arrived at {$hub->code} yet.");
        }
        if (! $trip->received_seal) {
            $this->fail('tracking', "Check the seal of {$trip->code} first.");
        }
        $this->receiveItem($trip, $item, $parcel);

        [$in, $all] = [$trip->onBoard()->where('status', 'received')->count(), $trip->onBoard()->count()];

        return ['message' => "$tracking received at {$hub->code} ($in/$all).", 'received' => $in, 'total' => $all, 'trip_id' => $trip->id];
    }

    /** A parcel damaged at unloading: a photo is required. */
    public function markDamaged(LinehaulTrip $trip, Parcel $parcel, ?UploadedFile $photo): LinehaulTrip
    {
        if ($trip->status !== 'arrived') {
            $this->fail('photo', "{$trip->code} is {$trip->label()}.");
        }
        $item = $trip->onBoard()->where('parcel_id', $parcel->id)->first() ?? $this->fail('parcel', "{$parcel->tracking_number} is not on {$trip->code}.");
        if (! $photo) {
            $this->fail('photo', 'Take a photo of the damage first (JPG or PNG, up to 5 MB).');
        }
        $path = $photo->store('linehaul-incidents', 'local');
        $item->update(['status' => 'damaged', 'damage_photo_path' => $path, 'received_at' => now()]);
        $parcel->linehaulStep = true;
        $parcel->update(['status' => 'damaged', 'current_hub_id' => $trip->destination_hub_id, 'current_sorting_center_id' => $trip->receiverId(), 'exception_photo_path' => $path]);
        $parcel->timeline("Damaged at {$trip->destination->code}");
        $trip->update(['needs_courier_note' => true]);
        $trip->log('damaged', null, ['old_value' => $parcel->tracking_number, 'photo_path' => $path]);
        $this->notify($parcel->seller_id, 'Parcel damaged in transit', "{$parcel->tracking_number} arrived damaged at {$trip->destination->code}.");

        return $trip;
    }

    /**
     * Close receiving: parcels not scanned in become Missing in Transit (asked first). Any discrepancy makes the trip
     * Completed with Discrepancy; the courier who handed it over is paid.
     */
    public function closeReceiving(LinehaulTrip $trip, bool $confirmed): LinehaulTrip
    {
        if ($trip->status !== 'arrived') {
            $this->fail('trip', "{$trip->code} is {$trip->label()}: receiving is closed after it arrives.");
        }
        if (! $trip->received_seal) {
            $this->fail('trip', "Check the seal of {$trip->code} first.");
        }
        $missing = $trip->onBoard()->whereIn('status', ['pending', 'loaded'])->with('parcel')->get();
        if ($missing->isNotEmpty() && ! $confirmed) {
            throw ValidationException::withMessages([
                'missing' => 'Not scanned in: ' . $missing->pluck('parcel.tracking_number')->implode(', ') . '. Close anyway? They become Missing in Transit.',
            ]);
        }

        return DB::transaction(function () use ($trip, $missing) {
            foreach ($missing as $item) {
                $p = $item->parcel;
                $item->update(['status' => 'missing']);
                $p->linehaulStep = true;
                $p->update(['status' => 'missing_in_transit']);
                $p->timeline("Missing in Transit ({$trip->route()})");
                $trip->log('missing', null, ['old_value' => $p->tracking_number]);
                $this->notify($p->seller_id, 'Parcel missing in transit', "{$p->tracking_number} was not found when {$trip->code} arrived at {$trip->destination->code}. " . $trip->company?->name . ' is checking.');
            }
            $discrepancy = $trip->seal_discrepancy || $missing->isNotEmpty() || $trip->onBoard()->where('status', 'damaged')->exists();
            $courier = $trip->courier;
            $pay = $courier ? self::payFor($courier) : 0;
            $trip->update([
                'status' => $discrepancy ? 'completed_discrepancy' : 'completed', 'completed_at' => now(),
                'pay' => $pay, 'paid_courier_id' => $courier?->id, 'needs_courier_note' => $discrepancy && ! $trip->courier_note,
            ]);
            $trip->log($discrepancy ? 'completed_discrepancy' : 'completed', null, ['new_value' => number_format($pay, 2)]);
            if ($courier) {
                $courier->increment('total_earnings', $pay);
                $this->notify($courier->user_id, "{$trip->code} completed", '₱' . number_format($pay, 2) . " earned for {$trip->route()}."
                    . ($discrepancy ? ' There was a discrepancy: open the trip and add your note.' : ''));
            }
            if ($discrepancy) {
                $this->notifyBoth($trip, "{$trip->code} completed with discrepancy", 'Missing: ' . $missing->count() . ($trip->seal_discrepancy ? ', seal discrepancy' : '') . '.');
            } elseif ($trip->crossCompany()) {
                $this->notify((int) $trip->sorting_center_id, "{$trip->code} was received", \App\Support\SortingCenters::nameOf($trip->destinationCompany) . " received all {$trip->onBoard()->count()} parcel(s) at {$trip->destination->code}.");
            }

            return $trip;
        });
    }

    // ── Between two sorting centers ──────────────────────────────

    /**
     * The hub a parcel should go to: the sorting center that serves the buyer's municipality, else the hub nearest
     * the buyer (their map pin, or the middle of their barangay). Null when this company delivers it itself.
     */
    public function bestHubFor(Parcel $parcel, User $from): ?LogisticsHub
    {
        $flow = app(ShipmentWorkflow::class);
        $next = $flow->nextDestination($parcel, $from);
        if ($next['type'] === 'local') {
            return null;
        }
        if ($next['type'] === 'transfer' && $next['center']) {
            return LogisticsHub::forCompany($next['center'])->first();
        }
        [$lat, $lng] = \App\Services\NearestRiders::destination($parcel);
        if ($lat === null) {
            return null;
        }

        return LogisticsHub::where('sorting_center_id', '!=', $from->id)->whereNotNull('latitude')->get()
            ->sortBy(fn (LogisticsHub $h) => $h->distanceTo($lat, $lng))->first();
    }

    /**
     * Put parcels on a trip before it departs (no limit). They must be waiting at the trip's origin hub and not on
     * another trip. The receiving sorting center is told how many were added.
     */
    public function addParcels(User $sc, LinehaulTrip $trip, array $ids, bool $notify = true): int
    {
        if ((int) $trip->sorting_center_id !== (int) $sc->id) {
            $this->fail('parcel_ids', 'Only the sending sorting center can add parcels.');
        }
        if (! in_array($trip->status, LinehaulTrip::BEFORE_DEPARTURE, true)) {
            $this->fail('parcel_ids', "{$trip->code} is {$trip->label()}: parcels can be added only before it departs.");
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids) {
            $this->fail('parcel_ids', 'Choose the parcels to add.');
        }
        $eligible = $this->eligibleParcels($sc, $trip->origin)->keyBy('id');
        $wrong = array_diff($ids, $eligible->keys()->all());
        if ($wrong) {
            $this->fail('parcel_ids', 'These parcels are not waiting at ' . $trip->origin->code . ': ' . Parcel::whereIn('id', $wrong)->pluck('tracking_number')->implode(', ') . '.');
        }
        DB::transaction(function () use ($trip, $ids) {
            foreach ($ids as $id) {
                $trip->items()->create(['parcel_id' => $id, 'status' => 'pending']);
                Parcel::whereKey($id)->whereNull('current_hub_id')->update(['current_hub_id' => $trip->origin_hub_id]);
            }
            $trip->log('parcels_added', null, ['new_value' => count($ids) . ' parcel(s)']);
        });
        if ($notify && $trip->crossCompany() && $trip->handover_status !== 'rejected') {
            $this->notify($trip->receiverId(), count($ids) . " parcel(s) added to {$trip->code}", "{$trip->code} ({$trip->route()}) now has {$trip->onBoard()->count()} parcel(s).");
        }

        return count($ids);
    }

    /** Auto-assign: every parcel waiting at the origin whose nearest hub to the buyer is this trip's destination. */
    public function autoAddParcels(User $sc, LinehaulTrip $trip): int
    {
        $ids = $this->parcelsFor($sc, $trip->origin, (int) $trip->destination_hub_id)->pluck('id')->all();

        return $ids ? $this->addParcels($sc, $trip, $ids) : 0;
    }

    /** The parcels waiting at the origin hub that should go to this destination hub (nearest to their buyers). */
    public function parcelsFor(User $sc, LogisticsHub $origin, int $destinationHubId): Collection
    {
        return $this->eligibleParcels($sc, $origin)->load('order')
            ->filter(fn (Parcel $p) => $this->bestHubFor($p, $sc)?->id === $destinationHubId)->values();
    }

    /**
     * Auto-assign everything waiting at the origin hub: the parcels are grouped by the hub nearest their buyers; each
     * group joins an open delivery to that hub (not departed, not rejected), or a new delivery is created for it.
     * Parcels this company delivers itself are left out. Returns [['trip' => LinehaulTrip, 'added' => n, 'new' => bool], …].
     */
    public function autoAssignAll(User $sc, LogisticsHub $origin): array
    {
        $groups = $this->eligibleParcels($sc, $origin)->load('order')
            ->groupBy(fn (Parcel $p) => $this->bestHubFor($p, $sc)?->id ?? 0)
            ->forget(0);
        $result = [];
        foreach ($groups as $hubId => $parcels) {
            $trip = LinehaulTrip::where('sorting_center_id', $sc->id)->where('origin_hub_id', $origin->id)
                ->where('destination_hub_id', $hubId)->whereIn('status', LinehaulTrip::BEFORE_DEPARTURE)
                ->where(fn ($q) => $q->whereNull('handover_status')->orWhere('handover_status', '!=', 'rejected'))
                ->latest('id')->first();
            $new = ! $trip;
            if ($new) {
                $trip = $this->createTrip($sc, ['origin_hub_id' => $origin->id, 'destination_hub_id' => $hubId, 'parcel_ids' => $parcels->pluck('id')->all()]);
                $added = $parcels->count();
            } else {
                $added = $this->addParcels($sc, $trip, $parcels->pluck('id')->all());
            }
            $result[] = ['trip' => $trip->fresh(['destination', 'destinationCompany']), 'added' => $added, 'new' => $new];
        }

        return $result;
    }

    /** A trip to another company's hub loads and departs only after that company accepted it. */
    private function handoverAccepted(LinehaulTrip $trip): void
    {
        if ($trip->handover_status === 'pending') {
            $this->fail('trip', "{$trip->code} is waiting for " . \App\Support\SortingCenters::nameOf($trip->destinationCompany) . " to accept it. It can load once they accept.");
        }
        if ($trip->handover_status === 'rejected') {
            $this->fail('trip', "{$trip->code} was rejected by the receiving sorting center.");
        }
    }

    /** Both sorting centers of the trip (one notification when it stays inside one company). */
    private function notifyBoth(LinehaulTrip $trip, string $title, string $body): void
    {
        $this->notify((int) $trip->sorting_center_id, $title, $body);
        if ($trip->crossCompany()) {
            $this->notify($trip->receiverId(), $title, $body);
        }
    }

    /**
     * The receiving company accepts (the truck may then load and depart) or rejects the trip before it departs.
     * A rejected trip is cancelled: its parcels stay at the origin hub.
     */
    public function respondHandover(User $sc, LinehaulTrip $trip, bool $accept, ?string $note = null): LinehaulTrip
    {
        if (! $trip->crossCompany() || $trip->receiverId() !== (int) $sc->id) {
            $this->fail('trip', 'Only the receiving sorting center can accept or reject this trip.');
        }
        if ($trip->handover_status !== 'pending') {
            $this->fail('trip', "{$trip->code} was already " . ($trip->handover_status ?? 'answered') . '.');
        }
        $note = trim((string) $note);
        if (! $accept && (mb_strlen($note) < 5 || mb_strlen($note) > 300)) {
            $this->fail('note', 'Say why you are rejecting the trip (5 to 300 characters).');
        }
        $name = \App\Support\SortingCenters::nameOf($sc);

        return DB::transaction(function () use ($sc, $trip, $accept, $note, $name) {
            $trip->update(['handover_status' => $accept ? 'accepted' : 'rejected', 'handover_responded_at' => now(),
                'handover_responded_by' => $sc->id, 'handover_note' => $note ?: null]);
            $trip->log($accept ? 'handover_accepted' : 'handover_rejected', $note ?: null, ['new_value' => $name]);
            if ($accept) {
                $this->notify((int) $trip->sorting_center_id, "{$name} accepted {$trip->code}", "The trip to {$trip->destination->code} can load and depart." . ($note ? " Note: $note" : ''));
                $this->notify($trip->courier?->user_id, "{$trip->code} can load", "{$name} accepted the trip. You can start loading.");

                return $trip;
            }
            $this->cancel($trip, "Rejected by {$name}: $note");
            $this->notify((int) $trip->sorting_center_id, "{$name} rejected {$trip->code}", $note . ' The parcels stay at ' . $trip->origin->code . '.');

            return $trip;
        });
    }

    /** A message between the two sorting centers on the trip (e.g. a delay or a problem); the other side is notified. */
    public function postMessage(User $sc, LinehaulTrip $trip, ?string $body): void
    {
        if (! $trip->involves((int) $sc->id)) {
            $this->fail('body', 'This trip is not yours.');
        }
        $body = trim((string) $body);
        if ($body === '' || mb_strlen($body) > 1000) {
            $this->fail('body', 'Write a message (up to 1,000 characters).');
        }
        $trip->messages()->create(['user_id' => $sc->id, 'body' => $body]);
        $other = (int) $sc->id === (int) $trip->sorting_center_id ? $trip->receiverId() : (int) $trip->sorting_center_id;
        if ($other !== (int) $sc->id) {
            $this->notify($other, \App\Support\SortingCenters::nameOf($sc) . " on {$trip->code}", \Illuminate\Support\Str::limit($body, 160));
        }
    }

    /**
     * Receive the whole trip at once (after the seal check): every parcel still on it is received, except the
     * ones listed as not found, which become Missing in Transit when receiving closes. The parcels then belong
     * to the receiving company and wait in its Parcel Sorting.
     */
    public function receiveAll(User $sc, LinehaulTrip $trip, array $notFoundIds = []): LinehaulTrip
    {
        if ($trip->receiverId() !== (int) $sc->id) {
            $this->fail('trip', 'Only the receiving sorting center can receive this trip.');
        }
        if ($trip->status !== 'arrived') {
            $this->fail('trip', "{$trip->code} is {$trip->label()}: parcels are received after it arrives.");
        }
        if (! $trip->received_seal) {
            $this->fail('trip', "Check the seal of {$trip->code} first.");
        }
        $notFound = array_map('intval', $notFoundIds);
        DB::transaction(function () use ($trip, $notFound) {
            foreach ($trip->onBoard()->whereIn('status', ['pending', 'loaded'])->with('parcel')->get() as $item) {
                if (! in_array((int) $item->parcel_id, $notFound, true) && $item->parcel) {
                    $this->receiveItem($trip, $item, $item->parcel);
                }
            }
        });

        return $this->closeReceiving($trip->fresh(), true);
    }

    /** One parcel received at the destination hub: it is at that hub, belongs to the receiving company, and can be sorted. */
    private function receiveItem(LinehaulTrip $trip, LinehaulTripParcel $item, Parcel $parcel): void
    {
        $item->update(['status' => 'received', 'received_at' => now()]);
        $parcel->linehaulStep = true;
        $parcel->update(['status' => 'picked_up', 'current_hub_id' => $trip->destination_hub_id, 'current_sorting_center_id' => $trip->receiverId(),
            'transfer_status' => null, 'received_at' => now()]);
        $parcel->timeline('At Hub (' . ($trip->destination?->code ?? 'destination') . ')');
    }

    // ── Suspension ───────────────────────────────────────────────

    /** Suspended: Off Duty, and trips not yet departed go back to Unassigned (In Transit stays until handover). */
    public function suspend(Courier $c, string $reason): void
    {
        DB::transaction(function () use ($c, $reason) {
            $c->forceFill(['status' => 'suspended', 'on_duty' => false, 'rejection_reason' => $reason])->save();
            foreach ($c->trips()->whereIn('status', ['assigned', 'accepted', 'loading'])->get() as $trip) {
                $trip->items()->where('status', 'loaded')->update(['status' => 'pending', 'loaded_at' => null]);
                $trip->update(['status' => 'scheduled', 'courier_id' => null]);
                $trip->log('unassigned', 'Courier suspended: ' . $reason, ['courier_id' => $c->id, 'old_value' => $c->fullName()]);
            }
            $this->notify($c->user_id, 'Your courier account was suspended', $reason);
        });
    }
}
