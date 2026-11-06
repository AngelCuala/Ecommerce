<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Shorter menus: related pages share one button and show tabs, so every page is still one tap away. */
class ShorterSidebarsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_admin_menu_groups_reports_accounts_and_approvals(): void
    {
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'), 'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Accounts')->assertSee('Approvals')->assertDontSee('All User Accounts')->assertDontSee('Analytics &amp; Reports', false);
        $this->actingAs($admin)->get(route('admin.sellers.index'))->assertOk()
            ->assertSee('data-page-tabs', false)->assertSee(route('admin.users.index'), false)->assertSee(route('admin.riders.index'), false);
        $this->actingAs($admin)->get(route('admin.analytics.index'))->assertOk()->assertSee(route('admin.reports.index'), false);
    }

    public function test_seller_menu_groups_marketing_products_and_reports(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('Vouchers &amp; Promos', false)->assertDontSee('Bundles &amp; Promos', false)->assertDontSee('Store Categories');
        $this->actingAs($this->seller1)->get(route('seller.flash-sales.index'))->assertOk()
            ->assertSee('data-page-tabs', false)->assertSee(route('seller.vouchers.index'), false)->assertSee(route('seller.promotions.index'), false);
        $this->actingAs($this->seller1)->get(route('seller.commissions'))->assertOk()->assertSee(route('seller.reports'), false);
    }

    public function test_logistics_menu_groups_deliveries_riders_and_reports(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertOk()
            ->assertSee('data-page-tabs', false)->assertSee(route('sc.delivery-assignment'), false)->assertSee(route('sc.delivery-monitoring'), false)
            ->assertSee('Deliveries');   // the one menu button for all three
        $this->actingAs($this->logistics1)->get(route('sc.activity'))->assertOk()->assertSee(route('sc.reports'), false);
    }

    public function test_a_hub_rider_sees_pay_and_cod_together_and_no_courier_pages(): void
    {
        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod'))->assertOk()
            ->assertSee('Pay &amp; COD', false)->assertSee('data-page-tabs', false)->assertSee(route('courier.rider.earnings'), false)
            ->assertDontSee('Delivery History');
    }
}
