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

    /**
     * A rider with a 4-wheel vehicle is the company's Linehaul courier: keep their courier record (same account)
     * in step with the rider record, with the company's hub as home hub. Returns null when the rider isn't one.
     */
    public static function syncFromRider(Rider $r): ?self
    {
        if (! $r->user_id || ! $r->sorting_center_id || ! $r->isApproved() || ! $r->isFourWheel()) {
            return null;
        }
        $hubId = LogisticsHub::where('sorting_center_id', $r->sorting_center_id)->orderBy('id')->value('id');
        if (! $hubId) {
            return null;   // the company has no hub yet
        }
        $c = self::firstOrNew(['user_id' => $r->user_id]);
        $words = preg_split('/\s+/', trim((string) $r->full_name)) ?: [];
        $last = count($words) > 1 ? array_pop($words) : '';
        $c->fill(array_filter([
            'first_name'      => $c->first_name ?: implode(' ', $words) ?: $r->full_name,
            'last_name'       => $c->last_name ?: $last,
            'contact_no'      => $c->contact_no ?: $r->phone,
            'plate_number'    => $c->plate_number ?: $r->plate_number,
            'license_number'  => $c->license_number ?: $r->license_number,
            'license_expiry'  => $c->license_expiry ?: $r->license_expiry,
            'or_cr_path'      => $c->or_cr_path ?: $r->or_cr_path,
            'id_license_path' => $c->id_license_path ?: ($r->license_path ?: $r->id_document_path),
        ], fn ($v) => $v !== null && $v !== ''));
        $c->sorting_center_id = $r->sorting_center_id;
        $c->vehicle_type = $r->vehicle_type;
        if ($c->sorting_center_id != $c->getOriginal('sorting_center_id') || ! $c->home_hub_id) {
            $c->home_hub_id = $hubId;
        }
        // The company approved the rider. A suspended rider is a suspended courier; a courier suspended in Linehaul stays so.
        $c->status = ! $r->is_active || $c->status === 'suspended' ? 'suspended' : 'approved';
        $c->last_name = (string) $c->last_name;
        $c->save();

        return $c;
    }

    /** Make sure every 4-wheel rider of the company has their courier record. */
    public static function syncCompany(int $scId): void
    {
        Rider::where('sorting_center_id', $scId)->where('application_status', 'approved')->get()
            ->filter(fn (Rider $r) => $r->isFourWheel())
            ->each(fn (Rider $r) => self::syncFromRider($r));
    }
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
