<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The site's portals. Each one keeps its own login (session cookie and "remember me" cookie), so
 * one browser can be signed in to the shop in one tab and to the Seller Centre, a sorting center,
 * the courier app or the admin panel in other tabs at the same time.
 *
 *   shop     everything not below (buyers)        cookie: <session.cookie>
 *   seller   /seller/...                          cookie: <session.cookie>_seller
 *   sc       /sc/..., /sorting-center/...         cookie: <session.cookie>_sc
 *   courier  /courier/...                         cookie: <session.cookie>_courier
 *   admin    /admin/..., /logistics/...           cookie: <session.cookie>_admin
 *
 * Two accounts of the same portal (e.g. two buyers) still share that portal's login.
 * Turned off with SESSION_PER_PORTAL=false (one login for the whole site, as before).
 */
final class Portal
{
    public const SHOP = 'shop';

    /** First path segment => portal. */
    public const PREFIXES = [
        'seller' => 'seller', 'sc' => 'sc', 'sorting-center' => 'sc', 'courier' => 'courier', 'admin' => 'admin', 'logistics' => 'admin',
    ];

    /** URL prefix of each portal's own routes (login, logout, hand-off, shared pages). */
    public const PATHS = ['shop' => '', 'seller' => 'seller', 'sc' => 'sc', 'courier' => 'courier', 'admin' => 'admin'];

    public const LABELS = ['shop' => 'ALVY', 'seller' => 'Seller Centre', 'sc' => 'Sorting Center', 'courier' => 'Courier', 'admin' => 'Admin'];

    private static ?string $current = null;

    public static function enabled(): bool
    {
        return (bool) config('session.per_portal', true);
    }

    public static function fromPath(string $path): string
    {
        $first = strtolower(explode('/', trim($path, '/'))[0] ?? '');
        return self::PREFIXES[$first] ?? self::SHOP;
    }

    /** Decided once per request (by StartPortalSession), from the URL. */
    public static function resolve(Request $request): string
    {
        return self::$current = self::enabled() ? self::fromPath($request->path()) : self::SHOP;
    }

    public static function current(): string
    {
        return self::$current ?? (self::enabled() && app()->bound('request') ? self::fromPath(request()->path()) : self::SHOP);
    }

    public static function forget(): void
    {
        self::$current = null;
    }

    public static function cookieName(string $portal): string
    {
        $base = (string) config('session.cookie');
        return $portal === self::SHOP ? $base : $base . '_' . $portal;
    }

    /** The portal an account works in. */
    public static function home(User $user): string
    {
        return match (true) {
            $user->isAdmin()         => 'admin',
            $user->isSortingCenter() => 'sc',
            $user->isCourier(), $user->isCourierPending() => 'courier',
            $user->isSeller()        => 'seller',
            default                  => self::SHOP,
        };
    }

    /** Admins may also open the Seller Centre and courier pages (as before); everyone else only their own portal. */
    public static function allows(User $user, string $portal): bool
    {
        return $portal === self::home($user) || ($user->isAdmin() && in_array($portal, ['seller', 'courier'], true));
    }

    /**
     * A route that exists in several portals (logout, messages, chat, proof of delivery): this
     * portal's copy ("seller.messages.inbox"), else the shop one ("messages.inbox").
     */
    public static function route(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        $portal = self::current();
        if (self::enabled() && $portal !== self::SHOP && Route::has($portal . '.' . $name)) {
            $name = $portal . '.' . $name;
        }
        return route($name, $parameters, $absolute);
    }

    /** A plain path in this portal's copy of the shared pages ("chat/thread" → "/seller/chat/thread"). */
    public static function url(string $path): string
    {
        $portal = self::current();
        $prefix = self::enabled() && in_array($portal, ['seller', 'courier', 'admin'], true) ? self::PATHS[$portal] . '/' : '';
        return url($prefix . ltrim($path, '/'));
    }

    public static function loginUrl(?string $portal = null): string
    {
        $portal = self::enabled() ? ($portal ?? self::current()) : self::SHOP;
        return match ($portal) {
            'sc'    => route('sc.login'),
            'admin' => route('admin.login'),
            'seller', 'courier' => route($portal . '.login'),
            default => route('login'),
        };
    }

    /** Where this portal's sign-in form posts. */
    public static function loginAction(): string
    {
        $portal = self::enabled() ? self::current() : self::SHOP;
        return in_array($portal, ['seller', 'courier', 'admin'], true) ? route($portal . '.login.attempt') : route('login.attempt');
    }

    public static function handoffUrl(string $portal, string $token): string
    {
        return route($portal === self::SHOP ? 'portal.handoff' : $portal . '.portal.handoff', $token);
    }
}
