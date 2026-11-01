<?php

namespace Tests\Feature\Seller;

use App\Models\Order;
use App\Models\UserNotification;
use App\Services\SellerSales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/**
 * Seller dashboard (own data, periods, order counts, best sellers), commission details
 * (rate kept per line, statuses, totals that match the admin reports) and low-stock alerts.
 * Commission rate in the test data: 5%.
 */
class SellerDashboardAndCommissionTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    /** Seller 1: two orders of P-02 (₱450) this month; Seller 2: one order of P-05 (₱399). */
    private function sales(): array
    {
        $a = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $b = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first();
        $c = $this->placeOrder($this->buyer2, [$this->cart($this->buyer2, $this->p05)])->first();
        return [$a, $b, $c];
    }

    public function test_dashboard_shows_only_the_sellers_own_numbers(): void
    {
        [$a, $b] = $this->sales();
        $this->moveTo($a, Order::COMPLETED);

        $page = $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertOk();
        $page->assertSeeInOrder(['Sales', '₱1,350.00', 'Commission', '₱67.50', 'Net earnings', '₱1,282.50', 'Orders', '2'], false)
            ->assertSee('data-best-seller="' . $this->p02->id . '"', false)
            ->assertSee('Rattan Fruit Basket')
            ->assertDontSee('USB-C Fast Charger');                 // Seller 2's product

        $this->assertSame(['total' => 2, 'in_progress' => 1, 'completed' => 1, 'cancelled' => 0, 'refunded' => 0], $page->viewData('orderCounts'));
        $this->assertSame(3, $page->viewData('bestSellers')->first()->units);
    }

    public function test_cancelled_and_refunded_orders_are_counted_but_never_earn(): void
    {
        [$a, $b] = $this->sales();
        $a->update(['status' => Order::CANCELLED]);
        $this->moveTo($b, Order::DELIVERED)->update(['status' => Order::REFUNDED]);

        $page = $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertOk();
        $this->assertSame(0.0, $page->viewData('money')['gross']);
        $this->assertSame(0.0, $page->viewData('money')['commission']);
        $this->assertSame(900.0, $page->viewData('money')['refunds']);
        $this->assertSame(1, $page->viewData('orderCounts')['cancelled']);
        $this->assertSame(1, $page->viewData('orderCounts')['refunded']);
    }

    public function test_period_filters_today_week_month_and_custom(): void
    {
        [$a, $b] = $this->sales();
        $a->forceFill(['created_at' => now()->subMonths(2)])->save();

        $gross = fn (array $q) => $this->actingAs($this->seller1)->get(route('seller.dashboard', $q))->assertOk()->viewData('money')['gross'];
        $this->assertSame(900.0, $gross(['period' => 'today']));
        $this->assertSame(900.0, $gross(['period' => 'week']));
        $this->assertSame(900.0, $gross([]));                                  // default: this month
        $this->assertSame(450.0, $gross(['period' => 'custom', 'from' => now()->subMonths(2)->subDay()->toDateString(), 'to' => now()->subMonths(2)->addDay()->toDateString()]));

        $this->actingAs($this->seller1)->get(route('seller.dashboard', ['period' => 'custom', 'from' => '2026-05-10', 'to' => '2026-05-01']))
            ->assertSessionHasErrors('to');
        $this->actingAs($this->seller1)->get(route('seller.dashboard', ['period' => 'yesterday']))->assertSessionHasErrors('period');
    }

    public function test_commission_lines_keep_the_rate_of_the_day_they_were_bought(): void
    {
        [$a] = $this->sales();
        config(['marketplace.commission_rate' => 12]);                         // rate changes later
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);

        $page = $this->actingAs($this->seller1)->get(route('seller.commissions'))->assertOk();
        $rates = $page->viewData('items')->getCollection()->pluck('commission_rate')->map(fn ($r) => (float) $r)->sort()->values()->all();
        $this->assertSame([5.0, 5.0, 12.0], $rates);
        $this->assertSame(22.5 + 45.0 + 54.0, $page->viewData('summary')['commission']);
    }

    public function test_commission_statuses_filters_and_totals_match_the_admin_report(): void
    {
        [$a, $b] = $this->sales();
        $this->moveTo($a, Order::COMPLETED);
        $c2 = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p03)])->first();
        $c2->update(['status' => Order::CANCELLED]);

        $get = fn (array $q = []) => $this->actingAs($this->seller1)->get(route('seller.commissions', $q))->assertOk();
        $page = $get();
        $page->assertSee('data-status="finalized"', false)->assertSee('data-status="pending"', false)->assertSee('data-status="reversed"', false);
        $this->assertSame(['gross' => 1350.0, 'refunds' => 0.0, 'commission' => 67.5, 'net' => 1282.5, 'pending_commission' => 45.0, 'finalized_commission' => 22.5, 'lines' => 2],
            $page->viewData('summary'));

        $this->assertSame([$a->id], $get(['status' => 'finalized'])->viewData('items')->getCollection()->pluck('order_id')->all());
        $this->assertSame([$c2->id], $get(['status' => 'reversed'])->viewData('items')->getCollection()->pluck('order_id')->all());
        $this->actingAs($this->seller1)->get(route('seller.commissions', ['status' => 'bogus']))->assertSessionHasErrors('status');

        // Same totals as Analytics & Reports for the same month.
        $admin = app(SellerSales::class)->totals([now()->startOfMonth()->toDateTimeString(), now()->endOfDay()->toDateTimeString()])->firstWhere('id', $this->seller1->id);
        $this->assertEqualsWithDelta($admin->gross, $page->viewData('summary')['gross'], 0.001);
        $this->assertEqualsWithDelta($admin->commission, $page->viewData('summary')['commission'], 0.001);
        $this->assertEqualsWithDelta($admin->payout, $page->viewData('summary')['net'], 0.001);

        // The PDF export has the same lines.
        $data = null;
        \Illuminate\Support\Facades\View::composer('seller.commissions.pdf', function ($view) use (&$data) { $data = $view->getData(); });
        $pdf = $get(['export' => 'pdf'])->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('commission_', $pdf->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertCount(3, $data['rows']);
        $this->assertContains('reversed', $data['rows']->pluck('status')->all());
        $this->assertSame($page->viewData('summary'), $data['summary']);
        $this->assertStringStartsWith('%PDF-', $get(['export' => 'csv'])->getContent());   // old CSV links get the PDF
    }

    public function test_a_seller_never_sees_another_sellers_commission(): void
    {
        $this->sales();
        $page = $this->actingAs($this->seller2)->get(route('seller.commissions'))->assertOk();
        $this->assertSame([$this->p05->id], $page->viewData('items')->getCollection()->pluck('product_id')->unique()->values()->all());
        $page->assertDontSee('Rattan Fruit Basket');
        $this->actingAs($this->buyer1)->get(route('seller.commissions'))->assertForbidden();   // buyers have no seller panel
    }

    public function test_low_stock_alert_uses_the_products_own_level_once_and_again_after_restock(): void
    {
        $this->p02->update(['stock' => 20, 'low_stock_threshold' => 18]);
        $alerts = fn () => UserNotification::where('user_id', $this->seller1->id)->where('title', 'Low stock')->count();

        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 1)]);       // 19: above 18
        $this->assertSame(0, $alerts());
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)]);       // 17: crossed
        $this->assertSame(1, $alerts());
        $this->assertStringContainsString('alert at 18 or fewer', UserNotification::where('title', 'Low stock')->latest('id')->value('body'));
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 1)]);       // 16: still low, no repeat
        $this->assertSame(1, $alerts());

        $this->actingAs($this->seller1)->patch(route('seller.books.stock', $this->p02), ['stock' => 30])->assertSessionHasNoErrors();
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 13)]);      // 17 again: alerts again
        $this->assertSame(2, $alerts());
        $this->assertSame(0, UserNotification::where('user_id', $this->seller2->id)->count());   // only the owner is told
    }

    public function test_selling_the_last_unit_sends_a_sold_out_alert_and_stock_never_goes_negative(): void
    {
        $this->p03->update(['stock' => 1]);
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p03)]);
        $this->assertSame(0, $this->p03->fresh()->stock);
        $this->assertSame(1, UserNotification::where('user_id', $this->seller1->id)->where('title', 'Sold out')->count());

        // Another buyer with it still in the cart can't take it below zero.
        $this->cart($this->buyer2, $this->p03);
        $this->openCheckout($this->buyer2, []);
        $this->actingAs($this->buyer2)->post(route('checkout.store'), $this->checkoutForm($this->addr3));
        $this->assertSame(0, $this->p03->fresh()->stock);

        $page = $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertOk();
        $page->assertSee('data-low-stock="' . $this->p03->id . '"', false)->assertSee('Out of stock');
    }

    public function test_seller_sets_a_products_low_stock_level_on_the_form(): void
    {
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p02), $this->editForm($this->p02, ['low_stock_threshold' => 25]))
            ->assertSessionHasNoErrors();
        $this->assertSame(25, $this->p02->fresh()->low_stock_threshold);
        $this->assertTrue($this->p02->fresh()->isLowStock());                 // 20 in stock ≤ 25

        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p02), $this->editForm($this->p02, ['low_stock_threshold' => -1]))
            ->assertSessionHasErrors('low_stock_threshold');
    }
}
