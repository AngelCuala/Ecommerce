<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Admin › Buyers › buyer detail page, in each account state. */
class AdminBuyerPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'),
            'role' => 'admin', 'approval_status' => 'approved']);
    }

    private function buyer(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Gel Cuala', 'first_name' => 'Gel', 'last_name' => 'Cuala', 'email' => 'gel@test.com',
            'password' => Hash::make('x'), 'role' => 'buyer', 'approval_status' => 'approved',
            'contact_no' => '09123456789', 'municipality' => 'Puerto Galera', 'province' => 'Oriental Mindoro',
        ], $attrs));
    }

    public function test_approved_buyer_shows_details_and_suspend(): void
    {
        $b = $this->buyer(['birthday' => '2000-06-19']);
        $this->actingAs($this->admin)->get(route('admin.customers.show', $b->id))->assertOk()
            ->assertSee('Gel Cuala')->assertSee('Approved')->assertSee('Suspend account')
            ->assertSee('Puerto Galera, Oriental Mindoro')->assertSee('(age')
            ->assertDontSee('Buyer Application');
    }

    public function test_buyer_without_birthday_does_not_crash(): void
    {
        $b = $this->buyer(['birthday' => null]);
        $this->actingAs($this->admin)->get(route('admin.customers.show', $b->id))->assertOk();
    }

    public function test_pending_buyer_can_be_approved_or_rejected(): void
    {
        $b = $this->buyer(['approval_status' => 'pending']);
        $this->actingAs($this->admin)->get(route('admin.customers.show', $b->id))->assertOk()
            ->assertSee('Approve')->assertSee('Reject account')->assertSee('reason-other-input', false);

        $this->patch(route('admin.customers.reject', $b->id), ['rejection_reason' => 'invalid_id'])->assertSessionHas('success');
        $this->get(route('admin.customers.show', $b->id))->assertSee('Rejected: Invalid id');
    }

    public function test_suspended_buyer_can_be_restored(): void
    {
        $b = $this->buyer(['role' => 'suspended']);
        $this->actingAs($this->admin)->get(route('admin.customers.show', $b->id))->assertOk()->assertSee('Restore account');
    }
}
