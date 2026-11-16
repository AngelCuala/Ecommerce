<?php

namespace Tests\Feature\Buyer;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** The return sits at the top of the order page with its steps and everything that happened, and it shows in the order timeline. */
class ReturnProgressTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    public function test_the_return_card_shows_the_steps_and_what_happened(): void
    {
        $this->seedSellerTestData();
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first(), Order::DELIVERED);
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Wrong item received', 'details' => 'I got a different basket.', 'evidence_photos' => [$this->jpg('wrong.jpg', 600)],
        ])->assertSessionHas('success');

        // Waiting for the seller: the card comes before the items, the seller step is the current one.
        $page = $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSeeInOrder(['data-return-progress="requested"', 'id="rate"', 'Order timeline'], false)
            ->assertSee('Waiting for the seller to answer')
            ->assertSee('data-return-step="seller" data-state="current"', false)
            ->assertSee('data-return-step="pickup" data-state="todo"', false)
            ->assertSee('data-timeline-return', false);

        // The seller rejects, the buyer escalates, ALVY refunds: each shows in the history, in order.
        $this->actingAs($this->seller1)->from(route('seller.orders.show', $order->id))
            ->post(route('seller.orders.return', $order->id), ['decision' => 'reject', 'seller_note' => 'That is the item you ordered.']);
        $this->actingAs($this->buyer1)->post(route('orders.escalate', $order->id), ['dispute_note' => 'The label says another model.']);
        $admin = $this->account('admin', 'admin@alvy.example.com', 'ALVY Admin');
        $this->actingAs($admin)->post(route('admin.disputes.decide', ReturnRequest::sole()), ['decision' => 'refund', 'reason' => 'The photo shows a different model.']);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSee('data-return-step="seller" data-state="failed"', false)
            ->assertSee('data-return-step="alvy" data-state="done"', false)
            ->assertSee('data-return-step="refund" data-state="done"', false)
            ->assertSeeInOrder(['Return requested', 'Seller rejected the return', 'You asked ALVY to review it', 'ALVY decided: refund', 'Refunded ₱'])
            ->assertSee('That is the item you ordered.')->assertSee('The photo shows a different model.');
    }
}
