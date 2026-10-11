<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerApplication extends Model
{
    /** Business types a seller can register as. */
    public const SELLER_TYPES = [
        'Individual',
        'Sole Proprietorship',
        'Partnership',
        'Corporation',
        'Cooperative',
    ];

    protected $fillable = [
        'user_id',
        'last_name', 'first_name', 'middle_initial', 'sex', 'birthday', 'age',
        'full_name', 'shop_name', 'business_name', 'line_of_business', 'seller_type', 'tin',
        'phone', 'address', 'region', 'province', 'municipality', 'barangay', 'street', 'house_number', 'zip_code',
        'municipality_code', 'province_code', // PSGC codes — the seller's official pickup/origin for routing
        'government_id_path', 'business_permit_path', 'dti_certificate_path', 'sec_certificate_path', 'bir_2303_path',
        'description', 'status', 'rejection_reason', 'id_check_status', 'id_check_note',
        'shop_name_changes_this_month', 'shop_name_last_changed_at',
    ];

    protected $casts = [
        'birthday'                    => 'date',
        'shop_name_last_changed_at'   => 'datetime',
        'shop_name_changes_this_month'=> 'integer',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function isUnverified(): bool { return $this->status === 'unverified'; }
    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    /**
     * How many shop name changes remain this calendar month.
     * Resets automatically when a new month begins.
     */
    public function shopNameChangesRemaining(): int
    {
        // If the last change was in a previous month, the counter resets
        if ($this->shop_name_last_changed_at &&
            ! $this->shop_name_last_changed_at->isSameMonth(now())) {
            return 3;
        }
        return max(0, 3 - ($this->shop_name_changes_this_month ?? 0));
    }

    public function canChangeShopName(): bool
    {
        return $this->shopNameChangesRemaining() > 0;
    }

    /**
     * Returns the date when the limit resets (first day of next month).
     */
    public function shopNameResetsAt(): \Carbon\Carbon
    {
        return now()->addMonthNoOverflow()->startOfMonth();
    }

    public function fullName(): string
    {
        if ($this->first_name) {
            $m = $this->middle_initial ? " {$this->middle_initial}." : '';
            return trim("{$this->first_name}{$m} {$this->last_name}");
        }
        return $this->full_name ?? '';
    }
}
