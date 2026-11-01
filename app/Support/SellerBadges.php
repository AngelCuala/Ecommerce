<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserNotification;

/**
 * Numbers on the Seller Centre sidebar: how many unread notifications point into each section
 * (a new order or parcel update → Order Management, low stock → Inventory, …). Opening a section
 * marks its notifications as seen (App\Http\Middleware\MarkSellerSectionSeen).
 */
final class SellerBadges
{
    /** First path segment after /seller => sidebar item key. */
    public const SECTIONS = [
        ''                 => 'dashboard',
        'orders'           => 'orders',
        'books'            => 'products',
        'store-categories' => 'store-categories',
        'reviews'          => 'reviews',
        'vouchers'         => 'vouchers',
        'flash-sales'      => 'flash',
        'promotions'       => 'promotions',
        'reports'          => 'reports',
        'earnings'         => 'earnings',
        'commissions'      => 'commissions',
        'messages'         => 'messages',
        'store-settings'   => 'store',
        'account'          => 'account',
    ];

    /** Which sidebar item a link (absolute or relative) belongs to, or null. */
    public static function sectionOf(?string $link): ?string
    {
        $path = (string) parse_url((string) $link, PHP_URL_PATH);
        if (! preg_match('#^/seller(?:/([^/]+))?/?#', $path, $m)) {
            return null;
        }
        return self::SECTIONS[$m[1] ?? ''] ?? null;
    }

    /** @return array<string,int> sidebar key => unread notifications */
    public static function counts(User $user): array
    {
        $counts = [];
        foreach (self::unread($user) as $note) {
            if ($key = self::sectionOf($note->link)) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /** The seller opened this section: its notifications count as seen. */
    public static function markSeen(User $user, string $section): void
    {
        $ids = self::unread($user)->filter(fn ($n) => self::sectionOf($n->link) === $section)->pluck('id');
        if ($ids->isNotEmpty()) {
            UserNotification::whereIn('id', $ids)->update(['read_at' => now()]);
        }
    }

    private static function unread(User $user)
    {
        return UserNotification::where('user_id', $user->id)->whereNull('read_at')
            ->where('link', 'like', '%/seller%')->get(['id', 'link']);
    }
}
