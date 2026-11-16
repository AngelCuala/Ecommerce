<?php

namespace App\Support;

use App\Models\User;

/**
 * The account menu (left of every account page, <x-account-shell>) for each portal, the same on its
 * My Account and Accounts & Security pages. $active is the key of the page being shown. Pages already in the
 * portal's sidebar (Store Settings, Earnings, Notifications, Riders…) are not repeated here.
 */
class AccountNav
{
    private static function items(array $items, string $active): array
    {
        return array_map(fn ($key, $item) => $item + ['active' => $key === $active], array_keys($items), $items);
    }

    public static function seller(string $active): array
    {
        return self::items([
            'account'       => ['label' => 'My Account', 'href' => route('seller.account'), 'icon' => 'user'],
            'security'      => ['label' => 'Accounts & Security', 'href' => route('seller.account.security'), 'icon' => 'lock'],
        ], $active);
    }

    public static function sortingCenter(string $active): array
    {
        return self::items([
            'account'  => ['label' => 'My Account', 'href' => route('sc.account'), 'icon' => 'user'],
            'security' => ['label' => 'Accounts & Security', 'href' => route('sc.account.security'), 'icon' => 'lock'],
        ], $active);
    }

    /** Riders (and the few direct couriers): the work pages differ for a 4-wheel rider (hub-to-hub trips). */
    public static function rider(string $active, bool $truck = false): array
    {
        return self::items([
            'account'       => ['label' => 'My Account', 'href' => route('courier.account'), 'icon' => 'user'],
            'vehicle'       => ['label' => 'Vehicle & License', 'href' => route('courier.account') . '#rider-profile', 'icon' => 'truck'],
            'security'      => ['label' => 'Accounts & Security', 'href' => route('courier.account.security'), 'icon' => 'lock'],
        ], $active);
    }
}
