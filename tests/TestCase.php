<?php

namespace Tests;

use App\Http\Middleware\EnforceLoginTimeLimit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * actingAs() means "this user has just signed in": its login-time clock starts now, so tests
     * that travel in time don't get signed out by the idle / 8-hour limit (LoginTimeLimitTest
     * covers those limits with real logins).
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);
        $this->withSession([EnforceLoginTimeLimit::STARTED => now()->getTimestamp(), EnforceLoginTimeLimit::SEEN => now()->getTimestamp()]);

        return $this;
    }
}
