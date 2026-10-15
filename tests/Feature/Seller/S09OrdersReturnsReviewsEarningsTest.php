<?php

namespace Tests\Feature\Seller;

use App\Models\Delivery;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SellerPayout;
use App\Models\UserNotification;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S9 (Order Processing), S10 (Returns & Refunds), S11 (Reviews & Buyer Chat) and S12 (Sales Reports & Earnings). */
class S09OrdersReturnsReviewsEarningsTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    /** One COD order of 2 × P-02 from Buyer 1 (₱900 + ₱65 shipping). */
    private function newOrder(array $extra = []): Order
    {
        return $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)], null, $extra)->first();
    }

    /** The worked checkout example: Seller 1's order (Tote Natural-M + Rattan Basket, MCH50, PLAT100, FREESHIP, note). */
    private function workedExample(): Order
    {
        $lines = [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M'), $this->cart($this->buyer1, $this->p02), $this->cart($this->buyer1, $this->p05)];
        $orders = $this->placeOrder($this->buyer1, $lines, null, [
            'seller_vouchers' => [$this->seller1->id => 'MCH50'], 'platform_voucher' => 'PLAT100', 'shipping_voucher' => 'FREESHIP',
            'buyer_notes' => [$this->seller1->id => 'Please wrap as a gift'],
        ]);
        return $orders->firstWhere('seller_id', $this->seller1->id);
    }

    private function seller(string $method, string $route, Order $order, array $data = [])
    {
        return $this->actingAs($this->seller1)->from(route('seller.orders.show', $order->id))->{$method}(route($route, $order->id), $data);
    }

    /** A logistics company (sorting center) with one active rider, for Ready for Pickup. */
    private function company(): \App\Models\User
    {
        $sc = $this->account('sorting_center', 'operations@bilishatid.example.com', 'Bilis Hatid Express Inc.');
        $rider = $this->account('courier', 'pedrojose.garcia@example.com', 'Pedro Jose Garcia');
        \App\Models\Rider::create(['user_id' => $rider->id, 'sorting_center_id' => $sc->id, 'full_name' => 'Pedro Jose Garcia',
            'application_status' => 'approved', 'is_active' => true, 'is_online' => true, 'last_seen_at' => now()]);
        return $sc;
    }

    private function delivered(): Order
    {
        $order = $this->moveTo($this->newOrder(), Order::DELIVERED);
        return $order;
    }

    private function requestReturn(Order $order)
    {
        return $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.', 'evidence' => $this->jpg('review_photo.jpg', 1500),
        ])->assertSessionHasNoErrors();
    }

    // ── S9 Order Processing ─────────────────────────────────────

    /** SEL-ORD-001 (A10) */
    public function test_sel_ord_001_tabs_and_filters(): void
    {
        $new       = $this->newOrder();
        $toShip    = $this->moveTo($this->newOrder(), Order::PROCESSING);
        $shipping  = $this->moveTo($this->newOrder(), Order::SHIPPED);
        $completed = $this->moveTo($this->newOrder(), Order::COMPLETED);
        $cancelled = $this->newOrder();
        $cancelled->update(['status' => Order::CANCELLED, 'cancellation_reason' => 'Changed my mind']);
        $returned  = $this->moveTo($this->newOrder(), Order::DELIVERED);
        $returned->update(['status' => Order::RETURN_REQUESTED]);
        $old = $this->moveTo($this->newOrder(), Order::COMPLETED);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $tab = fn ($t, $q = []) => $this->actingAs($this->seller1)->get(route('seller.orders.index', ['tab' => $t] + $q))->assertOk();
        foreach (['new' => $new, 'to_ship' => $toShip, 'shipping' => $shipping, 'cancelled' => $cancelled, 'return_refund' => $returned] as $key => $order) {
            $res = $tab($key);
            $this->assertSame([$order->id], $res->viewData('orders')->pluck('id')->all(), $key);
            $this->assertSame(1, $res->viewData('counts')[$key]);
        }
        $this->assertEqualsCanonicalizing([$completed->id, $old->id], $tab('completed')->viewData('orders')->pluck('id')->all());
        $this->assertSame(7, $tab('all')->viewData('counts')['all']);

        // Date filter and search by order ID.
        $this->assertSame([$completed->id], $tab('completed', ['date_from' => now()->subDays(7)->toDateString(), 'date_to' => now()->toDateString()])->viewData('orders')->pluck('id')->all());
        $this->assertSame([$toShip->id], $tab('all', ['q' => $toShip->number()])->viewData('orders')->pluck('id')->all());
        $this->actingAs($this->seller1)->get(route('seller.orders.index', ['date_from' => now()->toDateString(), 'date_to' => now()->subDay()->toDateString()]))
            ->assertSessionHasErrors('date_to');
    }

    /** SEL-ORD-002 (A10) */
    public function test_sel_ord_002_accept_pack_ready_for_pickup_and_waybill(): void
    {
        $order = $this->newOrder();
        $this->assertSame('new', $order->sellerStep());

        $this->seller('post', 'seller.orders.accept', $order)->assertSessionHas('success');
        $this->assertSame('accepted', $order->fresh()->sellerStep());
        $this->assertSame(Order::PROCESSING, $order->fresh()->status);

        $this->seller('post', 'seller.orders.pack', $order)->assertSessionHas('success');
        $this->assertSame('packed', $order->fresh()->sellerStep());

        $company = $this->company();
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('choose the logistics company')->assertSee('Bilis Hatid Express Inc.');
        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['sorting_center_id' => $company->id, 'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'offered to Pedro Jose Garcia'));
        $this->assertSame('ready', $order->fresh()->sellerStep());
        // The chosen company sees it as ready for pickup, and its rider got the job offer.
        $parcel = \App\Models\Parcel::where('order_id', $order->id)->sole();
        $this->assertSame(['pickup_approved', $company->id], [$parcel->status, (int) $parcel->current_sorting_center_id]);
        $this->assertSame(1, \App\Models\RiderJobOffer::where('parcel_id', $parcel->id)->count());
        $this->assertFalse(Delivery::where('order_id', $order->id)->exists());

        $this->actingAs($this->seller1)->get(route('seller.orders.waybill', $order->id))->assertOk()
            ->assertSee($parcel->tracking_number)->assertSee($order->full_name)->assertSee('45 Kalayaan Ave.')
            ->assertSee('12 Tandang Sora Ave., Commonwealth')->assertSee('1.6 kg')->assertSee('₱' . number_format($order->total_price, 2));
    }

    /** SEL-ORD-003 (A10) */
    public function test_sel_ord_003_steps_cannot_be_skipped(): void
    {
        $order = $this->newOrder();
        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['sorting_center_id' => $this->company()->id, 'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHas('error', 'Accept the order first, then pack it, before booking the pickup.');
        $this->seller('post', 'seller.orders.pack', $order)->assertSessionHas('error', 'Accept the order before packing it.');
        $this->actingAs($this->seller1)->get(route('seller.orders.waybill', $order->id))->assertRedirect(route('seller.orders.show', $order->id));
        $this->assertSame('new', $order->fresh()->sellerStep());
        $this->assertFalse(\App\Models\Parcel::where('order_id', $order->id)->exists());

        // Only the next valid action is offered.
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Accept order')->assertDontSee('Mark as packed')->assertDontSee('book the courier');
        $this->seller('post', 'seller.orders.accept', $order);
        $this->seller('post', 'seller.orders.accept', $order)->assertSessionHas('error', 'Only a new order can be accepted.');
    }

    /** SEL-ORD-004 (A3, A21, A23) */
    public function test_sel_ord_004_seller_cancels_a_cod_order_with_a_reason(): void
    {
        $order = $this->newOrder(['seller_vouchers' => [$this->seller1->id => 'MCH50']]);
        $this->seller('post', 'seller.orders.accept', $order);
        $this->assertSame(18, $this->p02->fresh()->stock);
        $this->assertSame(1, Voucher::where('code', 'MCH50')->value('used_count'));

        $this->seller('post', 'seller.orders.cancel', $order, ['reason' => ''])->assertSessionHasErrors(['reason' => 'Please choose a reason for cancelling.']);
        $this->assertSame(Order::PROCESSING, $order->fresh()->status);

        $this->seller('post', 'seller.orders.cancel', $order, ['reason' => 'Out of stock'])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertSame('Cancelled by the seller: Out of stock', $order->cancellation_reason);
        $this->assertSame(20, $this->p02->fresh()->stock);
        $this->assertSame(0, Voucher::where('code', 'MCH50')->value('used_count'));   // back in the buyer's wallet (A21)
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Order cancelled')
            ->where('body', 'like', '%Out of stock%')->exists());
    }

    /** SEL-ORD-005 (A6) */
    public function test_sel_ord_005_seller_cancels_a_prepaid_order(): void
    {
        config(['payments.gateway' => 'sandbox']);
        $order = $this->newOrder(['payment_method' => 'gcash']);
        $this->actingAs($this->buyer1)->post(route('payments.sandbox.act', Payment::sole()), ['result' => 'pay']);
        $order->refresh();
        $this->assertSame('Paid', $order->payment_status);
        $this->seller('post', 'seller.orders.accept', $order);

        $this->seller('post', 'seller.orders.cancel', $order, ['reason' => 'Item damaged before shipping'])->assertSessionHas('success', fn ($m) => str_contains($m, 'refunded in full'));
        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertSame('Refunded', $order->payment_status);
        $this->assertEquals($order->total_price, $order->refunded_amount);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('Cancelled');
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Cancelled');
    }

    /** SEL-ORD-006 (A6) */
    public function test_sel_ord_006_no_cancel_after_pickup(): void
    {
        $order = $this->newOrder();
        $this->seller('post', 'seller.orders.accept', $order);
        $this->seller('post', 'seller.orders.pack', $order);
        Delivery::create(['order_id' => $order->id, 'courier_name' => 'Pedro Jose Garcia', 'status' => 'picked_up', 'delivery_fee' => 50, 'picked_up_at' => now()]);

        $this->assertFalse($order->fresh()->isCancellableBySeller());
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertDontSee('Cancel order')->assertDontSee('name="status"', false);
        $this->seller('post', 'seller.orders.cancel', $order, ['reason' => 'Out of stock'])
            ->assertSessionHas('error', 'This order has already been handed to the courier, so it can no longer be cancelled.');
        $this->assertSame(Order::PROCESSING, $order->fresh()->status);
    }

    /** SEL-ORD-007 */
    public function test_sel_ord_007_order_details(): void
    {
        $order = $this->workedExample();
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertOk()
            ->assertSee('Natural / M')->assertSee('₱249.00 × 1', false)->assertSee('Rattan Fruit Basket')
            ->assertSee('Store voucher MCH50')->assertSee('−₱50.00', false)
            ->assertSee('Shipping fee (buyer pays)')->assertSee('₱65.00')
            ->assertSee('Please wrap as a gift')->assertSee('COD');
    }

    // ── S10 Returns & Refunds ───────────────────────────────────

    /** SEL-RET-001 (A23) */
    public function test_sel_ret_001_approve_a_return(): void
    {
        $order = $this->delivered();
        $this->requestReturn($order);
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Damaged item')->assertSee('Evidence photo');

        $this->seller('post', 'seller.orders.return', $order, ['decision' => 'approve'])->assertSessionHas('success');
        $this->assertSame(Order::REFUNDED, $order->fresh()->status);
        $this->assertSame('approved', ReturnRequest::sole()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Refund approved')->exists());
    }

    /** SEL-RET-002 */
    public function test_sel_ret_002_reject_without_a_reason(): void
    {
        $order = $this->delivered();
        $this->requestReturn($order);
        $this->seller('post', 'seller.orders.return', $order, ['decision' => 'reject', 'seller_note' => ''])
            ->assertSessionHasErrors(['seller_note' => 'Please tell the buyer why the return is rejected.']);
        $this->assertSame('requested', ReturnRequest::sole()->status);
    }

    /** SEL-RET-003 */
    public function test_sel_ret_003_reject_with_a_reason(): void
    {
        $order = $this->delivered();
        $this->requestReturn($order);
        $this->seller('post', 'seller.orders.return', $order, ['decision' => 'reject', 'seller_note' => 'Item shows signs of use'])->assertSessionHas('success');

        $this->assertSame('rejected', ReturnRequest::sole()->status);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('Item shows signs of use')->assertSee('Escalate');
    }

    /** SEL-RET-004 */
    public function test_sel_ret_004_escalate_to_a_dispute(): void
    {
        $order = $this->delivered();
        $this->requestReturn($order);
        $this->seller('post', 'seller.orders.return', $order, ['decision' => 'reject', 'seller_note' => 'Item shows signs of use']);

        $this->actingAs($this->buyer1)->post(route('orders.escalate', $order->id), ['dispute_note' => 'It was crushed on arrival.'])->assertSessionHas('success');
        $this->assertSame(Order::IN_DISPUTE, $order->fresh()->status);
        $this->assertSame('disputed', ReturnRequest::sole()->status);

        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('In Dispute')->assertDontSee('Approve refund');
        $this->seller('post', 'seller.orders.return', $order, ['decision' => 'approve'])->assertSessionHas('error', 'There is no return request waiting for a decision.');
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('In dispute');

        // ALVY decides.
        $admin = $this->account('admin', 'admin@alvy.test', 'Admin');
        $this->actingAs($admin)->post(route('admin.orders.dispute', $order->id), ['decision' => 'refund'])->assertSessionHas('success');
        $this->assertSame(Order::REFUNDED, $order->fresh()->status);
    }

    /** SEL-RET-005 (A15) */
    public function test_sel_ret_005_refund_removed_from_sales_and_earnings(): void
    {
        $order = $this->delivered();
        $this->requestReturn($order);
        $this->seller('post', 'seller.orders.return', $order, ['decision' => 'approve']);

        $report = $this->actingAs($this->seller1)->get(route('seller.reports'))->assertOk();
        $this->assertEquals(0, $report->viewData('totalRevenue'));
        $this->assertEquals(0, $report->viewData('totalEarnings'));
        $this->assertSame(0, $report->viewData('totalOrders'));
        $this->assertEquals(0, app(\App\Http\Controllers\Seller\EarningsController::class)->balances($this->seller1->id)['pending']);
    }

    // ── S11 Reviews & Buyer Chat ────────────────────────────────

    private function reviews(): array
    {
        // One review per order item: each buyer reviews their own completed order.
        $make = function ($user, $rating, $comment) {
            $order = $this->moveTo($this->placeOrder($user, [$this->cart($user, $this->p02)])->first(), Order::COMPLETED);
            $item  = OrderItem::where('order_id', $order->id)->sole();
            return Review::create(['product_id' => $this->p02->id, 'user_id' => $user->id, 'order_id' => $order->id,
                'order_item_id' => $item->id, 'rating' => $rating, 'comment' => $comment, 'photo_path' => 'reviews/basket.jpg']);
        };
        return [$make($this->buyer1, 5, 'Matibay at maganda!'), $make($this->buyer2, 1, 'Medyo maliit.')];
    }

    /** SEL-REV-001 */
    public function test_sel_rev_001_view_reviews(): void
    {
        [$five, $one] = $this->reviews();
        $this->actingAs($this->seller1)->get(route('seller.reviews.index'))->assertOk()
            ->assertSee('Matibay at maganda!')->assertSee('Medyo maliit.')->assertSee('Rattan Fruit Basket')->assertSee('reviews/basket.jpg')
            ->assertSee($five->created_at->format('M j, Y'));
        $this->actingAs($this->seller1)->get(route('seller.reviews.index', ['stars' => 1]))->assertSee('Medyo maliit.')->assertDontSee('Matibay at maganda!');
        $this->actingAs($this->seller2)->get(route('seller.reviews.index'))->assertDontSee('Medyo maliit.');
    }

    /** SEL-REV-002 (A12) */
    public function test_sel_rev_002_reply_to_a_review(): void
    {
        [$five] = $this->reviews();
        $this->actingAs($this->seller1)->post(route('seller.reviews.reply', $five), ['reply' => 'Salamat po! Enjoy your basket.'])->assertSessionHas('success');
        $this->assertSame('Salamat po! Enjoy your basket.', $five->fresh()->seller_reply);
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('Salamat po! Enjoy your basket.');
    }

    /** SEL-REV-003 (A12) */
    public function test_sel_rev_003_one_reply_but_it_can_be_edited(): void
    {
        [$five] = $this->reviews();
        $this->actingAs($this->seller1)->post(route('seller.reviews.reply', $five), ['reply' => 'Salamat po!']);
        $this->actingAs($this->seller1)->post(route('seller.reviews.reply', $five), ['reply' => 'Second reply'])
            ->assertSessionHas('error', 'You already replied to this review. You can edit your reply instead.');
        $this->actingAs($this->seller1)->put(route('seller.reviews.reply.update', $five), ['reply' => 'Salamat po! Balik po kayo.'])->assertSessionHas('success');
        $this->assertSame('Salamat po! Balik po kayo.', $five->fresh()->seller_reply);
        // Another seller can't reply to it.
        $this->actingAs($this->seller2)->post(route('seller.reviews.reply', $five), ['reply' => 'Hi'])->assertForbidden();
    }

    /** SEL-REV-004 (A12) */
    public function test_sel_rev_004_seller_cannot_delete_a_review(): void
    {
        [, $one] = $this->reviews();
        $this->actingAs($this->seller1)->get(route('seller.reviews.index'))->assertDontSee('Delete');
        $this->actingAs($this->seller1)->delete(route('admin.reviews.destroy', $one->id))->assertForbidden();
        $this->assertNotNull($one->fresh());
    }

    /** SEL-REV-005 (A16) */
    public function test_sel_rev_005_chat_with_an_attachment(): void
    {
        $key = min($this->buyer1->id, $this->seller1->id) . '_' . max($this->buyer1->id, $this->seller1->id);
        Message::create(['thread_key' => $key, 'sender_id' => $this->buyer1->id, 'receiver_id' => $this->seller1->id, 'body' => 'Available pa po?']);
        $send = fn ($data) => $this->actingAs($this->seller1)->from(route('messages.direct', $key))->post(route('messages.direct.store', $key), $data);

        $send(['body' => 'Yes po, available!', 'attachment' => $this->jpg('review_photo.jpg', 1500)])->assertSessionHasNoErrors();
        $send(['attachment' => $this->jpg('size_5.1MB.jpg', 5222)])->assertSessionHasErrors(['attachment' => 'A photo can be at most 5 MB.']);
        $send(['attachment' => UploadedFile::fake()->create('installer.exe', 2100, 'application/x-msdownload')])->assertSessionHasErrors('attachment');

        $this->assertSame(1, Message::where('sender_id', $this->seller1->id)->count());
        $sent = Message::where('sender_id', $this->seller1->id)->sole();
        $this->assertNotNull($sent->attachment_path);
        $this->actingAs($this->buyer1)->get(route('messages.direct', $key))->assertOk()->assertSee('Yes po, available!');
    }

    /** SEL-REV-006 */
    public function test_sel_rev_006_empty_message(): void
    {
        $key = min($this->buyer1->id, $this->seller1->id) . '_' . max($this->buyer1->id, $this->seller1->id);
        foreach (['', '   '] as $body) {
            $this->actingAs($this->seller1)->post(route('messages.direct.store', $key), ['body' => $body])
                ->assertSessionHasErrors(['body' => 'Write a message or attach a photo.']);
        }
        $this->assertSame(0, Message::count());
    }

    // ── S12 Sales Reports & Earnings ────────────────────────────

    /** SEL-EARN-001 (A8, A9) */
    public function test_sel_earn_001_net_earnings_calculation(): void
    {
        $order = $this->moveTo($this->workedExample(), Order::COMPLETED);

        $this->actingAs($this->seller1)->get(route('seller.earnings'))->assertOk()
            ->assertSee('data-order="' . $order->id . '"', false)
            ->assertSee('₱699.00')->assertSee('−₱50.00', false)->assertSee('−₱32.45', false)->assertSee('₱616.55');
        $this->assertEquals(616.55, round(OrderItem::where('order_id', $order->id)->sum('seller_earning'), 2));
        // A8: PLAT100 is split by merchandise subtotal: ₱63.66 to Seller 1's order, ₱36.34 to Seller 2's.
        $this->assertEquals(63.66, (float) $order->platform_discount);
        $this->assertEquals(36.34, (float) Order::where('seller_id', $this->seller2->id)->value('platform_discount'));
        // The ALVY and shipping vouchers (₱100 and ₱50 off this checkout) don't reduce Seller 1's earnings.
        $this->assertGreaterThan(0, (float) $order->platform_discount);
        $this->assertEquals(0, round(OrderItem::where('order_id', $order->id)->sum('promo_discount'), 2));
        $this->assertEquals(round(649 * 0.05, 2), round(OrderItem::where('order_id', $order->id)->sum('commission_amount'), 2));
    }

    /** SEL-EARN-002 (A15) */
    public function test_sel_earn_002_pending_until_completed(): void
    {
        $order = $this->moveTo($this->workedExample(), Order::DELIVERED);
        $balances = fn () => app(\App\Http\Controllers\Seller\EarningsController::class)->balances($this->seller1->id);

        $this->assertEquals(616.55, $balances()['pending']);
        $this->assertEquals(0, $balances()['available']);

        $this->actingAs($this->buyer1)->post(route('orders.confirm-delivery', $order->id))->assertSessionHas('success');
        $this->assertSame(Order::COMPLETED, $order->fresh()->status);
        $this->assertEquals(0, $balances()['pending']);
        $this->assertEquals(616.55, $balances()['available']);
        $this->actingAs($this->seller1)->get(route('seller.earnings'))->assertSee('data-balance="available"', false)->assertSee('₱616.55');
    }

    /** SEL-EARN-003 (A15) */
    public function test_sel_earn_003_cancelled_and_refunded_orders_excluded(): void
    {
        $kept = $this->moveTo($this->newOrder(), Order::COMPLETED);
        $cancelled = $this->newOrder();
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $cancelled->id), ['cancellation_reason' => 'Changed my mind']);
        $refunded = $this->delivered();
        $this->requestReturn($refunded);
        $this->seller('post', 'seller.orders.return', $refunded, ['decision' => 'approve']);

        $report = $this->actingAs($this->seller1)->get(route('seller.reports'))->assertOk();
        $this->assertSame(1, $report->viewData('totalOrders'));
        $this->assertEquals(900, $report->viewData('totalRevenue'));
        $this->assertEquals(855, $report->viewData('totalEarnings'));   // 900 − 5%
        $this->assertSame(1, $this->actingAs($this->seller1)->get(route('seller.earnings'))->viewData('orders')->total());
        $this->assertSame($kept->id, $this->actingAs($this->seller1)->get(route('seller.earnings'))->viewData('orders')->first()->id);
    }

    /** SEL-EARN-004 */
    public function test_sel_earn_004_report_date_range(): void
    {
        $today = $this->moveTo($this->newOrder(), Order::COMPLETED);
        $older = $this->moveTo($this->newOrder(), Order::COMPLETED);
        $older->forceFill(['created_at' => now()->subDays(5)])->save();
        $oldest = $this->moveTo($this->newOrder(), Order::COMPLETED);
        $oldest->forceFill(['created_at' => now()->subDays(20)])->save();
        $report = fn ($from, $to) => $this->actingAs($this->seller1)->get(route('seller.reports', ['from' => $from, 'to' => $to]));

        $this->assertSame(1, $report(now()->toDateString(), now()->toDateString())->viewData('totalOrders'));
        $this->assertSame(2, $report(now()->subDays(6)->toDateString(), now()->toDateString())->viewData('totalOrders'));
        $this->assertSame(1, $report(now()->subDays(25)->toDateString(), now()->subDays(10)->toDateString())->viewData('totalOrders'));
        $this->assertEquals(1800, $report(now()->subDays(6)->toDateString(), now()->toDateString())->viewData('totalRevenue'));
        $report(now()->toDateString(), now()->subDay()->toDateString())->assertSessionHasErrors(['to' => 'The end date must be on or after the start date.']);
    }

    /** SEL-EARN-005 (A15) */
    public function test_sel_earn_005_payout_request_limits(): void
    {
        $this->moveTo($this->workedExample(), Order::COMPLETED);
        $request = fn ($amount) => $this->actingAs($this->seller1)->from(route('seller.earnings'))
            ->post(route('seller.earnings.payout'), ['amount' => $amount, 'request_token' => (string) Str::uuid()]);

        $request('99.99')->assertSessionHasErrors(['amount' => 'The minimum payout is ₱100.00.']);
        $request('616.56')->assertSessionHasErrors(['amount' => 'You can request up to your available balance of ₱616.55.']);
        $request('616.55')->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(1, SellerPayout::count());

        $balances = app(\App\Http\Controllers\Seller\EarningsController::class)->balances($this->seller1->id);
        $this->assertEquals(0, $balances['available']);
        $this->assertEquals(616.55, $balances['pending_payout']);
        $request('100.00')->assertSessionHasErrors('amount');   // nothing left

        SellerPayout::query()->delete();
        $request('100.00')->assertSessionHasNoErrors();
        $this->actingAs($this->seller1)->get(route('seller.earnings'))->assertSee('₱516.55')->assertSee('Pending payout');
    }

    /** SEL-EARN-006 */
    public function test_sel_earn_006_double_click_request_payout(): void
    {
        $this->moveTo($this->workedExample(), Order::COMPLETED);
        $form = ['amount' => '200.00', 'request_token' => (string) Str::uuid()];

        $this->actingAs($this->seller1)->post(route('seller.earnings.payout'), $form)->assertSessionHas('success');
        $this->actingAs($this->seller1)->post(route('seller.earnings.payout'), $form)->assertSessionHas('success', 'Your payout request was already sent.');
        $this->assertSame(1, SellerPayout::count());
        $this->assertEquals(200, SellerPayout::sole()->amount);
    }
}
