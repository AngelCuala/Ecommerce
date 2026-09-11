<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerApplication extends Model
{
    protected $fillable = [
        'user_id',
        'last_name', 'first_name', 'middle_initial', 'sex', 'birthday', 'age',
        'full_name', 'shop_name', 'business_name', 'line_of_business',
        'phone', 'address', 'province', 'municipality', 'barangay', 'street', 'house_number',
        'government_id_path', 'business_permit_path',
        'description', 'status', 'rejection_reason',
    ];

    protected $casts = ['birthday' => 'date'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function fullName(): string
    {
        if ($this->first_name) {
            $m = $this->middle_initial ? " {$this->middle_initial}." : '';
            return trim("{$this->first_name}{$m} {$this->last_name}");
        }
        return $this->full_name ?? '';
    }
}
