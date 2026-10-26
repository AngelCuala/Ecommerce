<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin → Users: Deactivate and Activate (reactivate) an account. */
class AdminDeactivateReactivateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin', 'admin@alvy.test');
    }

    private function user(string $role, string $email): User
    {
        return User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function login(User $u)
    {
        return $this->post(route('login'), ['email' => $u->email, 'password' => 'Secret@123']);
    }

    public function test_deactivate_then_reactivate_keeps_the_account_role(): void
    {
        foreach (['buyer', 'seller', 'courier', 'sorting_center'] as $role) {
            $u = $this->user($role, $role . '@alvy.test');

            // Deactivate: saved, and login is refused.
            $this->actingAs($this->admin)->from(route('admin.users.show', $u->id))
                ->patch(route('admin.users.deactivate', $u->id))
                ->assertRedirect(route('admin.users.show', $u->id))->assertSessionHas('success');
            $this->assertSame('deactivated', $u->fresh()->role, "$role was not deactivated");
            auth()->logout();
            $this->login($u)->assertSessionHasErrors(['email' => 'Your account has been deactivated. Please contact support.']);
            $this->assertGuest();

            $this->actingAs($this->admin)->get(route('admin.users.show', $u->id))->assertOk()->assertSee('Activate Account');

            // Reactivate: back to the same kind of account, and login works again.
            $this->actingAs($this->admin)->patch(route('admin.users.activate', $u->id))->assertSessionHas('success');
            $this->assertSame($role, $u->fresh()->role, "$role came back as " . $u->fresh()->role);
            auth()->logout();
            $this->login($u)->assertSessionHasNoErrors();
            $this->assertAuthenticatedAs($u->fresh());
            auth()->logout();
        }
    }

    public function test_reactivating_an_account_deactivated_before_this_fix_still_works(): void
    {
        $old = $this->user('deactivated', 'old@alvy.test');   // no saved role to go back to
        $this->actingAs($this->admin)->patch(route('admin.users.activate', $old->id))->assertSessionHas('success');
        $this->assertSame('buyer', $old->fresh()->role);
    }

    public function test_deactivating_signs_the_user_out_of_their_devices(): void
    {
        config(['session.driver' => 'database']);   // sessions live in the database, as on the live site
        $buyer = $this->user('buyer', 'buyer@alvy.test');
        $buyer->forceFill(['remember_token' => 'old-token'])->save();
        \DB::table('sessions')->insert(['id' => 'buyer-phone', 'user_id' => $buyer->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $buyer->id))->assertSessionHas('success');
        $this->assertFalse(\DB::table('sessions')->where('id', 'buyer-phone')->exists());
        $this->assertNotSame('old-token', $buyer->fresh()->remember_token);
        // The role it had is kept in the activity log for reactivation.
        $this->assertDatabaseHas('activity_logs', ['action' => 'user_deactivate', 'subject_id' => $buyer->id, 'old_value' => 'buyer', 'new_value' => 'deactivated']);
    }

    public function test_activate_and_deactivate_refuse_accounts_already_in_that_state(): void
    {
        $seller = $this->user('seller', 'seller@alvy.test');
        $this->actingAs($this->admin)->patch(route('admin.users.activate', $seller->id))->assertSessionHas('error');
        $this->assertSame('seller', $seller->fresh()->role);   // an active seller is not turned into a buyer

        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $seller->id));
        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $seller->id))->assertSessionHas('error');
        $this->actingAs($this->admin)->patch(route('admin.users.activate', $seller->id));
        $this->assertSame('seller', $seller->fresh()->role);
    }

    public function test_only_admins_can_deactivate_or_reactivate_and_admins_are_protected(): void
    {
        $seller = $this->user('seller', 'seller@alvy.test');
        $buyer  = $this->user('buyer', 'buyer@alvy.test');

        $this->actingAs($buyer)->patch(route('admin.users.deactivate', $seller->id));
        $this->assertSame('seller', $seller->fresh()->role);
        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $this->admin->id))->assertSessionHas('error');
        $this->assertSame('admin', $this->admin->fresh()->role);
    }

    public function test_suspend_then_activate_keeps_the_account_role(): void
    {
        foreach (['seller', 'courier', 'sorting_center'] as $role) {
            $u = $this->user($role, $role . '@alvy.test');
            $this->actingAs($this->admin)->patch(route('admin.users.suspend', $u->id))->assertSessionHas('success');
            $this->assertSame('suspended', $u->fresh()->role);
            auth()->logout();
            $this->login($u)->assertSessionHasErrors(['email' => 'Your account has been suspended. Please contact support.']);

            $this->actingAs($this->admin)->patch(route('admin.users.activate', $u->id))->assertSessionHas('success');
            $this->assertSame($role, $u->fresh()->role, "$role came back as " . $u->fresh()->role);
            auth()->logout();
        }

        // Suspended, then deactivated: still goes back to seller.
        $s = $this->user('seller', 'both@alvy.test');
        $this->actingAs($this->admin)->patch(route('admin.users.suspend', $s->id));
        $this->actingAs($this->admin)->patch(route('admin.users.deactivate', $s->id));
        $this->actingAs($this->admin)->patch(route('admin.users.activate', $s->id));
        $this->assertSame('seller', $s->fresh()->role);
    }
}
