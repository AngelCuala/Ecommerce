<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Services\SortingCenterCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Sorting center "Commission & Earnings": share of the shipping fee (split between hubs), ALVY's
 * commission, the hub's rider pay (₱30 pickup, ₱50 delivery) and the net.
 *
 *   A  ₱100 fee, picked up by Logistics 1's rider, transferred, delivered by Logistics 2's rider
 *      L1: 50 − 5 − 30 = 15 (earned)      L2: 50 − 5 − 50 = −5 (earned)
 *   B  ₱100 fee, picked up by Logistics 1's rider, still at Logistics 1
 *      L1: 100 − 10 − 30 = 60 (pending)
 *   C  ₱100 fee, order cancelled after Logistics 1's rider picked it up
 *      L1: 0 − 0 − 30 = −30 (reversed)
 */
class SortingCenterCommissionTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private Parcel $a, $b, $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        config(['marketplace.sorting_center_commission_rate' => 10]);

        $this->a = $this->handled($this->rider1, $this->agilaRider);
        $this->b = $this->handled($this->rider2);
        $this->c = $this->handled($this->rider1);
        $this->c->order->update(['status' => Order::CANCELLED]);
    }

    /** A parcel with a ₱100 shipping fee picked up by $pickup and, if given, delivered by $delivery (after a transfer to its hub). */
    private function handled(Rider $pickup, ?Rider $delivery = null): Parcel
    {
        $p = $this->parcel($delivery ? 'delivered' : 'received', [], null, $delivery ? $delivery->sortingCenter : null);
        $p->order->update(['shipping_fee' => 100]);
        ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $pickup->id, 'sorting_center_id' => $pickup->sorting_center_id,
            'status' => 'received', 'picked_up_at' => now()]);
        if ($delivery) {
            ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $delivery->id, 'status' => 'delivered', 'attempt' => 1, 'delivered_at' => now()]);
            $p->order->update(['status' => Order::DELIVERED]);
        }
        return $p->fresh();
    }

    private function line(int $scId, Parcel $p): array
    {
        return app(SortingCenterCommission::class)->lines($scId)->firstWhere(fn ($l) => $l['parcel']->id === $p->id);
    }

    public function test_each_hub_gets_its_share_minus_commission_and_its_own_rider_pay(): void
    {
        $this->assertSame(['hubs' => 2, 'share' => 50.0, 'commission' => 5.0, 'rider_pay' => 30.0, 'net' => 15.0, 'status' => 'earned', 'role' => 'Pickup'],
            array_intersect_key($this->line($this->logistics1->id, $this->a), array_flip(['hubs', 'share', 'commission', 'rider_pay', 'net', 'status', 'role'])));
        $this->assertSame(['share' => 50.0, 'commission' => 5.0, 'rider_pay' => 50.0, 'net' => -5.0, 'role' => 'Delivery'],
            array_intersect_key($this->line($this->logistics2->id, $this->a), array_flip(['share', 'commission', 'rider_pay', 'net', 'role'])));

        $b = $this->line($this->logistics1->id, $this->b);
        $this->assertSame([100.0, 10.0, 30.0, 60.0, 'pending'], [$b['share'], $b['commission'], $b['rider_pay'], $b['net'], $b['status']]);

        $c = $this->line($this->logistics1->id, $this->c);
        $this->assertSame([0.0, 0.0, 30.0, -30.0, 'reversed'], [$c['share'], $c['commission'], $c['rider_pay'], $c['net'], $c['status']]);

        // A cancelled parcel earns no fee even if its order still says otherwise.
        $this->b->forceFill(['status' => 'cancelled'])->saveQuietly();
        $this->assertSame('reversed', app(SortingCenterCommission::class)->statusOf($this->b->fresh()));
    }

    public function test_totals_and_the_commission_rate_setting(): void
    {
        $svc = app(SortingCenterCommission::class);
        $this->assertSame(['fees' => 150.0, 'commission' => 15.0, 'rider_pay' => 90.0, 'net' => 45.0, 'earned_net' => 15.0, 'pending_net' => 60.0, 'parcels' => 3],
            $svc->summarize($svc->lines($this->logistics1->id)));

        config(['marketplace.sorting_center_commission_rate' => 20]);
        $this->assertSame(30.0, $svc->summarize($svc->lines($this->logistics1->id))['commission']);
    }

    public function test_the_page_shows_the_hubs_own_parcels_and_net(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.commissions'))->assertOk()
            ->assertSee('Commission &amp; Earnings', false)
            ->assertSee('data-total="fees">₱150.00', false)
            ->assertSee('data-total="commission">−₱15.00', false)
            ->assertSee('data-total="rider_pay">−₱90.00', false)
            ->assertSee('data-total="net">₱45.00', false)
            ->assertSee($this->a->tracking_number)->assertSee('Split with 1 other hub')
            ->assertSee(route('sc.commissions'), false);   // sidebar link

        // Logistics 2 only sees the parcel it delivered.
        $this->actingAs($this->logistics2)->get(route('sc.commissions'))->assertOk()
            ->assertSee($this->a->tracking_number)->assertDontSee($this->b->tracking_number)->assertDontSee($this->c->tracking_number)
            ->assertSee('data-total="net">−₱5.00', false);
    }

    public function test_status_filter_period_and_export(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.commissions', ['status' => 'pending']))->assertOk()
            ->assertSee($this->b->tracking_number)->assertDontSee($this->a->tracking_number)
            ->assertSee('data-total="net">₱45.00', false);   // the cards cover the whole period

        $this->get(route('sc.commissions', ['period' => 'custom', 'from' => now()->subYear()->toDateString(), 'to' => now()->subMonths(6)->toDateString()]))
            ->assertOk()->assertSee('No parcels for this period');
        $this->get(route('sc.commissions', ['status' => 'bogus']))->assertSessionHasErrors('status');

        $csv = $this->get(route('sc.commissions', ['export' => 'csv']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('ALVY commission (PHP)', $body);
        $this->assertStringContainsString($this->a->tracking_number, $body);
    }

    public function test_a_parcel_delivered_without_a_rider_pickup_does_not_break_the_page(): void
    {
        // Dropped off at the hub by the seller (no pickup job), then delivered by Logistics 2's rider.
        $p = $this->parcel('delivered', [], null, $this->agilaRider->sortingCenter);
        $p->order->update(['shipping_fee' => 100, 'status' => Order::DELIVERED]);
        ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $this->agilaRider->id, 'status' => 'delivered', 'attempt' => 1, 'delivered_at' => now()]);

        $this->actingAs($this->logistics2)->get(route('sc.commissions'))->assertOk()->assertSee($p->tracking_number);
        $this->assertSame('Delivery', $this->line($this->logistics2->id, $p->fresh())['role']);
    }

    public function test_only_sorting_centers_can_open_it(): void
    {
        auth()->logout();
        $this->get(route('sc.commissions'))->assertRedirect();
        $this->actingAs($this->rider1->user)->get(route('sc.commissions'))->assertStatus(403);
    }
}
