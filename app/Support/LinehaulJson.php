<?php

namespace App\Support;

use App\Models\Courier;
use App\Models\LinehaulTrip;
use App\Models\LinehaulTripEvent;
use App\Models\LogisticsHub;
use App\Services\Linehaul;

/** Linehaul trips, couriers and hubs as the app shows them (courier app and the company's Trips screens). */
class LinehaulJson
{
    public static function hub(?LogisticsHub $h): ?array
    {
        return $h ? ['id' => $h->id, 'code' => $h->code, 'name' => $h->name, 'address' => $h->address, 'label' => $h->label(),
            'latitude' => $h->latitude, 'longitude' => $h->longitude] : null;
    }

    public static function courier(Courier $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->fullName(), 'vehicle_type' => $c->vehicle_type, 'plate_number' => $c->plate_number,
            'load_capacity_kg' => $c->load_capacity_kg, 'home_hub' => $c->homeHub?->code, 'status' => $c->status,
            'on_duty' => (bool) $c->on_duty, 'on_duty_now' => $c->isOnDutyNow(), 'duty_label' => $c->isSuspended() ? 'Suspended' : $c->dutyLabel(),
            'checklist_at' => $c->checklist_at?->toIso8601String(), 'last_seen_at' => $c->last_seen_at?->toIso8601String(),
            'location' => $c->last_lat !== null ? ['lat' => (float) $c->last_lat, 'lng' => (float) $c->last_lng] : null,
            'needs_reverification' => (bool) $c->needs_reverification, 'pay_per_trip' => Linehaul::payFor($c),
        ];
    }

    public static function trip(LinehaulTrip $t, bool $detail = false): array
    {
        $items = $t->onBoard()->with('parcel')->get();
        $row = [
            'id' => $t->id, 'code' => $t->code, 'status' => $t->status, 'status_label' => $t->label(),
            'origin' => self::hub($t->origin), 'destination' => self::hub($t->destination), 'route' => $t->route(),
            'parcels' => $items->count(), 'loaded' => $items->where('status', 'loaded')->count(), 'received' => $items->where('status', 'received')->count(),
            'weight_kg' => round((float) $items->sum(fn ($i) => (float) $i->parcel?->weight_kg), 2),
            'courier' => $t->courier ? ['id' => $t->courier->id, 'name' => $t->courier->fullName(), 'vehicle_type' => $t->courier->vehicle_type, 'plate_number' => $t->courier->plate_number] : null,
            'pay' => $t->courier && ! in_array($t->status, LinehaulTrip::DONE, true) ? Linehaul::payFor($t->courier) : (float) $t->pay,
            'scheduled_departure_at' => $t->scheduled_departure_at?->toIso8601String(),
            'accepted_at' => $t->accepted_at?->toIso8601String(), 'departed_at' => $t->departed_at?->toIso8601String(),
            'arrived_at' => $t->arrived_at?->toIso8601String(), 'completed_at' => $t->completed_at?->toIso8601String(),
            'seal_number' => $t->seal_number, 'delayed' => (bool) $t->delayed, 'delay_reason' => $t->delay_reason, 'eta_at' => $t->eta_at?->toIso8601String(),
            'needs_rescan' => (bool) $t->needs_rescan, 'needs_courier_note' => (bool) $t->needs_courier_note, 'courier_note' => $t->courier_note,
            'seal_discrepancy' => (bool) $t->seal_discrepancy, 'received_seal' => $t->received_seal,
        ];
        if ($detail) {
            $row['items'] = $items->map(fn ($i) => [
                'parcel_id' => $i->parcel_id, 'tracking' => $i->parcel?->tracking_number, 'weight_kg' => (float) $i->parcel?->weight_kg,
                'status' => $i->status, 'loaded_at' => $i->loaded_at?->toIso8601String(), 'received_at' => $i->received_at?->toIso8601String(),
                'receiver' => $i->parcel?->receiver_name, 'destination' => $i->parcel?->destination_municipality,
            ])->values();
            $row['events'] = $t->events()->with(['courier', 'user'])->get()->map(fn (LinehaulTripEvent $e) => [
                'type' => $e->type, 'courier' => $e->courier?->fullName(), 'by' => $e->user?->name, 'old_value' => $e->old_value,
                'new_value' => $e->new_value, 'reason' => $e->reason, 'at' => $e->created_at?->toIso8601String(),
            ])->values();
            $last = $t->locations()->reorder('recorded_at', 'desc')->first();
            $row['last_location'] = $last ? ['lat' => $last->lat, 'lng' => $last->lng, 'at' => $last->recorded_at->toIso8601String()] : null;
            $row['route_points'] = $t->locations()->get(['lat', 'lng', 'recorded_at'])->map(fn ($l) => ['lat' => $l->lat, 'lng' => $l->lng, 'at' => $l->recorded_at->toIso8601String()])->values();
        }

        return $row;
    }
}
