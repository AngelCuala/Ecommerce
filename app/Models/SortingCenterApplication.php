<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SortingCenterApplication extends Model
{
    protected $fillable = [
        'user_id',
        'last_name', 'first_name', 'middle_initial', 'sex', 'birthday', 'age', 'contact_no',
        'business_name',
        'region', 'region_code', 'province', 'province_code', 'municipality', 'municipality_code',
        'barangay', 'barangay_code', 'street', 'house_number', 'zip_code', 'address',
        'government_id_path', 'business_permit_path',
        'status', 'rejection_reason', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'birthday'    => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . ($this->middle_initial ? rtrim($this->middle_initial, '.') . '. ' : '') . $this->last_name);
    }
}
