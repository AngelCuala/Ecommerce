<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rider of a logistics company (sorting center). Riders sign in with a courier account
 * (riders.user_id), go Online to receive job offers, pick parcels up from sellers and
 * deliver them to buyers. "Suspended" = approved but turned off by the logistics company.
 */
class Rider extends Model
{
    /** A rider counts as Online only while seen within this many minutes (session timeout, A22). */
    /** How long an Online rider stays Online without opening the app (12 hours). */
    public const ONLINE_MINUTES = 720;

    protected $fillable = [
        'user_id', 'sorting_center_id', 'full_name', 'phone',
        'vehicle_type', 'license_number', 'id_document_path',
        'area_id',
        'region_code', 'province_code', 'municipality_code', 'barangay_code',
        'application_status', 'rejection_reason', 'approved_at', 'approved_by',
        'is_active',
        'plate_number', 'license_expiry', 'license_path', 'or_cr_path', 'needs_reverification',
        'is_online', 'last_seen_at', 'can_transport_transfers',
    ];

    protected $casts = [
        'approved_at'          => 'datetime',
        'is_active'            => 'boolean',
        'license_expiry'       => 'date',
        'needs_reverification' => 'boolean',
        'is_online'            => 'boolean',
        'can_transport_transfers' => 'boolean',
        'last_seen_at'         => 'datetime',
    ];

    public function user(): BelongsTo          { return $this->belongsTo(User::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function area(): BelongsTo          { return $this->belongsTo(DeliveryArea::class, 'area_id'); }   // the main barangay

    /** Every barangay the rider covers (the main one included). */
    public function areas(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DeliveryArea::class, 'rider_areas', 'rider_id', 'area_id')->withTimestamps()->orderBy('name');
    }

    public function coversArea(?int $areaId): bool
    {
        if (! $areaId) {
            return false;
        }
        return (int) $this->area_id === $areaId || $this->areas->contains('id', $areaId);
    }

    /** Does an address (a seller's pickup address) lie in one of the rider's barangays? */
    public function coversAddress(?string $address): bool
    {
        if (! $address) {
            return false;
        }
        $names = $this->areas->pluck('name')->push($this->area?->name)->filter()->unique();
        return $names->contains(fn ($n) => stripos($address, $n) !== false);
    }

    /** "Bagong Kalsada, Banlic" */
    public function barangayNames(): string
    {
        return $this->areas->pluck('name')->push($this->area?->name)->filter()->unique()->implode(', ') ?: '—';
    }

    protected static function booted(): void
    {
        // The main barangay is always one of the rider's barangays.
        static::saved(function (Rider $r) {
            if ($r->area_id && $r->wasChanged('area_id') || ($r->wasRecentlyCreated && $r->area_id)) {
                $r->areas()->syncWithoutDetaching([$r->area_id]);
                $r->unsetRelation('areas');
            }
        });
    }
    public function approvedBy(): BelongsTo    { return $this->belongsTo(User::class, 'approved_by'); }

    public function parcelDeliveries(): HasMany { return $this->hasMany(ParcelDelivery::class, 'rider_id'); }
    public function pickups(): HasMany          { return $this->hasMany(ParcelPickup::class, 'rider_id'); }
    public function offers(): HasMany           { return $this->hasMany(RiderJobOffer::class, 'rider_id'); }
    public function remittances(): HasMany      { return $this->hasMany(RiderRemittance::class, 'rider_id'); }

    public function isPending(): bool   { return $this->application_status === 'pending'; }
    public function isApproved(): bool  { return $this->application_status === 'approved'; }

    /** Two- and three-wheel vehicles do barangay pickups and deliveries; anything else is a 4-wheel Linehaul courier. */
    public const SMALL_VEHICLES = ['motorcycle', 'motorbike', 'motor', 'bike', 'bicycle', 'scooter', 'e-bike', 'ebike', 'tricycle'];

    public function isFourWheel(): bool
    {
        $v = strtolower(trim((string) $this->vehicle_type));
        return $v !== '' && ! in_array($v, self::SMALL_VEHICLES, true);
    }
    public function isRejected(): bool  { return $this->application_status === 'rejected'; }
    /** Made by the logistics company; waits for the rider to set a password from the emailed link. */
    public function isPendingActivation(): bool { return $this->application_status === 'pending_activation'; }
    /** Approved, but turned off by the logistics company: can't go Online or take jobs. */
    public function isSuspended(): bool { return $this->isApproved() && ! $this->is_active; }

    /**
     * Online until the rider taps Go Offline or logs out: closing the app does not make them Offline.
     * Only a rider not seen for 12 hours (e.g. forgot to go Offline at the end of the day) counts as Offline.
     */
    public function isOnlineNow(): bool
    {
        if (! $this->is_online || ! $this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_MINUTES));
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isPending()   => 'Pending',
            $this->isPendingActivation() => 'Pending Activation',
            $this->isRejected()  => 'Rejected',
            $this->isSuspended() => 'Suspended',
            default              => 'Active',
        };
    }

    /** Jobs not finished yet: pickups to collect/carry and deliveries to make. */
    public function activeJobsCount(): int
    {
        return $this->pickups()->whereIn('status', ParcelPickup::OPEN)->count()
            + $this->parcelDeliveries()->whereIn('status', ParcelDelivery::OPEN)->count();
    }

    /** The logistics company's name (its approved application, else the account name). */
    public function companyName(): string
    {
        $sc = $this->sortingCenter;
        if (! $sc) {
            return '—';
        }
        return SortingCenterApplication::where('user_id', $sc->id)->where('status', 'approved')->value('business_name') ?: $sc->name;
    }
}
