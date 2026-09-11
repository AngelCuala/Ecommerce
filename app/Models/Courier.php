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
    ];

    protected $casts = ['birthday' => 'date'];

    public function user(): BelongsTo          { return $this->belongsTo(User::class); }
    public function deliveries(): HasMany       { return $this->hasMany(Delivery::class); }

    public function fullName(): string          { return "{$this->first_name} {$this->middle_initial} {$this->last_name}"; }
    public function isPending(): bool           { return $this->status === 'pending'; }
    public function isApproved(): bool          { return $this->status === 'approved'; }
    public function isRejected(): bool          { return $this->status === 'rejected'; }

    /** Completed deliveries count */
    public function completedDeliveries(): int
    {
        return $this->deliveries()->where('status', 'delivered')->count();
    }
}
