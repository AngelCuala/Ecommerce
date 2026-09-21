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
            ['key' => 'about_us',               'title' => 'About Us'],
            ['key' => 'terms_of_service',      'title' => 'Terms of Service'],
            ['key' => 'privacy_policy',         'title' => 'Privacy Policy'],
            ['key' => 'seller_policy',          'title' => 'Seller Policy'],
            ['key' => 'return_refund_policy',   'title' => 'Return & Refund Policy'],
            ['key' => 'prohibited_items',       'title' => 'Prohibited Items Policy'],
        ];
    }

    /** Default body content shown when a policy hasn't been published in the DB yet. */
    public static function defaultContent(string $key): ?string
    {
        return match ($key) {
            'about_us' => '<h2 style="font-size:1.15rem;font-weight:800;color:#002b4d;margin-bottom:.5rem;">Developers Profile</h2>'
                . '<p>The ALVY development team consists of dedicated and aspiring Information Technology students who are responsible for designing, developing, and maintaining the platform. The developers apply their knowledge of programming, database management, web development, and user interface design to create a functional and user-friendly e-commerce system. ALVY also recognizes the important role of couriers and sorting centers in ensuring that orders are properly processed, sorted, and delivered to customers. Through teamwork, creativity, and continuous learning, the development team aims to create a reliable platform that connects consumers, sellers, sorting centers, and couriers while providing a smooth and convenient shopping experience.</p>'
                . '<h2 style="font-size:1.15rem;font-weight:800;color:#002b4d;margin-top:1.5rem;margin-bottom:.5rem;">Mission</h2>'
                . '<p>ALVY\'s mission is to become an accessible, convenient, and trustworthy shopping platform for everyone, where consumers can discover products and complete their transactions safely and easily.</p>'
                . '<h2 style="font-size:1.15rem;font-weight:800;color:#002b4d;margin-top:1.5rem;margin-bottom:.5rem;">Vision</h2>'
                . '<p>ALVY strives to become an e-commerce platform where everyone can easily find what they need and enjoy a safe, convenient, and satisfying online shopping experience.</p>',
            default => null,
        };
    }
}
