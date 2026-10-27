<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A hub of a logistics company (HUB-QC "QC First-Mile Hub"). A courier drives from its home hub to other hubs. */
class LogisticsHub extends Model
{
    protected $fillable = ['sorting_center_id', 'code', 'name', 'address', 'latitude', 'longitude'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float'];

    /** Depart and Arrived only this close to the hub (A39). */
    public const GEOFENCE_METERS = 500;

    /** Meters from the hub (null when the hub has no map location yet). */
    public function distanceTo(?float $lat, ?float $lng): ?float
    {
        if ($this->latitude === null || $this->longitude === null || $lat === null || $lng === null) {
            return null;
        }
        $r = 6371000;
        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function company(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }

    public function label(): string { return "{$this->code} ({$this->name})"; }

    /** The company's hubs; a company with none gets its Main Hub (HUB-{id}) first. */
    public static function forCompany(User $sc): \Illuminate\Support\Collection
    {
        if (! self::where('sorting_center_id', $sc->id)->exists()) {
            $company = SortingCenterApplication::where('user_id', $sc->id)->where('status', 'approved')->value('business_name') ?: $sc->name;
            self::create(['sorting_center_id' => $sc->id, 'code' => 'HUB-' . $sc->id, 'name' => $company . ' Main Hub']);
        }

        return self::where('sorting_center_id', $sc->id)->orderBy('code')->get();
    }
}
