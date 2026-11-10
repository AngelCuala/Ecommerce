<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Parcel extends Model
{
    protected $fillable = [
        'tracking_number', 'waybill_number', 'order_id',
        'seller_id', 'pickup_address', 'dropoff_address',
        'destination_municipality', 'destination_province',
        'receiver_name', 'receiver_phone',
        'weight_kg', 'size', 'notes', 'pickup_scheduled_at',
        'area_id', 'status', 'transfer_status',
        'current_sorting_center_id', 'current_hub_id',
        'verified_by', 'verified_at', 'received_at', 'sorted_at', 'failure_reason',
        'rts_rider_id', 'rts_assigned_at', 'returned_at', 'returned_received_at', 'exception_photo_path',
        'received_by', 'sorted_by', 'sorting_started_at',
    ];

    protected $casts = [
        'unassigned_at'       => 'datetime',
        'verified_at'         => 'datetime',
        'received_at'         => 'datetime',
        'sorted_at'           => 'datetime',
        'sorting_started_at'  => 'datetime',
        'rts_assigned_at'     => 'datetime',
        'returned_at'         => 'datetime',
        'returned_received_at' => 'datetime',
        'pickup_scheduled_at' => 'datetime',
        'weight_kg'           => 'decimal:2',
    ];

    /** Human labels for each pipeline status (sorting-center wording). */
    public const STATUS_LABELS = [
        'pending_pickup'  => 'Pickup Requested',
        'pickup_approved' => 'Pickup Confirmed',
        'in_transit_to_hub' => 'Picked Up by Rider',
        'pickup_rejected' => 'Pickup Rejected',
        'picked_up'       => 'At Sorting Center',
        'sorting'         => 'Sorting in Progress',
        'sorted'          => 'Sorted',
        'in_transfer'     => 'In Transit to Sorting Center',
        'assigned'        => 'Assigned to Rider',
        'in_transit'      => 'Out for Delivery',
        'linehaul'        => 'In Transit between hubs',
        'missing_in_transit' => 'Missing in Transit',
        'delivered'       => 'Delivered',
        'failed'          => 'Delivery Failed',
        'return_to_sender' => 'Return to Sender',
        'returned'        => 'Returned to Seller',
        'cancelled'       => 'Cancelled',
        'lost'            => 'Lost',
        'damaged'         => 'Damaged',
    ];

    /** Statuses after which the sorting-center workflow is finished (no longer an active route). */
    public const CLOSED_STATUSES = ['delivered', 'returned', 'cancelled', 'lost', 'damaged'];

    /** Dashboard / shipment-list groups (L1): every status is in exactly one. */
    public const BUCKETS = [
        'pending'    => ['pending_pickup', 'pickup_approved'],
        'in_transit' => ['in_transit_to_hub', 'picked_up', 'sorting', 'sorted', 'in_transfer', 'assigned', 'in_transit', 'linehaul'],
        'delivered'  => ['delivered'],
        'failed'     => ['failed', 'return_to_sender', 'lost', 'damaged', 'missing_in_transit'],
        'returned'   => ['returned', 'pickup_rejected', 'cancelled'],
    ];

    /**
     * The only status changes allowed (one place for the whole shipment lifecycle). Any other change
     * is refused by the model before it is saved, with the shipment left as it was.
     */
    public const TRANSITIONS = [
        'pending_pickup'    => ['pickup_approved', 'pickup_rejected', 'cancelled', 'picked_up'],
        'pickup_rejected'   => ['pending_pickup', 'pickup_approved', 'cancelled'],
        'cancelled'         => ['pending_pickup', 'pickup_approved'],
        'pickup_approved'   => ['pending_pickup', 'in_transit_to_hub', 'picked_up', 'pickup_rejected', 'cancelled', 'lost', 'damaged'],
        'in_transit_to_hub' => ['picked_up', 'cancelled', 'lost', 'damaged'],
        'picked_up'         => ['sorting', 'sorted', 'in_transfer', 'linehaul', 'return_to_sender', 'lost', 'damaged'],
        'sorting'           => ['sorted', 'in_transfer', 'linehaul', 'return_to_sender', 'lost', 'damaged'],
        'sorted'            => ['assigned', 'in_transfer', 'linehaul', 'return_to_sender', 'lost', 'damaged'],
        // A hub-to-hub trip (App\Services\Linehaul): scanned in at the destination hub, damaged, or not found there.
        'linehaul'          => ['picked_up', 'damaged', 'missing_in_transit', 'lost'],
        'missing_in_transit' => ['picked_up', 'lost'],
        'in_transfer'       => ['picked_up', 'lost', 'damaged'],
        'assigned'          => ['in_transit', 'sorted', 'failed', 'return_to_sender', 'lost', 'damaged'],
        'in_transit'        => ['delivered', 'failed', 'return_to_sender', 'cancelled', 'lost', 'damaged'],
        // 'cancelled': could not be delivered (3rd failed attempt or refused): the order is cancelled and
        // the parcel stays at the hub for the seller to collect (Return to Sender is no longer used).
        'failed'            => ['sorted', 'assigned', 'return_to_sender', 'cancelled', 'lost', 'damaged'],
        'return_to_sender'  => ['returned', 'lost', 'damaged'],
        'delivered'         => [],
        'returned'          => [],
        'lost'              => [],
        'damaged'           => [],
    ];

    public static function canMove(?string $from, string $to): bool
    {
        return $from === null || $from === $to || in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** Extra details for the tracking event of the next status change (set by the action, read once). */
    public array $tracking = [];

    public function withTracking(array $context): static
    {
        $this->tracking = $context;
        return $this;
    }

    public function rtsRider(): BelongsTo { return $this->belongsTo(Rider::class, 'rts_rider_id'); }
    public function currentHub(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'current_hub_id'); }

    /** Set while a linehaul trip moves the parcel: the trip writes the timeline line itself. */
    public bool $linehaulStep = false;

    /** Shipment steps on the buyer's order timeline (rider assignment is written by the dispatcher). */
    protected static function booted(): void
    {
        // Every parcel gets its own waybill number when it is created.
        static::creating(function (Parcel $parcel) {
            $parcel->waybill_number ??= static::generateWaybill();
        });

        // Central status rules: an impossible jump (e.g. sorted → delivered) is refused, nothing is saved.
        static::updating(function (Parcel $parcel) {
            if ($parcel->isDirty('status') && ! static::canMove($parcel->getOriginal('status'), $parcel->status)) {
                throw new \App\Exceptions\InvalidShipmentTransition($parcel, (string) $parcel->getOriginal('status'), (string) $parcel->status);
            }
        });

        // Every status change becomes a structured, append-only tracking event (with notifications).
        static::created(function (Parcel $parcel) {
            app(\App\Services\ShipmentTracker::class)->statusChanged($parcel, null, $parcel->status);
        });

        static::updated(function (Parcel $parcel) {
            if ($parcel->wasChanged('status')) {
                app(\App\Services\ShipmentTracker::class)->statusChanged($parcel, $parcel->getOriginal('status'), $parcel->status);
            }
            // Linehaul steps write their own line ("In Transit (HUB-QC → HUB-PSG)", "At Hub (HUB-PSG)").
            if ($parcel->wasChanged('status') && $parcel->order_id && $parcel->status !== 'assigned' && ! $parcel->linehaulStep) {
                $label = $parcel->status === 'picked_up' && $parcel->currentHub ? "At Hub ({$parcel->currentHub->code})" : $parcel->statusLabel();
                $parcel->timeline($label . ($parcel->status === 'picked_up' && $parcel->received_at ? ' — received ' . $parcel->received_at->format('M j, g:i A') : ''));
            }
        });
    }

    /** Add a shipment step to the order timeline. */
    public function timeline(string $note): void
    {
        OrderStatusEvent::create(['order_id' => $this->order_id, 'status' => 'Shipment', 'note' => $this->tracking_number . ': ' . $note, 'created_at' => now()]);
    }

    public function order(): BelongsTo       { return $this->belongsTo(Order::class); }

    /**
     * Google Maps directions to the buyer: to the map pin they dropped on their address, or a search for
     * the written address when there is no pin. Opens the Maps app on a phone.
     */
    public function mapsUrl(): string
    {
        $addr = $this->order?->deliveryAddress;
        if ($addr && $addr->latitude && $addr->longitude) {
            return 'https://www.google.com/maps/dir/?api=1&destination=' . round($addr->latitude, 7) . ',' . round($addr->longitude, 7);
        }

        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode((string) $this->dropoff_address);
    }

    /** The buyer pinned their house on the map (so the directions are exact). */
    public function hasMapPin(): bool
    {
        $addr = $this->order?->deliveryAddress;
        return (bool) ($addr && $addr->latitude && $addr->longitude);
    }
    public function seller(): BelongsTo      { return $this->belongsTo(User::class, 'seller_id'); }
    public function area(): BelongsTo        { return $this->belongsTo(DeliveryArea::class, 'area_id'); }
    public function verifiedBy(): BelongsTo  { return $this->belongsTo(User::class, 'verified_by'); }
    public function currentSortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'current_sorting_center_id'); }

    /** Latest delivery assignment (a parcel can be re-assigned after a failed attempt). */
    public function parcelDelivery(): HasOne
    {
        return $this->hasOne(ParcelDelivery::class, 'parcel_id')->latestOfMany();
    }

    /** Every delivery attempt for this parcel. */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ParcelDelivery::class, 'parcel_id');
    }

    /** First-mile rider trips (seller → sorting center). */
    public function pickups(): HasMany
    {
        return $this->hasMany(ParcelPickup::class, 'parcel_id');
    }

    public function pickup(): HasOne
    {
        return $this->hasOne(ParcelPickup::class, 'parcel_id')->latestOfMany();
    }

    public function jobOffers(): HasMany
    {
        return $this->hasMany(RiderJobOffer::class, 'parcel_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ParcelTransfer::class, 'parcel_id');
    }

    /** The transfer in progress (awaiting dispatch, in transit or under exception), if any. */
    public function activeTransfer(): HasOne
    {
        return $this->hasOne(ParcelTransfer::class, 'parcel_id')->whereIn('status', ParcelTransfer::ACTIVE)->latestOfMany();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class, 'parcel_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class, 'parcel_id');
    }

    public function receivedBy(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
    public function sortedBy(): BelongsTo   { return $this->belongsTo(User::class, 'sorted_by'); }

    /** Physically at a sorting center now (received there, not on a rider or between hubs). */
    public function isAtHub(): bool
    {
        return in_array($this->status, ['picked_up', 'sorting', 'sorted', 'failed'], true) && $this->current_sorting_center_id !== null;
    }

    public function pendingTransfer(): HasOne
    {
        return $this->hasOne(ParcelTransfer::class, 'parcel_id')->where('status', 'pending')->latestOfMany();
    }

    public function isBeingTransferred(): bool
    {
        return $this->transfer_status === 'outgoing';
    }

    public function isIncomingTransfer(): bool
    {
        return $this->transfer_status === 'incoming';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', $this->status));
    }

    /** Generate a unique tracking number */
    /**
     * Waybill number, Shopee-style: "PH" + date (YYMMDD) + 8 random digits + 1 check digit,
     * e.g. PH261019482915736. The check digit (Luhn) catches most mistyped numbers.
     */
    public static function generateWaybill(?\Carbon\CarbonInterface $date = null): string
    {
        do {
            $digits = ($date ?? now())->format('ymd') . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $number = 'PH' . $digits . static::luhnDigit($digits);
        } while (static::where('waybill_number', $number)->exists());

        return $number;
    }

    /** True when a typed waybill number has the right shape and check digit. */
    public static function isValidWaybill(string $number): bool
    {
        return (bool) preg_match('/^PH(\d{14})(\d)$/', strtoupper(trim($number)), $m)
            && (string) static::luhnDigit($m[1]) === $m[2];
    }

    private static function luhnDigit(string $digits): int
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $d) {
            $n = (int) $d * ($i % 2 === 0 ? 2 : 1);
            $sum += $n > 9 ? $n - 9 : $n;
        }
        return (10 - $sum % 10) % 10;
    }

    /**
     * Sorting code printed big on the label (like Shopee's route code): destination province,
     * city and barangay, e.g. "LAG-CAL-REA". The sorter reads it to drop the parcel in the right bin.
     */
    public function sortCode(): string
    {
        $abbr = function (?string $name, int $len = 3) {
            // An official short form in brackets wins: "National Capital Region (NCR)" → NCR.
            if (preg_match('/\(([A-Z]{2,6})\)/', (string) $name, $m)) {
                return $m[1];
            }
            $clean = strtoupper(preg_replace('/^(city of|municipality of|barangay)\s+/i', '', trim((string) $name)));
            $clean = preg_replace('/[^A-Z0-9 ]/', '', $clean);
            return $clean === '' ? null : substr(str_replace(' ', '', $clean), 0, $len);
        };

        return implode('-', array_filter([
            $abbr($this->destination_province) ?? 'PH',
            $abbr($this->destination_municipality) ?? '---',
            $this->area ? $abbr($this->area->name) : null,
        ]));
    }

    public static function generateTracking(): string
    {
        do {
            $number = 'ALVY-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));
        } while (static::where('tracking_number', $number)->exists());

        return $number;
    }
}
