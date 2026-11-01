<?php

namespace Tests\Feature\Buyer;

use App\Models\ShipmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The buyer's order page: styled cards, the "Out for delivery" card with the code, and tracking after delivery. */
class OrderPageDeliveryCardTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1);
    }

    public function test_out_for_delivery_card_shows_rider_and_cod_without_a_code(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $order = $d->parcel->order;

        $page = $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk();
        $page->assertSee('.pf-card {', false)                          // the account styles are loaded, so cards render
            ->assertSee('data-out-for-delivery', false)
            ->assertSee('Pedro Jose Garcia')->assertSee('Motorcycle 123ABC')
            ->assertSee('The rider takes a photo when handing it over as proof of delivery.')
            ->assertDontSee('delivery code')                           // no OTP to give the rider
            ->assertSee('data-cod-due', false)->assertSee('₱' . number_format($d->cod_amount, 2));
        // The delivery card comes before the items.
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'Items <span'), strpos($html, 'data-out-for-delivery'));
    }

    public function test_after_delivery_tracking_says_completed_and_drops_an_old_review_banner(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $parcel = $d->parcel;
        ShipmentException::create(['parcel_id' => $parcel->id, 'type' => 'other', 'details' => 'No sorting center serves the destination', 'status' => 'open']);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))
            ->assertSee('being reviewed by our logistics team');    // still on its way: the review shows

        $parcel->forceFill(['status' => 'delivered'])->saveQuietly();
        $d->forceFill(['status' => 'delivered', 'delivered_at' => now()])->saveQuietly();

        $this->get(route('profile.orders.show', $parcel->order_id))->assertOk()
            ->assertSee('Delivery completed')->assertDontSee('Not assigned yet')
            ->assertDontSee('being reviewed by our logistics team');
    }
}
