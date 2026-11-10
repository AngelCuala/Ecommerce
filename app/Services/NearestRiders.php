<?php

namespace App\Services;

use App\Models\DeliveryArea;
use App\Models\Parcel;
use App\Models\Rider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * When a parcel's barangay has no rider, the logistics company may give it to the nearest of its own riders.
 * Riders have no live GPS here, so a rider's place is the middle of their barangay; the parcel's place is the
 * buyer's map pin, or the middle of the buyer's barangay when there is no pin. Barangays are placed on the map
 * once from OpenStreetMap (`php artisan areas:locate`, also run by the scheduler for new barangays).
 */
class NearestRiders
{
    /** Active, approved riders of the parcel's company who cover the parcel's barangay. */
    public static function forArea(Collection $riders, ?int $areaId): Collection
    {
        return $riders->filter(fn (Rider $r) => $r->coversArea((int) $areaId))->values();
    }

    /**
     * The company's riders, nearest first, each with ['rider' => Rider, 'km' => ?float]. Riders whose distance
     * is unknown (their barangay isn't on the map yet) come last, same city first.
     */
    public static function ranked(Parcel $parcel, Collection $riders): Collection
    {
        [$lat, $lng] = self::destination($parcel);
        $city = $parcel->area?->municipality_code;

        return $riders->map(function (Rider $r) use ($lat, $lng) {
            $a = $r->area;
            $km = ($lat !== null && $a?->latitude !== null) ? self::km($lat, $lng, (float) $a->latitude, (float) $a->longitude) : null;
            return ['rider' => $r, 'km' => $km === null ? null : round($km, 1)];
        })->sortBy(fn ($x) => [
            $x['km'] === null ? 1 : 0,
            $x['km'] ?? ((string) $x['rider']->area?->municipality_code === (string) $city ? 0 : 1),
            $x['rider']->is_online ? 0 : 1,
            $x['rider']->full_name,
        ])->values();
    }

    /** [lat, lng] of where the parcel goes: the buyer's pin, else the middle of the buyer's barangay. */
    public static function destination(Parcel $parcel): array
    {
        $addr = $parcel->order?->deliveryAddress;
        if ($addr && $addr->latitude && $addr->longitude) {
            return [(float) $addr->latitude, (float) $addr->longitude];
        }
        $area = $parcel->area;
        return $area && $area->latitude !== null ? [(float) $area->latitude, (float) $area->longitude] : [null, null];
    }

    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Places a barangay on the map from OpenStreetMap ("Barangay, City, Philippines"). Marks it as tried
     * either way. Nominatim allows one request a second, so the command waits between barangays.
     */
    public function locate(DeliveryArea $area): bool
    {
        $found = null;
        foreach ([$area->name . ', ' . $area->municipality . ', Philippines', $area->name . ', Philippines'] as $n => $q) {
            if ($n > 0) {
                usleep(1_100_000);   // one request a second
            }
            try {
                $res = Http::withHeaders(['User-Agent' => 'ALVY-marketplace/1.0 (alvyshop27@gmail.com)', 'Accept-Language' => 'en'])
                    ->timeout(12)->get('https://nominatim.openstreetmap.org/search', ['q' => $q, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'ph']);
                $found = $res->successful() ? ($res->json()[0] ?? null) : null;
            } catch (\Throwable $e) {
                report($e);
                $found = null;
            }
            if ($found) {
                break;
            }
        }
        $area->forceFill([
            'latitude'   => $found ? (float) $found['lat'] : null,
            'longitude'  => $found ? (float) $found['lon'] : null,
            'located_at' => now(),
        ])->saveQuietly();

        return (bool) $found;
    }
}
