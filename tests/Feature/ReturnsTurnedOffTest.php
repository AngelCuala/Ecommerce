<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PlatformPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** While returns are off (marketplace.returns_enabled): no return requests and no Return & Refund Policy anywhere. */
class ReturnsTurnedOffTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedBuyerTestData();
        config(['marketplace.returns_enabled' => false]);
    }

    private function deliveredOrder(): Order
    {
        $line = $this->cart($this->buyer1, $this->p02, 1);
        $this->openCheckout($this->buyer1, [$line]);
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1))->assertSessionHasNoErrors();
        $order = Order::where('user_id', $this->buyer1->id)->latest('id')->firstOrFail();
        $order->forceFill(['status' => Order::DELIVERED, 'delivered_at' => now()->subDay()])->save();

        return $order->fresh();
    }

    public function test_the_return_refund_policy_is_hidden(): void
    {
        $this->get(route('policies.show', 'return_refund_policy'))->assertNotFound();
        $this->getJson('/api/v1/policies/return_refund_policy')->assertNotFound();
        $this->assertNotContains('return_refund_policy', array_column(PlatformPolicy::defaultPolicies(), 'key'));

        // Other policies still work and don't list or link it.
        foreach (['terms_of_service', 'seller_policy', 'privacy_policy'] as $key) {
            $this->get(route('policies.show', $key))->assertOk()->assertDontSee('return_refund_policy', false);
        }
        $this->get(route('policies.show', 'terms_of_service'))->assertSee('not available yet');
        $this->assertNotContains('return_refund_policy', array_column($this->getJson('/api/v1/policies/privacy_policy')->json('others'), 'key'));
        $this->get('/')->assertOk()->assertDontSee('Return &amp; refund policy', false);
        $this->get(route('products.show', $this->p02))->assertOk()->assertDontSee('return_refund_policy', false);
    }

    public function test_buyers_cannot_request_a_return(): void
    {
        $order = $this->deliveredOrder();
        $this->assertFalse($order->returnWindowOpen());

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()->assertDontSee(route('orders.return', $order->id), false);
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed and torn.', 'evidence' => UploadedFile::fake()->image('a.jpg'),
        ])->assertSessionHas('error', fn ($m) => str_contains($m, 'not available yet'));
        $this->assertSame(Order::DELIVERED, $order->fresh()->status);

        // The app: no return button, and no empty Return/Refund tab.
        $token = $this->buyer1->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson("/api/v1/orders/{$order->id}")->assertOk()->assertJsonPath('order.actions.return', false);
        $this->assertNotContains('return_refund', collect($this->withToken($token)->getJson('/api/v1/orders')->json('tabs'))->pluck('key')->all());
        $this->actingAs($this->buyer1)->get(route('profile.orders'))->assertOk()->assertDontSee('tab=return_refund', false);
    }

    public function test_turning_returns_back_on_brings_everything_back(): void
    {
        config(['marketplace.returns_enabled' => true]);
        $this->get(route('policies.show', 'return_refund_policy'))->assertOk();
        $this->assertTrue($this->deliveredOrder()->returnWindowOpen());
    }
}
