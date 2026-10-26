<?php

namespace Tests\Feature\Rider;

use App\Models\ParcelDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Courier "Delivery History": the rider's real delivery jobs (parcel_deliveries), totals and filters. */
class DeliveryHistoryPageTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private ParcelDelivery $delivered, $failed, $onTheWay, $someoneElses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1, $this->rider2);

        $this->delivered = $this->deliveryFor($this->rider1);
        $this->delivered->forceFill(['status' => 'delivered', 'delivered_at' => now()->subDays(2), 'cod_collected' => 500])->saveQuietly();

        $this->failed = $this->deliveryFor($this->rider1);
        $this->failed->forceFill(['status' => 'failed', 'attempt' => 2, 'failure_reason' => 'Nobody home'])->saveQuietly();

        $this->onTheWay = $this->deliveryFor($this->rider1);
        $this->onTheWay->forceFill(['status' => 'out_for_delivery'])->saveQuietly();

        $this->someoneElses = $this->deliveryFor($this->rider2);
    }

    private function page(array $query = [])
    {
        return $this->actingAs($this->rider1->user)->get(route('courier.history', $query))->assertOk();
    }

    private function tracking(ParcelDelivery $d): string
    {
        return $d->parcel->tracking_number;
    }

    public function test_shows_the_riders_own_delivery_jobs_with_totals(): void
    {
        $this->page()
            ->assertSee('data-history-row="' . $this->tracking($this->delivered) . '"', false)
            ->assertSee('data-history-row="' . $this->tracking($this->failed) . '"', false)
            ->assertSee('data-history-row="' . $this->tracking($this->onTheWay) . '"', false)
            ->assertDontSee($this->tracking($this->someoneElses))
            ->assertSee('Nobody home')->assertSee('attempt 2')
            ->assertSee('+₱50.00')->assertSee('COD ₱500.00')
            ->assertSee(route('courier.rider.deliveries.show', $this->delivered->id), false)
            ->assertSeeInOrder(['data-summary="delivered"', '>1<', 'data-summary="in_progress"', '>1<', 'data-summary="failed"', '>1<', 'data-summary="earned"', '₱50.00'], false)
            ->assertSee('Delivery History');   // sidebar label
    }

    public function test_filters_by_order_or_tracking_status_and_date(): void
    {
        $order = $this->delivered->parcel->order;
        // An order number matches exactly, with or without # and zeros.
        foreach ([$order->number(), ltrim($order->number(), '#'), (string) $order->id] as $q) {
            $this->page(['q' => $q])->assertSee($this->tracking($this->delivered))->assertDontSee($this->tracking($this->failed));
        }
        $this->page(['q' => (string) ($order->id + 100)])->assertSee('No deliveries match these filters');
        $this->page(['q' => strtolower($this->tracking($this->failed))])->assertSee($this->tracking($this->failed))->assertDontSee($this->tracking($this->delivered));

        $this->page(['status' => 'failed'])->assertSee($this->tracking($this->failed))->assertDontSee($this->tracking($this->onTheWay));
        $this->page(['status' => 'in_progress'])->assertSee($this->tracking($this->onTheWay))->assertDontSee($this->tracking($this->delivered));

        $this->page(['date' => now()->subDays(2)->toDateString()])->assertSee($this->tracking($this->delivered))->assertDontSee($this->tracking($this->onTheWay));
        $this->page(['date' => now()->subYear()->toDateString()])->assertSee('No deliveries match these filters');

        $this->actingAs($this->rider1->user)->get(route('courier.history', ['status' => 'bogus']))->assertSessionHasErrors('status');
    }

    public function test_a_rider_with_no_jobs_sees_the_empty_state(): void
    {
        $this->online($this->agilaRider);
        $this->actingAs($this->agilaRider->user)->get(route('courier.history'))->assertOk()
            ->assertSee('No delivery history yet')->assertDontSee('No deliveries match');
    }
}
