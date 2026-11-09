<?php

namespace App\Support;

use App\Models\User;

/**
 * The account menu (left of every account page, <x-account-shell>) for each portal, the same on its
 * My Account and Accounts & Security pages. $active is the key of the page being shown.
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
            'store'         => ['label' => 'Store Settings', 'href' => route('seller.store-settings'), 'icon' => 'store'],
            'earnings'      => ['label' => 'Earnings & Payouts', 'href' => route('seller.earnings'), 'icon' => 'pay'],
            'notifications' => ['label' => 'Notifications', 'href' => route('seller.notifications'), 'icon' => 'bell'],
            'security'      => ['label' => 'Accounts & Security', 'href' => route('seller.account.security'), 'icon' => 'lock'],
        ], $active);
    }

    public static function sortingCenter(string $active): array
    {
        return self::items([
            'account'  => ['label' => 'My Account', 'href' => route('sc.account'), 'icon' => 'user'],
            'areas'    => ['label' => 'Coverage Areas', 'href' => route('sc.areas'), 'icon' => 'pin'],
            'riders'   => ['label' => 'Riders', 'href' => route('sc.riders'), 'icon' => 'users'],
            'earnings' => ['label' => 'Commission & Earnings', 'href' => route('sc.commissions'), 'icon' => 'pay'],
            'security' => ['label' => 'Accounts & Security', 'href' => route('sc.account.security'), 'icon' => 'lock'],
        ], $active);
    }

    /** Riders (and the few direct couriers): the work pages differ for a 4-wheel rider (hub-to-hub trips). */
    public static function rider(string $active, bool $truck = false): array
    {
        return self::items([
            'account'       => ['label' => 'My Account', 'href' => route('courier.account'), 'icon' => 'user'],
            'vehicle'       => ['label' => 'Vehicle & License', 'href' => route('courier.account') . '#rider-profile', 'icon' => 'truck'],
            'pay'           => ['label' => $truck ? 'Rider Pay' : 'Pay & COD', 'href' => $truck ? route('courier.trips.pay') : route('courier.rider.earnings'), 'icon' => 'pay'],
            'notifications' => ['label' => 'Notifications', 'href' => route('courier.notifications'), 'icon' => 'bell'],
            'security'      => ['label' => 'Accounts & Security', 'href' => route('courier.account.security'), 'icon' => 'lock'],
        ], $active);
    }
}
