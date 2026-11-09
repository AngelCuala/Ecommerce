<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\LinehaulTrip;
use App\Models\LogisticsHub;
use App\Models\Rider;
use Illuminate\Support\Facades\DB;

/**
 * A rider with a 4-wheeler (car, van, truck) carries parcels in bulk between hubs. The hub-to-hub trip rules
 * (App\Services\Linehaul, the trip pages and the app) work on a truck record (App\Models\Courier with a home hub);
 * this keeps that record in step with the rider, so a sorting center only ever manages riders:
 *  - a 4-wheel rider gets a truck record: their company, its first hub, name, phone, vehicle, plate, license;
 *    Active when the rider is Active, Suspended when the rider is suspended, Pending until approved;
 *  - a rider who switches to a motorcycle, tricycle or bicycle has the truck record turned off
 *    (Off Duty, trips not yet departed go back to Unassigned).
 */
class TruckRiders
{
    /** Rider columns that change the truck record. */
    public const WATCHED = ['vehicle_type', 'plate_number', 'application_status', 'is_active', 'sorting_center_id', 'full_name',
        'phone', 'license_number', 'license_expiry', 'license_path', 'or_cr_path'];

    /** Load capacity (kg) for a 4-wheeler the company did not give one. */
    public const CAPACITY = ['Car' => 300, 'Van' => 1000, 'Truck' => 4000, '4-Wheeler Closed Van Truck' => 3000, '6-Wheeler Truck' => 8000];

    public function sync(Rider $rider): ?Courier
    {
        // A roster entry without a login of its own (Add Rider's quick form uses the company's account) can't sign in,
        // go Online or take trips, so it gets no truck record.
        if ((int) $rider->user_id === (int) $rider->sorting_center_id) {
            return null;
        }
        $truck = Courier::where('user_id', $rider->user_id)->first();

        if (! $rider->isTruck()) {
            if ($truck && $truck->home_hub_id && in_array($truck->status, ['approved', 'pending', 'pending_activation'], true)) {
                $this->turnOff($truck, 'The rider no longer drives a 4-wheeler.');
            }

            return $truck;
        }

        $status = match ($rider->application_status) {
            'approved' => $rider->is_active ? 'approved' : 'suspended',
            'rejected' => 'rejected',
            'pending_activation' => 'pending_activation',
            default => 'pending',
        };
        $wasActive = $truck && $truck->status === 'approved';
        $hubId = ($truck && $truck->home_hub_id && LogisticsHub::where('sorting_center_id', $rider->sorting_center_id)->whereKey($truck->home_hub_id)->exists())
            ? $truck->home_hub_id
            : LogisticsHub::where('sorting_center_id', $rider->sorting_center_id)->orderBy('id')->value('id');
        $parts = preg_split('/\s+/', trim((string) $rider->full_name)) ?: [''];
        $last = count($parts) > 1 ? array_pop($parts) : '';
        $user = $rider->user;
        $birthday = $user?->birthday ?? '1990-01-01';
        $vehicleChanged = ! $truck || $truck->vehicle_type !== $rider->vehicle_type;

        $truck ??= new Courier(['user_id' => $rider->user_id]);
        $truck->forceFill([
            'user_id' => $rider->user_id,
            'sorting_center_id' => $rider->sorting_center_id,
            'home_hub_id' => $hubId,
            'first_name' => implode(' ', $parts) ?: $rider->full_name,
            'last_name' => $last,
            'contact_no' => (string) ($rider->phone ?? $user?->phone ?? ''),
            'sex' => in_array($user?->sex, ['Male', 'Female', 'Other'], true) ? $user->sex : $truck->sex,
            'birthday' => $truck->birthday ?? $birthday,
            'age' => $truck->age ?? (int) \Carbon\Carbon::parse($birthday)->age,
            'vehicle_type' => $rider->vehicle_type,
            'plate_number' => (string) ($rider->plate_number ?? ''),
            'license_number' => $rider->license_number,
            'license_type' => $truck->license_type ?? 'Professional',
            'license_expiry' => $rider->license_expiry,
            'load_capacity_kg' => $vehicleChanged || ! $truck->load_capacity_kg ? (self::CAPACITY[$rider->vehicle_type] ?? 1000) : $truck->load_capacity_kg,
            'or_cr_path' => (string) ($rider->or_cr_path ?? ''),
            'id_license_path' => (string) ($rider->license_path ?? ''),
            'status' => $status,
            'reviewed_by' => $truck->reviewed_by ?? $rider->approved_by ?? $rider->sorting_center_id,
            'reviewed_at' => $truck->reviewed_at ?? $rider->approved_at,
            'submitted_at' => $truck->submitted_at ?? now(),
        ]);
        if ($status !== 'approved') {
            $truck->on_duty = false;
        }
        $truck->save();

        if ($wasActive && $status !== 'approved') {
            $this->releaseTrips($truck, 'Rider ' . ($status === 'suspended' ? 'suspended' : 'no longer active'));
        }

        return $truck;
    }

    /** Off Duty and out of the trip list; trips not yet departed go back to Unassigned. */
    private function turnOff(Courier $truck, string $reason): void
    {
        DB::transaction(function () use ($truck, $reason) {
            $truck->forceFill(['status' => 'suspended', 'on_duty' => false, 'rejection_reason' => $reason])->save();
            $this->releaseTrips($truck, $reason);
        });
    }

    private function releaseTrips(Courier $truck, string $reason): void
    {
        foreach ($truck->trips()->whereIn('status', ['assigned', 'accepted', 'loading'])->get() as $trip) {
            $trip->items()->where('status', 'loaded')->update(['status' => 'pending', 'loaded_at' => null]);
            $trip->update(['status' => 'scheduled', 'courier_id' => null]);
            $trip->log('unassigned', $reason, ['courier_id' => $truck->id, 'old_value' => $truck->fullName()]);
        }
    }

    /** A truck record made before riders and couriers were one (or from the app's Add Courier): give it a rider. */
    public function adopt(Courier $truck): ?Rider
    {
        if (! $truck->home_hub_id || ! $truck->sorting_center_id || Rider::where('user_id', $truck->user_id)->exists()) {
            return null;
        }
        $status = match ($truck->status) {
            'approved', 'suspended' => 'approved',
            'rejected' => 'rejected',
            'pending_activation' => 'pending_activation',
            default => 'pending',
        };
        $area = \App\Models\DeliveryArea::where('sorting_center_id', $truck->sorting_center_id)->orderBy('id')->first();

        $rider = new Rider();
        $rider->forceFill([
            'user_id' => $truck->user_id, 'sorting_center_id' => $truck->sorting_center_id,
            'full_name' => $truck->fullName(), 'phone' => $truck->contact_no,
            // The rider vehicle list has Car, Van and Truck; the old truck names become Truck.
            'vehicle_type' => in_array($truck->vehicle_type, ['Car', 'Van', 'Truck'], true) ? $truck->vehicle_type : 'Truck',
            'plate_number' => $truck->plate_number, 'license_number' => $truck->license_number, 'license_expiry' => $truck->license_expiry,
            'license_path' => $truck->id_license_path ?: null, 'or_cr_path' => $truck->or_cr_path ?: null,
            'area_id' => $area?->id, 'application_status' => $status, 'is_active' => $truck->status !== 'suspended',
            'approved_at' => $status === 'approved' ? ($truck->reviewed_at ?? now()) : null,
            'approved_by' => $status === 'approved' ? $truck->sorting_center_id : null,
        ]);
        $rider->saveQuietly();   // the truck record already exists and stays as it is

        return $rider;
    }
}
