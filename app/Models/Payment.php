<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One online payment attempt for a checkout (all of its store orders). */
class Payment extends Model
{
    protected $fillable = [
        'user_id', 'checkout_group', 'method', 'provider', 'amount', 'status',
        'provider_ref', 'provider_payment_id', 'checkout_url', 'failure_reason', 'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** The checkout's orders this payment pays for. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'checkout_group', 'checkout_group');
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isPaid(): bool    { return $this->status === 'paid'; }

    public function methodLabel(): string
    {
        return config('payments.methods.' . $this->method, ucfirst($this->method));
    }
}
