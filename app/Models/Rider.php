<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rider extends Model
{
    protected $fillable = [
        'user_id', 'full_name', 'phone',
        'vehicle_type', 'license_number', 'id_document_path',
        'area_id',
        'application_status', 'rejection_reason', 'approved_at', 'approved_by',
        'is_active',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'is_active'   => 'boolean',
    ];

    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function area(): BelongsTo        { return $this->belongsTo(DeliveryArea::class, 'area_id'); }
    public function approvedBy(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }

    public function parcelDeliveries(): HasMany
    {
        return $this->hasMany(ParcelDelivery::class, 'rider_id');
    }

    public function isPending(): bool  { return $this->application_status === 'pending'; }
    public function isApproved(): bool { return $this->application_status === 'approved'; }
    public function isRejected(): bool { return $this->application_status === 'rejected'; }
}
