<?php

namespace App\Auth;

use App\Support\Portal;
use Illuminate\Auth\SessionGuard;

/**
 * The session guard with a "remember me" cookie per portal, so remembering a seller in the
 * Seller Centre never signs that seller in to the shop (or the other way round).
 * The shop keeps Laravel's usual names, so existing logins and remember-me cookies keep working.
 */
class PortalSessionGuard extends SessionGuard
{
    public function getName()
    {
        return 'login_' . $this->name . '_' . sha1(SessionGuard::class);
    }

    public function getRecallerName()
    {
        $base = 'remember_' . $this->name . '_' . sha1(SessionGuard::class);
        $portal = Portal::enabled() ? Portal::current() : Portal::SHOP;

        return $portal === Portal::SHOP ? $base : $base . '_' . $portal;
    }
}
