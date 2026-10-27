<?php

namespace Tests\Feature\Api;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Phase 2 of the app conversion: the buyer's pages and actions through /api/v1, with the web's rules. */
class BuyerApiTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedSellerTestData();
        $this->token = $this->buyer1->createToken('test')->plainTextToken;
    }

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->token)->json($method, '/api/v1' . $uri, $data);
    }

    /** Each app request starts fresh: in a test the guards and default headers would carry over to the next one. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $app = str_contains($uri, '/api/v1');
        if ($app) {
            $this->app['auth']->forgetGuards();
        }
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        if ($app) {
            $this->defaultHeaders = [];
        }

        return $response;
    }

    public function test_browsing_as_guest_and_as_buyer(): void
    {
        $home = $this->getJson('/api/v1/home')->assertOk();
        $this->assertNotEmpty($home->json('featured'));
        $this->assertNotEmpty($home->json('categories'));

        $search = $this->getJson('/api/v1/shop?search=rattan')->assertOk();
        $this->assertSame(['Rattan Fruit Basket'], collect($search->json('data'))->pluck('title')->all());
        $this->assertSame(1, $search->json('meta.total'));

        $product = $this->getJson('/api/v1/products/' . $this->p01->slug)->assertOk()->json('product');
        $this->assertSame(['Color', 'Size'], $product['variation_labels']);
        $this->assertCount(6, $product['variations']);
        $this->assertSame([199.0, 299.0], array_map('floatval', $product['price_range']));
        $this->assertFalse($product['wished']);
        $this->assertSame('Maria Clara Handicrafts', $product['seller']['name']);

        $this->api('POST', '/wishlist/' . $this->p01->id)->assertOk()->assertJsonPath('wished', true);
        $this->assertTrue($this->api('GET', '/products/' . $this->p01->slug)->json('product.wished'));
        $this->assertFalse($this->getJson('/api/v1/products/' . $this->p01->slug)->json('product.wished'));   // guest

        $this->getJson('/api/v1/products/no-such-thing-999999')->assertNotFound();

        $store = $this->getJson('/api/v1/stores/' . $this->seller1->id)->assertOk();
        $this->assertSame('Maria Clara Handicrafts', $store->json('store.name'));
        $this->assertContains('MCH50', collect($store->json('vouchers'))->pluck('code')->all());
    }

    public function test_cart_checkout_with_a_voucher_and_place_order(): void
    {
        // Same rules as the web's cart.
        $this->api('POST', '/cart/' . $this->p01->id)->assertStatus(422)->assertJsonPath('message', 'Please select a variation.');
        $this->api('POST', '/cart/' . $this->p02->id, ['quantity' => 2])->assertOk()
            ->assertJsonPath('message', '"Rattan Fruit Basket" was added to your cart.');
        $line = CartItem::where('user_id', $this->buyer1->id)->sole();
        $this->api('PATCH', '/cart/item/' . $line->id, ['quantity' => 0])->assertStatus(422);
        $this->api('PATCH', '/cart/item/' . $line->id, ['quantity' => 1])->assertOk()->assertJsonPath('message', 'Cart updated.');
        $this->assertSame(1, $this->api('GET', '/cart')->assertOk()->json('count'));

        // Checkout: ticked lines, best vouchers, shipping and totals as on the website.
        $co = $this->api('GET', '/checkout?' . http_build_query(['items' => [$line->id]]))->assertOk();
        $this->assertSame(450.0, (float) $co->json('subtotal'));
        $this->assertSame(40.0, (float) $co->json('shipping_total'));   // same city
        $this->assertSame($this->addr1->id, $co->json('address_id'));
        $this->assertNotEmpty($co->json('checkout_token'));

        $preview = $this->api('POST', '/checkout/preview', ['address_id' => $this->addr1->id, 'seller_vouchers' => [$this->seller1->id => 'MCH50']])->assertOk();
        $this->assertSame(50.0, (float) $preview->json('seller_discount'));
        $this->assertSame(440.0, (float) $preview->json('total'));   // 450 + 40 (same city) − 50

        $form = ['address_id' => $this->addr1->id, 'payment_method' => 'cod', 'checkout_token' => $co->json('checkout_token'),
            'prices' => $co->json('prices'), 'seller_vouchers' => [$this->seller1->id => 'MCH50']];
        $placed = $this->api('POST', '/checkout', $form)->assertOk();
        $this->assertCount(1, $placed->json('orders'));
        $order = Order::sole();
        $this->assertSame([Order::PENDING, 440.0], [$order->status, (float) $order->total_price]);
        $this->assertSame(19, $this->p02->fresh()->stock);
        $this->assertSame(0, CartItem::where('user_id', $this->buyer1->id)->count());

        // Same token again (double tap): no second order.
        $this->api('POST', '/checkout', $form)->assertOk()->assertJsonPath('message', 'Your order was already placed.');
        $this->assertSame(1, Order::count());
    }

    public function test_buy_now_goes_straight_to_checkout_with_only_that_item(): void
    {
        $this->cart($this->buyer1, $this->p05);   // something else already in the cart
        $this->api('POST', '/cart/' . $this->p02->id, ['action' => 'buy_now', 'quantity' => 1])->assertOk()
            ->assertJsonPath('redirect', '/checkout?buy_now=1');

        $co = $this->api('GET', '/checkout?buy_now=1')->assertOk();
        $this->assertTrue($co->json('buy_now'));
        $this->assertSame(['Rattan Fruit Basket'], collect($co->json('lines'))->pluck('product.title')->all());
    }

    public function test_order_page_actions_cancel_receive_return_and_review(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();

        $detail = $this->api('GET', '/orders/' . $order->id)->assertOk()->json('order');
        $this->assertTrue($detail['actions']['cancel']);
        $this->assertFalse($detail['actions']['confirm_received']);
        $this->assertSame('Order placed', $detail['timeline'][0]['label']);

        $this->api('POST', '/orders/' . $order->id . '/cancel', [])->assertStatus(422)->assertJsonValidationErrors('cancellation_reason');
        $this->api('POST', '/orders/' . $order->id . '/cancel', ['cancellation_reason' => 'Changed my mind'])->assertOk()
            ->assertJsonPath('message', 'Your order has been cancelled.')->assertJsonPath('order.status', Order::CANCELLED);
        $this->assertSame(20, $this->p02->fresh()->stock);

        // Delivered → return request (with photo) → seller decides; another delivered one → received → review.
        $delivered = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::DELIVERED);
        $this->assertTrue($this->api('GET', '/orders/' . $delivered->id)->json('order.actions.return'));
        $this->withToken($this->token)->post('/api/v1/orders/' . $delivered->id . '/return', [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.',
            'evidence_photos' => [UploadedFile::fake()->image('damage.jpg')],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('order.status', Order::RETURN_REQUESTED);

        $other = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::DELIVERED);
        $this->api('POST', '/orders/' . $other->id . '/received')->assertOk()->assertJsonPath('order.status', Order::COMPLETED);
        $item = $other->items()->first();
        $this->api('POST', '/reviews/item/' . $item->id, ['rating' => 6])->assertStatus(422)->assertJsonValidationErrors(['rating' => 'Please choose 1 to 5 stars.']);
        $this->api('POST', '/reviews/item/' . $item->id, ['rating' => 5, 'comment' => 'Matibay at maganda!'])->assertOk()
            ->assertJsonPath('order.order_items.0.review.rating', 5);

        // Tabs and counts as on My Purchases.
        $list = $this->api('GET', '/orders?tab=completed')->assertOk();
        $this->assertSame([$other->id], collect($list->json('data'))->pluck('id')->all());
        $this->assertSame(1, collect($list->json('tabs'))->firstWhere('key', 'cancelled')['count']);

        // Another buyer's order: not found.
        $this->withToken($this->buyer2->createToken('t')->plainTextToken)->getJson('/api/v1/orders/' . $other->id)->assertNotFound();
    }

    public function test_account_addresses_notifications_vouchers_and_follows(): void
    {
        $form = ['label' => 'Office', 'full_name' => 'Juan Miguel Dela Cruz', 'phone' => '09171234567', 'region' => 'NCR',
            'province' => 'Metro Manila', 'city' => 'Quezon City', 'barangay' => 'Central', 'address_line' => '45 Kalayaan Ave.',
            'municipality_code' => '137404000'];
        $this->api('POST', '/addresses', ['phone' => '123'] + $form)->assertStatus(422)
            ->assertJsonValidationErrors(['phone' => 'Mobile numbers are 11 digits starting with 09 (e.g. 09171234567).']);
        $added = $this->api('POST', '/addresses', $form)->assertOk();
        $new = collect($added->json('addresses'))->firstWhere('label', 'Office');
        $this->api('PATCH', '/addresses/' . $new['id'] . '/default')->assertOk();
        $this->assertTrue(collect($this->api('GET', '/addresses')->json('addresses'))->firstWhere('id', $new['id'])['is_default']);
        // Someone else's address can't be touched.
        $this->api('DELETE', '/addresses/' . $this->addr3->id)->assertStatus(403);

        UserNotification::create(['user_id' => $this->buyer1->id, 'title' => 'Hello', 'body' => 'Test', 'type' => 'info', 'link' => route('profile.orders')]);
        $notes = $this->api('GET', '/notifications')->assertOk();
        $this->assertSame(1, $notes->json('unread'));
        $this->api('POST', '/notifications/' . $notes->json('data.0.id') . '/open')->assertOk()->assertJsonPath('redirect', '/profile/orders');
        $this->assertSame(0, $this->api('GET', '/notifications')->json('unread'));

        $plat = \App\Models\Voucher::where('code', 'PLAT100')->first();
        $this->api('POST', '/vouchers/' . $plat->id . '/claim')->assertOk()->assertJsonPath('message', 'PLAT100 was added to My Vouchers.');
        $this->assertContains('PLAT100', collect($this->api('GET', '/vouchers')->json('claimed'))->pluck('code')->all());

        $this->api('POST', '/stores/' . $this->seller1->id . '/follow')->assertOk()->assertJsonPath('following', true);
        $this->assertSame(['Maria Clara Handicrafts'], collect($this->api('GET', '/following')->json('stores'))->pluck('name')->all());

        $this->api('POST', '/profile', ['name' => 'J4n'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->api('POST', '/profile', ['name' => 'Juan Miguel Dela Cruz', 'bio' => 'Mahilig sa crafts'])->assertOk()
            ->assertJsonPath('user.bio', 'Mahilig sa crafts');
        $this->api('PATCH', '/profile/password', ['current_password' => 'wrong', 'password' => 'Bago@2026x', 'password_confirmation' => 'Bago@2026x'])
            ->assertStatus(422)->assertJsonValidationErrors(['current_password' => 'Current password is incorrect.']);
    }

    public function test_chat_with_the_seller(): void
    {
        $start = $this->api('POST', '/messages/start', ['receiver_id' => $this->seller1->id, 'product_id' => $this->p02->id])->assertOk();
        [$type, $id] = [$start->json('type'), $start->json('id')];
        $this->assertSame('direct', $type);

        $this->api('POST', "/messages/{$type}/{$id}", ['body' => ''])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->api('POST', "/messages/{$type}/{$id}", ['body' => 'Available pa po ba ang Rattan Basket?'])->assertOk();
        $this->assertSame(['Available pa po ba ang Rattan Basket?'], collect($this->api('GET', "/messages/{$type}/{$id}")->json('messages'))->pluck('body')->all());

        // The seller sees it unread in their threads.
        $sellerThreads = $this->withToken($this->seller1->createToken('t')->plainTextToken)->getJson('/api/v1/messages')->assertOk();
        $this->assertSame(1, $sellerThreads->json('unread'));
    }

    public function test_sellers_and_admins_cant_use_the_buyer_pages(): void
    {
        $sellerToken = $this->seller1->createToken('t')->plainTextToken;
        $this->withToken($sellerToken)->getJson('/api/v1/cart')->assertForbidden()
            ->assertJsonPath('message', 'Seller accounts use the seller pages. Please sign in with a buyer account to shop.');
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        // Browsing stays open to them, like the website.
        $this->withToken($sellerToken)->getJson('/api/v1/home')->assertOk();
    }
}
