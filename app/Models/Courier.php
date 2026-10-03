<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Courier extends Model
{
    protected $fillable = [
        'user_id', 'last_name', 'first_name', 'middle_initial',
        'sex', 'contact_no', 'birthday', 'age',
        'province', 'municipality', 'barangay', 'street', 'house_number',
        'business_name',
        'vehicle_type', 'plate_number',
        'or_cr_path', 'id_license_path', 'dti_permit_path',
        'status', 'rejection_reason', 'total_earnings',
        'submitted_at', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'birthday'       => 'date',
        'submitted_at'   => 'datetime',
        'reviewed_at'    => 'datetime',
        'total_earnings' => 'decimal:2',
    ];

    public function user(): BelongsTo          { return $this->belongsTo(User::class); }
    public function deliveries(): HasMany       { return $this->hasMany(Delivery::class); }
    public function reviewedBy(): BelongsTo     { return $this->belongsTo(User::class, 'reviewed_by'); }

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

    /** Completed deliveries count */
    public function completedDeliveries(): int
    {
        return $this->deliveries()->where('status', 'delivered')->count();
    }
}
