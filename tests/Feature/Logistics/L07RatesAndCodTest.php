<?php

namespace Tests\Feature\Logistics;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\ParcelDelivery;
use App\Models\Rider;
use App\Models\RiderRemittance;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Logistics workbook L7 (Delivery Zones & Shipping Rates) and L8 (COD Reconciliation & Reports). */
class L07RatesAndCodTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        // Rider 1 joined before the e-wallet: their pay is still kept out of the COD (RiderWallet::unpaidPay).
        $this->rider1->forceFill(['wallet_since' => now()->addYear()])->save();
    }

    private function saveRate(array $data)
    {
        return $this->actingAs($this->logistics1)->from(route('sc.rates'))->post(route('sc.rates.update'), $data + ['route' => 'Same city', 'additional_kg' => '10.00']);   // Buyer 1 and Seller 1 are in the same city
    }

    /** Shipping for Seller 1 on a new checkout of P-02 (0.8 kg) to this address. */
    private function previewShipping($address)
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        return $this->actingAs($this->buyer1)->postJson(route('checkout.preview'), ['address_id' => $address->id])->assertOk();
    }

    // ── L7 Delivery Zones & Shipping Rates ──────────────────────

    /** LOG-RATE-001 */
    public function test_log_rate_001_rate_change_applies_to_new_checkouts_only(): void
    {
        $old = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->assertEquals(40, (float) $old->shipping_fee);   // same city: ₱40 for the first kg

        $this->saveRate(['first_kg' => '45.00'])->assertSessionHas('success', 'Rate for Same city saved. It applies to checkouts started from now on.');
        $this->actingAs($this->logistics1)->get(route('sc.rates'))->assertOk()->assertSee('value="45.00"', false);

        $this->assertEquals(45, $this->previewShipping($this->addr1)->json('stores.' . $this->seller1->id . '.shipping'));
        $new = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->assertEquals(45, (float) $new->shipping_fee);

        $this->assertEquals(40, (float) $old->fresh()->shipping_fee);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $old->id))->assertSee('₱40.00');

        $log = ActivityLog::where('description', 'Shipping rate Same city changed')->sole();
        $this->assertSame(['Same city: ₱40.00 + ₱10.00/kg', 'Same city: ₱45.00 + ₱10.00/kg', 'Shipping rate changed'],
            [$log->old_value, $log->new_value, $log->reason]);
        $this->assertSame($this->logistics1->id, (int) $log->admin_id);
    }

    /** LOG-RATE-002 */
    public function test_log_rate_002_invalid_rate_values(): void
    {
        foreach (['-1', '0', 'abc'] as $value) {
            $this->saveRate(['first_kg' => $value])->assertSessionHasErrors(['first_kg' => 'The first-kg rate must be an amount of at least ₱0.01.']);
            $this->assertSame(0, ShippingRate::count(), "{$value} must not be saved");
        }
        $this->saveRate(['first_kg' => '50', 'additional_kg' => '0'])->assertSessionHasErrors('additional_kg');
        $this->assertSame(0, ShippingRate::count());

        $this->saveRate(['first_kg' => '0.01'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEquals(0.01, (float) ShippingRate::where('route', 'Same city')->sole()->first_kg);
    }

    /** LOG-RATE-003 */
    public function test_log_rate_003_unserviceable_area_is_blocked(): void
    {
        $this->assertStringContainsString("don't deliver to Batanes", $this->previewShipping($this->addr4)->json('unserviceable'));
        $this->actingAs($this->buyer1)->from(route('checkout.index'))->post(route('checkout.store'), $this->checkoutForm($this->addr4))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Batanes'));
        $this->assertSame(0, Order::count());
    }

    /** LOG-RATE-004 */
    public function test_log_rate_004_add_a_zone(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.rates'))->assertOk()->assertSee('Not serviceable yet: Batanes');
        $this->actingAs($this->logistics1)->from(route('sc.rates'))->post(route('sc.zones.store'),
            ['province_code' => '020900000', 'first_kg' => '150.00', 'additional_kg' => '50.00'])
            ->assertSessionHas('success', 'Batanes is now covered. Buyers there can check out.');
        $this->assertSame('Batanes', ShippingZone::sole()->province_name);
        $this->assertNotNull(ActivityLog::where('description', 'Delivery zone added for Batanes')->first());

        $preview = $this->previewShipping($this->addr4);
        $this->assertNull($preview->json('unserviceable'));
        $this->assertEquals(150, $preview->json('stores.' . $this->seller1->id . '.shipping'));

        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)], $this->addr4)->first();
        $this->assertEquals(150, (float) $order->shipping_fee);

        // The same province can't get a second zone.
        $this->actingAs($this->logistics1)->from(route('sc.rates'))->post(route('sc.zones.store'),
            ['province_code' => '020900000', 'first_kg' => '120.00', 'additional_kg' => '40.00'])->assertSessionHasErrors('province_code');
        $this->assertSame(1, ShippingZone::count());
    }

    // ── L8 COD Reconciliation & Reports ─────────────────────────

    /** A COD delivery Rider 1 completed today, collecting this much. */
    private function codDelivered(float $amount, ?Rider $rider = null): ParcelDelivery
    {
        $d = $this->deliveryFor($rider ?? $this->rider1);
        $d->update(['status' => 'delivered', 'delivered_at' => now(), 'cod_amount' => $amount, 'cod_collected' => $amount]);
        $d->parcel->update(['status' => 'in_transit']);   // out for delivery first: a parcel can't jump to Delivered
        $d->parcel->update(['status' => 'delivered']);
        return $d->fresh();
    }

    private function remit(float $amount)
    {
        return $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['amount' => $amount, 'request_token' => (string) Str::uuid()]);
    }

    private function reconciliation()
    {
        return $this->actingAs($this->logistics1)->get(route('sc.remittances', ['date' => today()->toDateString()]))->assertOk();
    }

    /** LOG-COD-001 */
    public function test_log_cod_001_remittance_matches_cod_collected(): void
    {
        $a = $this->codDelivered(900);
        $b = $this->codDelivered(900);
        $this->remit(1800)->assertSessionHasNoErrors();

        $res = $this->reconciliation();
        $row = $res->viewData('daily')->firstWhere('rider.id', $this->rider1->id);
        $this->assertSame([1800.0, 1800.0, 0.0], [$row['collected'], $row['remitted'], $row['difference']]);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $row['orders']->pluck('id')->all());
        $res->assertSee('data-reconcile="Pedro Jose Garcia"', false)->assertSee('₱1,800.00')
            ->assertSee($a->parcel->order->number())->assertSee($b->parcel->order->number())
            ->assertDontSee('data-day-shortfall', false);
    }

    /** LOG-COD-002: the amount is worked out (COD − the rider's pay), so a rider can't hand over less. */
    public function test_log_cod_002_the_rider_hands_over_the_cod_minus_their_pay(): void
    {
        $this->codDelivered(900);
        $this->codDelivered(900);
        $this->remit(1750)->assertSessionHasNoErrors();   // a typed amount is ignored

        $r = RiderRemittance::sole();
        $this->assertEquals([1700, 100, 1800, 0], [(float) $r->amount, (float) $r->pay_kept, (float) $r->expected_amount, $r->shortfall()]);
        $res = $this->reconciliation();
        $this->assertSame(0.0, $res->viewData('daily')->firstWhere('rider.id', $this->rider1->id)['difference']);
        $res->assertDontSee('data-day-shortfall', false)->assertSee('₱1,700.00')->assertSee('₱100.00');
    }

    /** LOG-COD-003 */
    public function test_log_cod_003_confirm_remittance(): void
    {
        $this->codDelivered(900);
        $this->codDelivered(900);
        $this->remit(0);
        $remittance = RiderRemittance::sole();
        $wallet = app(RiderWallet::class);
        $this->assertEquals(1800, $wallet->codBalance($this->rider1));

        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.confirm', $remittance))
            ->assertSessionHas('success', 'Remittance confirmed.');
        $this->assertSame('confirmed', $remittance->fresh()->status);
        $this->assertEquals(0, $wallet->codBalance($this->rider1->fresh()));   // ₱1,700 cash + ₱100 pay kept
        $this->assertEquals(0, $wallet->unpaidPay($this->rider1->fresh()));

        $log = ActivityLog::where('description', 'like', 'Confirmed ₱1,700.00 COD from Pedro Jose Garcia%')->sole();
        $this->assertSame(['Pending ₱1,700.00', 'Confirmed ₱1,700.00', $this->logistics1->id], [$log->old_value, $log->new_value, (int) $log->admin_id]);

        // Only once.
        $this->actingAs($this->logistics1)->post(route('sc.remittances.confirm', $remittance))->assertSessionHas('error', 'This remittance is already confirmed.');
    }

    /** Download a report as PDF; $data receives what the PDF template was rendered with. */
    private function pdfOf(string $url, ?array &$data)
    {
        $data = null;
        \Illuminate\Support\Facades\View::composer('sc.reports-pdf', function ($view) use (&$data) { $data = $view->getData(); });
        $res = $this->actingAs($this->logistics1)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('.pdf', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $res->getContent());
        $this->assertNotNull($data, 'the PDF template was rendered');
        return $res;
    }

    /** LOG-COD-004 */
    public function test_log_cod_004_export_matches_the_screen(): void
    {
        $this->codDelivered(900);
        $this->codDelivered(900, $this->rider2);
        $range = ['from' => today()->subDays(2)->toDateString(), 'to' => today()->toDateString()];

        $screen = $this->actingAs($this->logistics1)->get(route('sc.reports', $range + ['tab' => 'cod']))->assertOk()->assertSee('₱1,800.00');
        $rows = $screen->viewData('codRows');
        $this->assertCount(2, $rows);

        // The PDF holds the same rows and the same total as the screen.
        $pdf = $this->pdfOf(route('sc.reports', $range + ['tab' => 'cod', 'export' => 'pdf']), $data);
        $this->assertStringContainsString('sc-cod-report_', $pdf->headers->get('Content-Disposition'));
        $this->assertSame(['Delivered at', 'Rider', 'Order', 'Tracking', 'COD collected'], $data['header']);
        $this->assertCount($rows->count(), $data['rows']);
        $this->assertSame('₱1,800.00', end($data['totals']));
        $this->assertSame('₱900.00', $data['rows'][0][4]);

        // The deliveries report too, and old CSV / XLSX links now give the PDF.
        $this->pdfOf(route('sc.reports', $range + ['tab' => 'daily', 'export' => 'csv']), $daily);
        $this->assertSame(['Date', 'Received', 'Sorted', 'Delivered', 'Failed', 'Returned'], $daily['header']);
        $this->assertSame(2, array_sum(array_column($daily['rows'], 3)));
        $this->assertSame('Daily Summary', $daily['title']);
    }

    /** LOG-COD-005 */
    public function test_log_cod_005_export_with_no_data(): void
    {
        $range = ['from' => '2026-01-01', 'to' => '2026-01-01', 'tab' => 'cod'];
        $this->actingAs($this->logistics1)->get(route('sc.reports', $range))->assertOk()->assertSee('No COD collected in this range.');

        $this->pdfOf(route('sc.reports', $range + ['export' => 'pdf']), $data);
        $this->assertSame([], $data['rows']);
        $this->assertNull($data['totals']);   // the PDF says "No data in this date range."
    }
}
