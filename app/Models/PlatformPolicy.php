<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformPolicy extends Model
{
    protected $fillable = ['key', 'title', 'content', 'updated_by'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Upsert a policy by key */
    public static function upsertPolicy(string $key, string $title, string $content, int $adminId): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['title' => $title, 'content' => $content, 'updated_by' => $adminId]
        );
    }

    public static function defaultPolicies(): array
    {
        return [
            ['key' => 'terms_of_service',      'title' => 'Terms of Service'],
            ['key' => 'privacy_policy',         'title' => 'Privacy Policy'],
            ['key' => 'seller_policy',          'title' => 'Seller Policy'],
            ['key' => 'return_refund_policy',   'title' => 'Return & Refund Policy'],
            ['key' => 'prohibited_items',       'title' => 'Prohibited Items Policy'],
        ];
    }
}
