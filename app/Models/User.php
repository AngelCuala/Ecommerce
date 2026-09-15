<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\UserAddress;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        // Core
        'name', 'email', 'password', 'role',

        // Extended registration fields
        'first_name', 'last_name', 'middle_initial', 'username',
        'sex', 'contact_no', 'birthday', 'age',
        'province', 'municipality', 'barangay', 'street', 'house_number',
        'valid_id_path', 'approval_status', 'rejection_reason',

        // Profile extras
        'phone', 'address', 'city', 'zip', 'country',
        'profile_photo_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthday'          => 'date',
            'password'          => 'hashed',
        ];
    }

    // ── Approval status helpers ───────────────────────────────
    public function isApproved(): bool  { return $this->approval_status === 'approved'; }
    public function isPending(): bool   { return $this->approval_status === 'pending'; }
    public function isRejected(): bool  { return $this->approval_status === 'rejected'; }

    /**
     * Virtual `status` attribute used by admin views.
     * Returns 'suspended' when the role is suspended,
     * otherwise returns the approval_status value.
     */
    public function getStatusAttribute(): string
    {
        if ($this->role === 'suspended') {
            return 'suspended';
        }
        return $this->approval_status ?? 'pending';
    }

    // ── Role helpers ──────────────────────────────────────────
    public function isAdmin(): bool       { return $this->role === 'admin'; }
    public function isSeller(): bool      { return $this->role === 'seller'; }
    public function isBuyer(): bool       { return in_array($this->role, ['buyer', null, '']); }
    public function isSuspended(): bool   { return $this->role === 'suspended'; }
    public function isDeactivated(): bool { return $this->role === 'deactivated'; }
    public function isActive(): bool      { return ! in_array($this->role, ['suspended', 'deactivated']); }
    public function isSortingCenter(): bool { return $this->role === 'sorting_center'; }

    // ── Full name accessor ────────────────────────────────────
    /** Returns first + middle + last if set, otherwise falls back to `name` */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name) {
            $middle = $this->middle_initial ? " {$this->middle_initial}." : '';
            return trim("{$this->first_name}{$middle} {$this->last_name}");
        }
        return $this->name ?? '';
    }

    // ── Relationships ─────────────────────────────────────────
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function sellerApplication(): HasOne
    {
        return $this->hasOne(SellerApplication::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'seller_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(\App\Models\UserAddress::class);
    }

    public function defaultAddress(): ?UserAddress
    {
        return $this->addresses()->where('is_default', true)->first()
            ?? $this->addresses()->oldest()->first();
    }

    public function violations(): HasMany
    {
        return $this->hasMany(\App\Models\SellerViolation::class, 'seller_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }
}
