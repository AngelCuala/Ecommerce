<?php

namespace Tests;

use App\Http\Middleware\EnforceLoginTimeLimit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests empty the database they run on. The live site caches its config (php artisan optimize),
     * so make sure a test never runs on the real MySQL database: phpunit.xml points the caches at
     * test-only files, and this stops everything if the database is anything but SQLite in memory.
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();   // runs before RefreshDatabase touches any database
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            fwrite(STDERR, "\nSTOPPED: tests must use the SQLite test database, not '" . config('database.default') . "'. Run: php artisan optimize:clear\n");
            exit(1);
        }
    }

    /** Private uploads (IDs, proof of delivery, …) go to the 'local' disk: tests write to a throw-away copy, never the real one. */
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

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
