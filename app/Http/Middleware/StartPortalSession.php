<?php

namespace App\Http\Middleware;

use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

/**
 * Laravel's StartSession, with one session cookie per portal (App\Support\Portal): the shop,
 * Seller Centre, sorting center, courier and admin pages each read and write their own cookie,
 * so different accounts can be signed in to different portals in the same browser.
 */
class StartPortalSession extends StartSession
{
    public function getSession(Request $request)
    {
        $name = Portal::cookieName(Portal::resolve($request));

        return tap($this->manager->driver(), function ($session) use ($request, $name) {
            $session->setName($name);
            $session->setId($request->cookies->get($name));
        });
    }
}
