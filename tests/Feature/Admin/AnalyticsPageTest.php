<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin → Analytics & Reports: account tiles, sign-up tiles and the registrations chart. */
class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, $createdAt = null): User
    {
        $u = User::create(['name' => ucfirst($role), 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now()]);
        if ($createdAt) {
            $u->forceFill(['created_at' => $createdAt])->saveQuietly();
        }
        return $u;
    }

    public function test_tiles_chart_tabs_and_table(): void
    {
        $admin = $this->user('admin', 'admin@alvy.test');
        $this->user('buyer', 'b1@alvy.test');
        $this->user('buyer', 'b2@alvy.test', now()->subDays(3));
        $this->user('seller', 's1@alvy.test', now()->subDays(3));

        $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk()
            ->assertSee('data-tile="total-accounts">4', false)
            ->assertSee('data-tile="buyers">2', false)
            ->assertSee('50%')                                   // buyers: 2 of 4
            ->assertSee('New registrations')
            ->assertSee('data-tab="daily"', false)->assertSee('data-tab="weekly"', false)->assertSee('data-tab="monthly"', false)
            ->assertSee('data-range-total>4', false)              // all four in the last 14 days
            ->assertSee('Show as table')
            ->assertSee(now()->subDays(3)->format('M j'));
    }

    public function test_only_admins_can_open_it(): void
    {
        $buyer = $this->user('buyer', 'b@alvy.test');
        $this->assertNotSame(200, $this->actingAs($buyer)->get(route('admin.analytics.index'))->status());
    }
}
