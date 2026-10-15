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
    public const ONLINE_MINUTES = 30;

    protected $fillable = [
        'user_id', 'sorting_center_id', 'full_name', 'phone',
        'vehicle_type', 'license_number', 'id_document_path',
        'area_id',
        'region_code', 'province_code', 'municipality_code', 'barangay_code',
        'application_status', 'rejection_reason', 'approved_at', 'approved_by',
        'is_active',
        'plate_number', 'license_expiry', 'license_path', 'or_cr_path', 'needs_reverification',
        'is_online', 'last_seen_at',
    ];

    protected $casts = [
        'approved_at'          => 'datetime',
        'is_active'            => 'boolean',
        'license_expiry'       => 'date',
        'needs_reverification' => 'boolean',
        'is_online'            => 'boolean',
        'last_seen_at'         => 'datetime',
    ];

    public function user(): BelongsTo          { return $this->belongsTo(User::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function area(): BelongsTo          { return $this->belongsTo(DeliveryArea::class, 'area_id'); }
    public function approvedBy(): BelongsTo    { return $this->belongsTo(User::class, 'approved_by'); }

    public function parcelDeliveries(): HasMany { return $this->hasMany(ParcelDelivery::class, 'rider_id'); }
    public function pickups(): HasMany          { return $this->hasMany(ParcelPickup::class, 'rider_id'); }
    public function offers(): HasMany           { return $this->hasMany(RiderJobOffer::class, 'rider_id'); }
    public function remittances(): HasMany      { return $this->hasMany(RiderRemittance::class, 'rider_id'); }

    public function isPending(): bool   { return $this->application_status === 'pending'; }
    public function isApproved(): bool  { return $this->application_status === 'approved'; }
    public function isRejected(): bool  { return $this->application_status === 'rejected'; }
    /** Approved, but turned off by the logistics company: can't go Online or take jobs. */
    public function isSuspended(): bool { return $this->isApproved() && ! $this->is_active; }

    /** Online and seen recently (a timed-out session counts as Offline). */
    public function isOnlineNow(): bool
    {
        return $this->is_online && $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_MINUTES));
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isPending()   => 'Pending',
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
