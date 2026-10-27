<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    protected $fillable = [
        'user_id', 'sorting_center_id', 'last_name', 'first_name', 'middle_initial',
        'sex', 'contact_no', 'birthday', 'age',
        'province', 'municipality', 'barangay', 'street', 'house_number',
        'business_name',
        'vehicle_type', 'plate_number',
        'license_number', 'license_type', 'dl_codes', 'license_expiry', 'load_capacity_kg', 'home_hub_id',
        'or_cr_path', 'id_license_path', 'dti_permit_path', 'nbi_path',
        'status', 'rejection_reason', 'total_earnings',
        'on_duty', 'duty_since', 'checklist_at', 'last_lat', 'last_lng', 'last_seen_at', 'needs_reverification',
        'submitted_at', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'birthday'       => 'date',
        'license_expiry' => 'date',
        'on_duty'        => 'boolean',
        'duty_since'     => 'datetime',
        'checklist_at'   => 'datetime',
        'last_seen_at'   => 'datetime',
        'needs_reverification' => 'boolean',
        'submitted_at'   => 'datetime',
        'reviewed_at'    => 'datetime',
        'total_earnings' => 'decimal:2',
    ];

    public function user(): BelongsTo          { return $this->belongsTo(User::class); }
    public function deliveries(): HasMany       { return $this->hasMany(Delivery::class); }
    public function reviewedBy(): BelongsTo     { return $this->belongsTo(User::class, 'reviewed_by'); }
    /** The sorting center (hub) the courier applied to; it reviews the application. */
    public function sortingCenter(): BelongsTo  { return $this->belongsTo(User::class, 'sorting_center_id'); }
    /** The hub a company-made courier drives from (Add Courier). */
    public function homeHub(): BelongsTo        { return $this->belongsTo(LogisticsHub::class, 'home_hub_id'); }
    public function trips(): HasMany            { return $this->hasMany(LinehaulTrip::class); }

    /** Seen by the app within this many minutes: older means Offline (closed app, no signal). */
    public const ONLINE_MINUTES = 5;

    /** On Duty and the app was seen recently (a closed app counts as Offline after 5 minutes). */
    public function isOnDutyNow(): bool
    {
        return $this->on_duty && $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_MINUTES));
    }

    /** The courier's trip that isn't finished yet (at most one). */
    public function activeTrip(): ?LinehaulTrip
    {
        return $this->trips()->whereIn('status', LinehaulTrip::ACTIVE)->latest('id')->first();
    }

    public function dutyLabel(): string
    {
        return match (true) {
            ! $this->on_duty     => 'Off Duty',
            $this->isOnDutyNow() => 'On Duty',
            default              => 'On Duty · Offline',
        };
    }

    private ?string $hubNameCache = null;

    /** Name of the sorting center reviewing this application, for messages to the courier. */
    public function hubName(): string
    {
        if ($this->hubNameCache !== null) {
            return $this->hubNameCache;   // looked up once per courier; pages call this several times
        }

        $hub = $this->sortingCenter;

        return $this->hubNameCache = $hub
            ? (SortingCenterApplication::where('user_id', $hub->id)->where('status', 'approved')->value('business_name') ?: $hub->name)
            : 'the Logistics team';
    }

    public function fullName(): string
    {
        $mi = $this->middle_initial ? " {$this->middle_initial}." : '';
        return trim("{$this->first_name}{$mi} {$this->last_name}");
    }

    public function fullAddress(): string
    {
        return implode(', ', array_filter([
            $this->house_number, $this->street, $this->barangay,
            $this->municipality, $this->province,
        ])) ?: '—';
    }

    public function isPending(): bool           { return $this->status === 'pending'; }
    public function isApproved(): bool          { return $this->status === 'approved'; }
    public function isRejected(): bool          { return $this->status === 'rejected'; }
    public function isSuspended(): bool         { return $this->status === 'suspended'; }
    /** Made by a logistics company; waits for the courier to set a password from the emailed link. */
    public function isPendingActivation(): bool { return $this->status === 'pending_activation'; }
    /** A linehaul courier made by a logistics company (Add Courier), not a self-registered one. */
    public function isLinehaul(): bool          { return $this->home_hub_id !== null; }

    /** Completed deliveries count */
    public function completedDeliveries(): int
    {
        return $this->deliveries()->where('status', 'delivered')->count();
    }
}
