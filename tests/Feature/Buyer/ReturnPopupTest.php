<?php

namespace Tests\Feature\Buyer;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Return/Refund sits beside Order Received (My Purchases and the order page) and opens a pop-up with the order's return details. */
class ReturnPopupTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    public function test_the_button_opens_the_return_pop_up_and_an_error_reopens_it(): void
    {
        $this->seedSellerTestData();
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first(), Order::DELIVERED);

        // My Purchases: the button next to Order Received, and the pop-up with the items and what they refund.
        $this->actingAs($this->buyer1)->get(route('profile.orders'))->assertOk()
            ->assertSeeInOrder(['Order Received', 'data-open-return="' . $order->id . '"'], false)
            ->assertSee('id="return-dialog-' . $order->id . '"', false)
            ->assertSee('data-return-item="' . $order->items->first()->id . '"', false)
            ->assertSee('Submit return request');

        // The order page: the same button and pop-up; the old fold-out form is gone.
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSee('data-open-return="' . $order->id . '"', false)->assertSee('id="return-dialog-' . $order->id . '"', false)
            ->assertDontSee('<summary class="cursor-pointer text-sm font-bold" style="color:#222;">Return / refund', false);

        // A mistake in the pop-up: back to the page with the pop-up open again and the message in it.
        $this->actingAs($this->buyer1)->from(route('profile.orders'))
            ->post(route('orders.return', $order->id), ['return_for' => $order->id, 'reason' => 'Damaged item', 'details' => 'Short'])
            ->assertRedirect(route('profile.orders'));
        $this->actingAs($this->buyer1)->withSession(['_old_input' => ['return_for' => $order->id], 'errors' => (new \Illuminate\Support\ViewErrorBag)
            ->put('default', new \Illuminate\Support\MessageBag(['details' => ['Please describe the problem (10 to 500 characters).']]))])
            ->get(route('profile.orders'))
            ->assertSee("document.getElementById('return-dialog-{$order->id}').showModal()", false)
            ->assertSee('Please describe the problem (10 to 500 characters).');

        // Sent from the pop-up: the request is made and the button becomes "Return status".
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'return_for' => $order->id, 'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.',
            'evidence_photos' => [$this->jpg('crushed.jpg', 600)],
        ])->assertSessionHas('success');
        $this->assertSame(1, ReturnRequest::count());
        $this->actingAs($this->buyer1)->get(route('profile.orders'))
            ->assertDontSee('data-open-return="' . $order->id . '"', false)->assertSee('Return status');
    }
}
