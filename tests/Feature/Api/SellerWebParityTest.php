<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's seller side, kept 1:1 with the website: dashboard period, Commission details, inventory filters, order page. */
class SellerWebParityTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedRiderTestData();
        $this->token = $this->seller1->createToken('t')->plainTextToken;
    }

    private function api(string $uri)
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($this->token)->getJson('/api/v1/seller' . $uri);
    }

    public function test_dashboard_by_period_with_money_best_sellers_and_products(): void
    {
        $this->parcel('pickup_approved');   // an order of Seller 1
        $d = $this->api('/dashboard?period=today')->assertOk();
        $this->assertSame('today', $d->json('period'));
        $this->assertArrayHasKey('gross', $d->json('money'));
        $this->assertArrayHasKey('net', $d->json('money'));
        $this->assertArrayHasKey('active', $d->json('product_counts'));
        $this->assertIsArray($d->json('best_sellers'));
        $this->assertSame(1, $d->json('order_counts.total'));
        $this->api('/dashboard?period=custom&from=2026-10-10&to=2026-10-01')->assertStatus(422);
    }

    public function test_commission_details_and_export(): void
    {
        $this->parcel('pickup_approved');
        $c = $this->api('/commissions?period=month')->assertOk();
        $this->assertSame(1, $c->json('meta.total'));
        $line = $c->json('data.0');
        $this->assertEqualsWithDelta($line['gross'], $line['commission'] + $line['net'], 0.01);
        $this->assertArrayHasKey('pending', $c->json('statuses'));
        $this->api('/commissions?status=nope')->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->get('/api/v1/seller/commissions/export?format=csv')->assertOk();
    }

    public function test_inventory_filters_sorts_and_pages(): void
    {
        $r = $this->api('/products?status=active&sort=price_high')->assertOk();
        $this->assertSame('price_high', $r->json('sort'));
        $this->assertArrayHasKey('best_selling', $r->json('sorts'));
        $this->assertNotNull($r->json('meta.total'));
        $prices = collect($r->json('data'))->pluck('price')->all();
        $this->assertSame($prices, collect($prices)->sortDesc()->values()->all());
        $this->assertArrayHasKey('low_stock_at', $r->json('data.0'));
        $this->api('/products?min_price=500&max_price=100')->assertStatus(422);
    }

    public function test_order_page_has_tracking_and_gcash(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $o = $this->api('/orders/' . $parcel->order_id)->assertOk()->json('order');
        $this->assertSame($parcel->tracking_number, $o['tracking']['tracking_number']);
        $this->assertSame($parcel->fresh()->waybill_number, $o['waybill_number']);
        $this->assertNull($o['gcash']);   // COD
        $this->assertSame('Accept order', $o['actions']['accept_label']);
    }
}
