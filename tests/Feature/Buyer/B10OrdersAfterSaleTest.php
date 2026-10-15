<?php

namespace Tests\Feature\Buyer;

use App\Models\Delivery;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Buyer workbook B10 (Tracking), B11 (Cancellation), B12 (Return & Refund), B13 (Ratings), B14 (Chat & Notifications). */
class B10OrdersAfterSaleTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedBuyerTestData();
    }

    /** An order of P-02 placed through checkout, then moved to $status. */
    private function orderIn(string $status, $buyer = null, $address = null): Order
    {
        $buyer ??= $this->buyer1;
        $line = $this->cart($buyer, $this->p02, 2);
        $this->openCheckout($buyer, [$line]);
        $this->actingAs($buyer)->post(route('checkout.store'), $this->checkoutForm($address ?? ($buyer->is($this->buyer1) ? $this->addr1 : $this->addr3)))
            ->assertSessionHasNoErrors();
        $order = Order::where('user_id', $buyer->id)->latest('id')->firstOrFail();

        foreach (['Processing', 'Shipped', 'Delivered', 'Completed'] as $step) {
            if (array_search($step, ['Pending', 'Processing', 'Shipped', 'Delivered', 'Completed']) > array_search($status, ['Pending', 'Processing', 'Shipped', 'Delivered', 'Completed'])) {
                break;
            }
            $order->update(['status' => $step]);
        }
        if ($status === 'Cancelled') {
            $order->update(['status' => 'Cancelled', 'cancellation_reason' => 'Test']);
        }
        return $order->fresh();
    }

    // ── B10 Order Tracking & History ──────────────────────────

    /** BUY-TRK-001 */
    public function test_each_order_is_in_exactly_one_tab(): void
    {
        $orders = [];
        foreach (['to_ship' => 'Processing', 'to_receive' => 'Shipped', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $tab => $status) {
            $orders[$tab] = $this->orderIn($status);
        }

        foreach ($orders as $tab => $order) {
            $ids = $this->actingAs($this->buyer1)->get(route('profile.orders', ['tab' => $tab]))->assertOk()
                ->viewData('orders')->pluck('id')->all();
            $this->assertSame([$order->id], $ids, "tab {$tab}");
        }
        $this->actingAs($this->buyer1)->get(route('profile.orders', ['tab' => 'to_pay']))->assertOk()->assertSee('No orders here yet');
    }

    /** BUY-TRK-002 */
    public function test_timeline_lists_every_status_change_in_order(): void
    {
        $order = $this->orderIn('Delivered');
        $this->assertSame(['Pending', 'Processing', 'Shipped', 'Delivered'], $order->events()->pluck('status')->all());
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSeeInOrder(['Order placed', 'Seller is preparing the order', 'Picked up — on the way', 'Delivered']);
    }

    /** BUY-TRK-003, 004 */
    public function test_order_received_only_after_delivery(): void
    {
        $onTheWay = $this->orderIn('Shipped');
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $onTheWay->id))->assertDontSee('Order Received');
        $this->actingAs($this->buyer1)->post(route('orders.confirm-delivery', $onTheWay->id))->assertSessionHas('error');
        $this->assertSame('Shipped', $onTheWay->fresh()->status);

        $delivered = $this->orderIn('Delivered');
        $this->actingAs($this->buyer1)->post(route('orders.confirm-delivery', $delivered->id))->assertSessionHas('success');
        $this->assertSame('Completed', $delivered->fresh()->status);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $delivered->id))->assertSee('Rate this item');
    }

    /** BUY-TRK-005 */
    public function test_orders_complete_by_themselves_seven_days_after_delivery(): void
    {
        $day6 = $this->orderIn('Delivered');
        $day6->forceFill(['delivered_at' => now()->subDays(6)])->save();
        $day8 = $this->orderIn('Delivered');
        $day8->forceFill(['delivered_at' => now()->subDays(7)->subMinute()])->save();

        $this->artisan('orders:complete-delivered')->assertSuccessful();
        $this->assertSame('Delivered', $day6->fresh()->status);
        $this->assertSame('Completed', $day8->fresh()->status);
    }

    // ── B11 Cancellation ──────────────────────────────────────

    /** BUY-CAN-001, 002 */
    public function test_cancel_before_pickup_restores_stock_and_tells_the_seller(): void
    {
        $order = $this->orderIn('Processing');
        $this->assertSame(18, $this->p02->fresh()->stock);

        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), [])->assertSessionHasErrors('cancellation_reason');
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionHas('success');

        $this->assertSame('Cancelled', $order->fresh()->status);
        $this->assertSame(20, $this->p02->fresh()->stock);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'like', '%cancelled%')->exists());
    }

    /** BUY-CAN-004, 005 */
    public function test_no_cancel_once_ready_for_pickup_or_shipped(): void
    {
        $ready = $this->orderIn('Processing');
        Delivery::create(['order_id' => $ready->id, 'status' => 'available', 'courier_name' => 'Courier', 'delivery_fee' => 50]);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $ready->id))->assertSee('Ready for pickup')->assertDontSee('Cancel order');
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $ready->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionHas('error');

        $shipped = $this->orderIn('Shipped');
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $shipped->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionHas('error');
        $this->assertSame('Shipped', $shipped->fresh()->status);
    }

    // ── B12 Return & Refund ───────────────────────────────────

    /** BUY-RET-001, 002, 004 */
    public function test_return_request_with_evidence(): void
    {
        $toShip = $this->orderIn('Processing');
        $this->actingAs($this->buyer1)->post(route('orders.return', $toShip->id), ['reason' => 'Damaged item', 'details' => 'The basket arrived crushed and torn.'])->assertSessionHas('error');

        $order = $this->orderIn('Delivered');
        $order->forceFill(['delivered_at' => now()->subDays(2)])->save();
        $return = fn ($file) => $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), array_filter(['reason' => 'Damaged item', 'details' => 'The basket arrived crushed and torn.', 'evidence' => $file]));

        $return(null)->assertSessionHasErrors('evidence');
        $return(UploadedFile::fake()->image('size_5.1MB.jpg')->size(5121))->assertSessionHasErrors('evidence');
        $return(UploadedFile::fake()->create('installer.exe', 2100))->assertSessionHasErrors('evidence');
        $return(UploadedFile::fake()->image('review_photo.jpg')->size(1500))->assertSessionHas('success');

        $this->assertSame('Return Requested', $order->fresh()->status);
        $this->assertSame('return_refund', $order->fresh()->tab());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'like', 'Return request%')->exists());
    }

    /** BUY-RET-003 */
    public function test_return_window_is_seven_days_and_closes_on_completion(): void
    {
        $order = $this->orderIn('Delivered');
        $order->forceFill(['delivered_at' => now()->subDays(7)->addMinute()])->save();   // still day 7
        $this->assertTrue($order->fresh()->returnWindowOpen());
        $order->forceFill(['delivered_at' => now()->subDays(8)])->save();
        $this->assertFalse($order->fresh()->returnWindowOpen());
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), ['reason' => 'Damaged item', 'details' => 'The basket arrived crushed and torn.'])->assertSessionHas('error');

        $done = $this->orderIn('Completed');
        $this->assertFalse($done->returnWindowOpen());
    }

    /** BUY-RET-005 */
    public function test_seller_decides_and_the_buyer_sees_it(): void
    {
        $order = $this->orderIn('Delivered');
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), ['reason' => 'Damaged item', 'details' => 'The basket arrived crushed and torn.', 'evidence' => UploadedFile::fake()->image('p.jpg')]);

        $this->actingAs($this->seller1)->post(route('seller.orders.return', $order->id), ['decision' => 'reject'])->assertSessionHasErrors('seller_note');
        $this->actingAs($this->seller1)->post(route('seller.orders.return', $order->id), ['decision' => 'reject', 'seller_note' => 'Item was not damaged in the photo.'])
            ->assertSessionHas('success');
        $this->assertSame('Delivered', $order->fresh()->status);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('Rejected by the seller')->assertSee('Item was not damaged in the photo.');

        $other = $this->orderIn('Delivered');
        $this->actingAs($this->buyer1)->post(route('orders.return', $other->id), ['reason' => 'Wrong item received', 'details' => 'This is not the item I ordered.', 'evidence' => UploadedFile::fake()->image('p.jpg')]);
        $this->actingAs($this->seller1)->post(route('seller.orders.return', $other->id), ['decision' => 'approve'])->assertSessionHas('success');
        $this->assertSame('Refunded', $other->fresh()->status);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $other->id))->assertSee('Approved');
    }

    // ── B13 Ratings & Reviews ─────────────────────────────────

    /** BUY-RATE-001, 002, 003, 004 */
    public function test_rate_a_completed_order_once_per_item(): void
    {
        $toReceive = $this->orderIn('Shipped');
        $item = $toReceive->items()->first();
        $this->actingAs($this->buyer1)->post(route('reviews.store', $item), ['rating' => 5])->assertSessionHas('error');

        $order = $this->orderIn('Completed');
        $item = $order->items()->first();
        $this->actingAs($this->buyer1)->post(route('reviews.store', $item), ['rating' => 0])->assertSessionHasErrors('rating');
        $this->actingAs($this->buyer1)->post(route('reviews.store', $item), [
            'rating' => 5, 'comment' => 'Matibay at maganda!', 'photo' => UploadedFile::fake()->image('review_photo.jpg')->size(1500),
        ])->assertSessionHas('success');
        $this->actingAs($this->buyer1)->post(route('reviews.store', $item), ['rating' => 4])->assertSessionHas('error');
        $this->assertSame(1, Review::count());

        $this->actingAs($this->buyer2)->get(route('products.show', $this->p02->slug))->assertSee('Matibay at maganda!')->assertSee('Review photo', false);
    }

    /** BUY-RATE-005 */
    public function test_a_review_can_be_edited_once_within_thirty_days(): void
    {
        $order = $this->orderIn('Completed');
        $this->actingAs($this->buyer1)->post(route('reviews.store', $order->items()->first()), ['rating' => 3]);
        $review = Review::firstOrFail();

        $this->actingAs($this->buyer1)->patch(route('reviews.update', $review), ['rating' => 4])->assertSessionHas('success');
        $this->actingAs($this->buyer1)->patch(route('reviews.update', $review), ['rating' => 5])->assertSessionHas('error', 'A review can be edited only once.');
        $this->assertSame(4, $review->fresh()->rating);

        $late = $this->orderIn('Completed');
        $this->actingAs($this->buyer1)->post(route('reviews.store', $late->items()->first()), ['rating' => 3]);
        $late->forceFill(['completed_at' => now()->subDays(31)])->save();
        $this->actingAs($this->buyer1)->patch(route('reviews.update', Review::latest('id')->first()), ['rating' => 5])->assertSessionHas('error');
    }

    /** BUY-RATE-006 */
    public function test_average_rating_updates(): void
    {
        $this->actingAs($this->buyer1)->post(route('reviews.store', $this->orderIn('Completed')->items()->first()), ['rating' => 4]);
        $this->assertEquals(4.0, $this->p02->fresh()->rating_avg);

        $this->actingAs($this->buyer2)->post(route('reviews.store', $this->orderIn('Completed', $this->buyer2)->items()->first()), ['rating' => 2]);
        $this->assertEquals(3.0, $this->p02->fresh()->rating_avg);
        $this->assertSame(2, $this->p02->fresh()->rating_count);
    }

    // ── B14 Chat & Notifications ──────────────────────────────

    /** BUY-CHAT-001, 002 */
    public function test_chat_with_a_seller_from_a_product(): void
    {
        $start = $this->actingAs($this->buyer1)->post(route('messages.new'), ['receiver_id' => $this->seller1->id, 'product_id' => $this->p02->id]);
        $thread = Message::threadKey($this->buyer1->id, $this->seller1->id);
        $start->assertRedirect(route('messages.direct', $thread));
        $this->actingAs($this->buyer1)->get(route('messages.direct', $thread))->assertSee('Rattan Fruit Basket');

        $this->actingAs($this->buyer1)->post(route('messages.direct.store', $thread), ['body' => 'Hi, available pa po ba?'])->assertSessionHas('success');
        $this->actingAs($this->buyer1)->post(route('messages.direct.store', $thread), ['attachment' => UploadedFile::fake()->image('review_photo.jpg')->size(1500)])
            ->assertSessionHas('success');

        $msgs = Message::where('thread_key', $thread)->oldest('id')->get();
        $this->assertSame($this->p02->id, $msgs[0]->product_id);
        $this->assertNotNull($msgs[1]->attachment_path);
        $this->actingAs($this->seller1)->get(route('messages.direct', $thread))->assertSee('Hi, available pa po ba?')->assertSee('Rattan Fruit Basket');

        // 002: a program or a file over 5 MB is refused.
        $this->actingAs($this->buyer1)->post(route('messages.direct.store', $thread), ['attachment' => UploadedFile::fake()->create('installer.exe', 2100)])
            ->assertSessionHasErrors('attachment');
        $this->actingAs($this->buyer1)->post(route('messages.direct.store', $thread), ['attachment' => UploadedFile::fake()->image('size_5.1MB.jpg')->size(5121)])
            ->assertSessionHasErrors('attachment');
    }

    /** BUY-CHAT-003, 005 */
    public function test_every_status_change_notifies_and_opening_one_marks_it_read(): void
    {
        $order = $this->orderIn('Delivered');

        $titles = UserNotification::where('user_id', $this->buyer1->id)->oldest('id')->pluck('title')->all();
        $this->assertSame(['Order placed', 'Order confirmed', 'Order shipped', 'Order delivered'], $titles);

        $note = UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Order shipped')->first();
        $before = UserNotification::where('user_id', $this->buyer1->id)->whereNull('read_at')->count();
        $this->actingAs($this->buyer1)->get(route('notifications.open', $note->id))->assertRedirect(route('profile.orders.show', $order->id, false));
        $this->assertSame($before - 1, UserNotification::where('user_id', $this->buyer1->id)->whereNull('read_at')->count());

        // Other people's notifications can't be opened.
        $other = UserNotification::where('user_id', $this->seller1->id)->first();
        $this->actingAs($this->buyer1)->get(route('notifications.open', $other->id))->assertNotFound();
    }
}
