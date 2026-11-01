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

    public function test_buyer_list_search_and_status_filters_work(): void
    {
        $gel   = $this->buyer();                                                                  // approved
        $maria = $this->buyer(['name' => 'Maria Santos', 'email' => 'maria@test.com', 'username' => 'mariasan', 'contact_no' => '09111111111', 'approval_status' => 'pending']);
        $pedro = $this->buyer(['name' => 'Pedro Reyes', 'email' => 'pedro@test.com', 'contact_no' => '09222222222', 'approval_status' => 'rejected']);
        $ana   = $this->buyer(['name' => 'Ana Cruz', 'email' => 'ana@test.com', 'contact_no' => '09333333333', 'role' => 'suspended']);
        $seller = $this->buyer(['name' => 'Gel Seller', 'email' => 'gelshop@test.com', 'contact_no' => '09444444444', 'role' => 'seller']);

        $list = fn (array $q) => $this->actingAs($this->admin)->get(route('admin.customers.index', $q))->assertOk();
        $rows = fn ($res) => collect([$gel, $maria, $pedro, $ana, $seller])
            ->filter(fn ($u) => str_contains($res->getContent(), route('admin.customers.show', $u->id) . '"'))->pluck('name')->values()->all();

        $this->assertSame(['Gel Cuala', 'Maria Santos', 'Pedro Reyes', 'Ana Cruz'], $rows($list([])));     // suspended buyers stay listed; sellers don't
        $this->assertSame(['Gel Cuala'], $rows($list(['status' => 'approved'])));
        $this->assertSame(['Maria Santos'], $rows($list(['status' => 'pending'])));
        $this->assertSame(['Pedro Reyes'], $rows($list(['status' => 'rejected'])));
        $this->assertSame(['Ana Cruz'], $rows($list(['status' => 'suspended'])));

        $this->assertSame(['Maria Santos'], $rows($list(['search' => 'santos'])));                    // name
        $this->assertSame(['Maria Santos'], $rows($list(['search' => 'mariasan'])));                  // username
        $this->assertSame(['Pedro Reyes'], $rows($list(['search' => ' pedro@test '])));               // email, spaces trimmed
        $this->assertSame(['Gel Cuala'], $rows($list(['search' => 'gel', 'status' => 'approved'])));  // both together
        $this->assertSame([], $rows($list(['search' => 'maria', 'status' => 'approved'])));
        $this->assertSame([], $rows($list(['search' => '%'])));                                         // % is a plain character
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
