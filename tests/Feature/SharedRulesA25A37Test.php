<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SellerPayout;
use App\Models\StoreCategory;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The shared assumption rules of the newer workbooks (A7, A12, A15, A19, A21, A22, A25, A26, A31, A34, A36, A37). */
class SharedRulesA25A37Test extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function publish(array $over = [])
    {
        return $this->actingAs($this->seller1)->from(route('seller.books.create'))->post(route('seller.books.store'), $this->productForm($over));
    }

    /** A7: a product can be in up to 3 store categories. */
    public function test_a7_up_to_three_store_categories(): void
    {
        $four = collect(['A1', 'A2', 'A3', 'A4'])->map(fn ($n) => StoreCategory::create(['seller_id' => $this->seller1->id, 'name' => 'Cat ' . $n])->id)->all();
        $this->publish(['store_category_ids' => $four])->assertSessionHasErrors(['store_category_ids' => 'A product can be in up to 3 store categories.']);
        $this->publish(['store_category_ids' => array_slice($four, 0, 3)])->assertSessionHasNoErrors();
    }

    /** A25: product name 5–120, description 20–3,000, store description up to 500, reasons 10–300. */
    public function test_a25_text_field_limits(): void
    {
        $this->publish(['description' => 'Too short'])->assertSessionHasErrors(['description' => 'The description must be 20 to 3,000 characters.']);
        $this->publish(['description' => str_repeat('a', 3001)])->assertSessionHasErrors('description');
        $this->publish(['description' => ''])->assertSessionHasErrors(['description' => 'Please describe the product (20 to 3,000 characters).']);
        $this->publish(['description' => str_repeat('a', 20)])->assertSessionHasNoErrors();

        $this->actingAs($this->seller1)->patch(route('seller.store-settings.update'), [
            'shop_name' => 'Maria Clara Handicrafts', 'description' => str_repeat('d', 501), 'phone' => '09189876543', 'region' => 'NCR',
            'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'barangay' => 'Central', 'street' => '1 Main', 'zip_code' => '1100',
        ])->assertSessionHasErrors('description');

        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Too slow'])
            ->assertSessionHasErrors(['cancellation_reason' => 'The reason must be 10 to 300 characters.']);
    }

    /** A26: voucher codes are 4 to 12 letters and digits, saved in uppercase. */
    public function test_a26_voucher_code_rule(): void
    {
        $form = fn ($code) => ['code' => $code, 'name' => 'Promo', 'discount_type' => 'fixed', 'discount_value' => 20, 'min_spend' => 100,
            'per_user_limit' => 1, 'is_active' => '1', 'is_claimable' => '1'];
        foreach (['ABC', 'TOOLONGCODE13', 'SALE-10', 'SALE 10'] as $bad) {
            $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), $form($bad))
                ->assertSessionHasErrors(['code' => 'Voucher codes are 4 to 12 letters and numbers, with no spaces or symbols (e.g. SALE1111).']);
        }
        $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), $form('mch2026'))->assertSessionHasNoErrors();
        $this->assertTrue(Voucher::where('code', 'MCH2026')->exists());
        $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), $form('PLAT100'))->assertSessionHasErrors('code');   // unique platform-wide
    }

    /** A12: review text up to 500 characters and up to 5 photos. */
    public function test_a12_review_text_and_photos(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::COMPLETED);
        $item = OrderItem::where('order_id', $order->id)->sole();
        $photos = fn ($n) => array_map(fn ($i) => UploadedFile::fake()->image("r{$i}.jpg"), range(1, $n));

        $this->actingAs($this->buyer1)->post(route('reviews.store', $item), ['rating' => 5, 'comment' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['comment' => 'A review can be up to 500 characters.']);
        $this->actingAs($this->buyer1)->post(route('reviews.store', $item), ['rating' => 5, 'photos' => $photos(6)])
            ->assertSessionHasErrors(['photos' => 'You can add up to 5 photos.']);
        $this->actingAs($this->buyer1)->post(route('reviews.store', $item), ['rating' => 5, 'comment' => 'Matibay!', 'photos' => $photos(5)])
            ->assertSessionHas('success');
        $this->assertCount(5, Review::sole()->photos());
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee(Review::sole()->photos()[4]);
    }

    /** A31: description 10–500, 1 to 5 photos; auto-approved after 3 days; escalation within 3 days. */
    public function test_a31_return_request_rules(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::DELIVERED);
        $photos = fn ($n) => array_map(fn ($i) => UploadedFile::fake()->image("e{$i}.jpg"), range(1, $n));
        $send = fn ($data) => $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), ['reason' => 'Damaged item'] + $data);

        $send(['details' => 'Broken', 'evidence_photos' => $photos(1)])->assertSessionHasErrors(['details' => 'Please describe the problem (10 to 500 characters).']);
        $send(['details' => 'The basket arrived crushed.'])->assertSessionHasErrors(['evidence' => 'Please add at least one photo of the item as evidence.']);
        $send(['details' => 'The basket arrived crushed.', 'evidence_photos' => $photos(6)])->assertSessionHasErrors('evidence_photos');
        $send(['details' => 'The basket arrived crushed.', 'evidence_photos' => $photos(5)])->assertSessionHas('success');
        $this->assertCount(5, ReturnRequest::sole()->photos());

        // The seller doesn't answer in 3 days: approved automatically and refunded.
        $this->travel(3)->days();
        $this->travel(1)->minutes();
        $this->artisan('returns:auto-approve')->assertSuccessful();
        $this->assertSame('approved', ReturnRequest::sole()->status);
        $this->assertSame(Order::REFUNDED, $order->fresh()->status);
    }

    public function test_a31_escalation_only_within_three_days(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::DELIVERED);
        ReturnRequest::create(['order_id' => $order->id, 'user_id' => $this->buyer1->id, 'seller_id' => $this->seller1->id, 'reason' => 'Damaged item',
            'details' => 'The basket arrived crushed.', 'evidence_path' => 'returns/x.jpg', 'status' => 'rejected', 'seller_note' => 'Item shows signs of use',
            'refund_amount' => $order->total_price, 'resolved_at' => now()->subDays(4)]);
        $this->actingAs($this->buyer1)->post(route('orders.escalate', $order->id))->assertSessionHas('error', 'A rejected request can be escalated within 3 days of the rejection.');
        $this->assertSame(Order::DELIVERED, $order->fresh()->status);
    }

    /** A15: only one payout request can be pending at a time. */
    public function test_a15_one_pending_payout_at_a_time(): void
    {
        $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p03)])->first(), Order::COMPLETED);
        $ask = fn ($amount) => $this->actingAs($this->seller1)->post(route('seller.earnings.payout'), ['amount' => $amount, 'request_token' => (string) Str::uuid()]);
        $ask('100.00')->assertSessionHasNoErrors();
        $ask('100.00')->assertSessionHasErrors(['amount' => 'You already have a payout request waiting. You can request another once it is paid.']);
        $this->assertSame(1, SellerPayout::count());
    }

    /** A19: a variant with unfinished orders can't be removed. */
    public function test_a19_variant_with_unfinished_orders_cannot_be_removed(): void
    {
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M')]);
        $form = $this->editForm($this->p01);
        $form['variations'] = array_values(array_filter($form['variations'], fn ($r) => $r['name'] !== 'Natural / M'));
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $form)
            ->assertSessionHasErrors(['variations' => 'Variant "Natural / M" has orders that are not finished yet, so it can\'t be removed. Set its stock to 0 instead.']);
        $this->assertNotNull($this->p01->variations()->where('name', 'Natural / M')->first());
    }

    /** A21: a voucher that already expired is not given back when the order is cancelled. */
    public function test_a21_expired_voucher_is_not_returned(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)], null, ['seller_vouchers' => [$this->seller1->id => 'MCH50']])->first();
        Voucher::where('code', 'MCH50')->update(['ends_at' => now()->subMinute()]);
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind']);
        $this->assertSame(1, Voucher::where('code', 'MCH50')->value('used_count'));
    }

    /** A22: a session expires after 30 minutes of inactivity. */
    public function test_a22_session_lifetime(): void
    {
        $this->assertSame(30, (int) config('session.lifetime'));
    }

    /** A34: suspending a rider returns their not-yet-picked-up jobs to the Unassigned queue. */
    public function test_a34_suspending_a_rider_releases_jobs_not_picked_up(): void
    {
        $waiting = $this->pickupFor($this->rider1);
        $carrying = $this->pickupFor($this->rider1);
        $carrying->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        $toDeliver = $this->deliveryFor($this->rider1);

        $this->actingAs($this->logistics1)->post(route('sc.riders.toggle', $this->rider1))->assertSessionHasErrors('reason');   // A25: reason required
        $this->actingAs($this->logistics1)->post(route('sc.riders.toggle', $this->rider1), ['reason' => 'Repeated late deliveries this week'])->assertSessionHas('success');
        $log = \App\Models\ActivityLog::where('action', 'sc.riders.toggle')->where('status', 'success')->latest('id')->first();
        $this->assertSame(['Active', 'Suspended', 'Repeated late deliveries this week'], [$log->old_value, $log->new_value, $log->reason]);
        $this->assertTrue($this->rider1->fresh()->isSuspended());
        $this->assertSame('cancelled', $waiting->fresh()->status);
        $this->assertSame('picked_up', $carrying->fresh()->status);   // stays with the rider
        $this->assertNull(ParcelDelivery::find($toDeliver->id));
        $this->assertSame('sorted', $toDeliver->parcel->fresh()->status);

        $dispatch = $this->dispatcher();
        $this->assertTrue($dispatch->isUnassigned($waiting->parcel->fresh()));
        $this->assertTrue($dispatch->isUnassigned($toDeliver->parcel->fresh()));
        $this->online($this->rider2);
        $this->assertNull($dispatch->offer($waiting->parcel->fresh()));   // manual assignment only
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $waiting->parcel), ['rider_id' => $this->rider2->id])->assertSessionHas('success');
        $this->assertFalse($dispatch->isUnassigned($waiting->parcel->fresh()));
    }

    /** A36: reports export as CSV and XLSX with the on-screen rows and totals. */
    public function test_a36_report_exports(): void
    {
        $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first(), Order::COMPLETED);

        $csv = $this->actingAs($this->seller1)->get(route('seller.reports', ['export' => 'csv']))->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('"Rattan Fruit Basket",2,900', $body);
        $this->assertStringContainsString('Total,2,900', $body);

        $xlsx = $this->actingAs($this->seller1)->get(route('seller.reports', ['export' => 'xlsx']))->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('Content-Type'));
        $zip = new \ZipArchive();
        $zip->open($xlsx->getFile()->getPathname());
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Rattan Fruit Basket', $sheet);
        $this->assertStringContainsString('<v>900</v>', $sheet);

        $this->actingAs($this->logistics1)->get(route('sc.reports', ['export' => 'xlsx']))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /** A37: pressing Publish twice (or Back and resubmit) creates one product. */
    public function test_a37_publish_is_idempotent(): void
    {
        $token = (string) Str::uuid();
        $this->publish(['form_token' => $token])->assertSessionHasNoErrors();
        $this->publish(['form_token' => $token])->assertSessionHas('success', 'This product was already saved.');
        $this->assertSame(1, Product::where('title', 'Buri Placemat Set')->count());
    }

    /** A8: a return of only some items refunds each item's price minus its voucher shares. */
    public function test_a8_partial_return_refunds_only_the_chosen_items(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M'), $this->cart($this->buyer1, $this->p02)], null,
            ['seller_vouchers' => [$this->seller1->id => 'MCH50']])->first();
        $order = $this->moveTo($order, Order::DELIVERED);
        $basket = OrderItem::where('order_id', $order->id)->where('product_id', $this->p02->id)->sole();
        // MCH50 is shared by price: the basket carries 50 × 450/699 = ₱32.19 of it.
        $this->assertEquals(32.19, (float) $basket->seller_discount);

        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), ['reason' => 'Damaged item', 'details' => 'The basket arrived crushed.',
            'items' => [$basket->id], 'evidence_photos' => [UploadedFile::fake()->image('e.jpg')]])->assertSessionHas('success');
        $return = ReturnRequest::sole();
        $this->assertEquals(417.81, (float) $return->refund_amount);   // 450 − 32.19
        $this->assertTrue($return->isPartial());

        $this->actingAs($this->seller1)->post(route('seller.orders.return', $order->id), ['decision' => 'approve'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '₱417.81'));
        $order->refresh();
        $this->assertSame(Order::DELIVERED, $order->status);   // the tote is kept
        $this->assertEquals(417.81, (float) $order->refunded_amount);
        $this->assertNotNull($basket->fresh()->refunded_at);
        $this->assertNull(OrderItem::where('order_id', $order->id)->where('product_id', $this->p01->id)->value('refunded_at'));

        // The refunded basket no longer counts in the seller's earnings.
        $order->update(['status' => Order::COMPLETED]);
        $tote = OrderItem::where('order_id', $order->id)->where('product_id', $this->p01->id)->sole();
        $this->assertEquals((float) $tote->seller_earning, app(\App\Http\Controllers\Seller\EarningsController::class)->balances($this->seller1->id)['completed']);
    }

    /** A3: a Return-to-Sender parcel's stock comes back when the seller confirms receipt. */
    public function test_a3_return_to_sender_restocks_on_seller_receipt(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->assertSame(19, $this->p02->fresh()->stock);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id), ['reason' => 'Refused']);
        $this->assertSame(19, $this->p02->fresh()->stock);   // not yet: the parcel is on its way back

        $order = $d->parcel->order;
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Confirm returned parcel received');
        $this->actingAs($this->seller1)->post(route('seller.orders.returned-received', $order->id))->assertSessionHas('success');
        $this->assertSame('returned', $d->parcel->fresh()->status);
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(20, $this->p02->fresh()->stock);
    }

    /** A10, A35: manual status changes are overrides with a reason, logged with old and new value. */
    public function test_a35_overrides_are_logged_with_old_new_and_reason(): void
    {
        $d = $this->deliveryFor($this->rider1);
        $this->actingAs($this->logistics1)->post(route('sc.deliveries.update-status', $d), ['status' => 'out_for_delivery'])
            ->assertSessionHasErrors(['remarks' => 'This is a logistics override: give the reason (10 to 300 characters).']);
        $this->actingAs($this->logistics1)->post(route('sc.deliveries.update-status', $d), ['status' => 'out_for_delivery', 'remarks' => 'Rider phone is broken, updating for him'])
            ->assertSessionHas('success');
        $log = \App\Models\ActivityLog::where('action', 'sc.deliveries.update-status')->where('status', 'success')->latest('id')->first();
        $this->assertSame(['Assigned', 'Out for Delivery', 'Rider phone is broken, updating for him'], [$log->old_value, $log->new_value, $log->reason]);

        $admin = $this->account('admin', 'admin@alvy.test', 'Admin');
        $order = $d->parcel->order;
        $this->actingAs($admin)->patch(route('admin.orders.update', $order->id), ['status' => 'Delivered'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->patch(route('admin.orders.update', $order->id), ['status' => 'Delivered', 'reason' => 'Buyer confirmed by phone call'])->assertSessionHas('success');
        $log = \App\Models\ActivityLog::where('action', 'admin.orders.update')->latest('id')->first();
        $this->assertSame([Order::SHIPPED, 'Delivered', 'Buyer confirmed by phone call'], [$log->old_value, $log->new_value, $log->reason]);

        // The log has no edit or delete routes.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.activity.destroy'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.activity.update'));
        $this->actingAs($admin)->get(route('admin.activity.index'))->assertOk()->assertSee('Buyer confirmed by phone call');
    }

    /** A36: the admin seller-sales report exports as CSV and XLSX. */
    public function test_a36_admin_report_exports(): void
    {
        $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::COMPLETED);
        $admin = $this->account('admin', 'admin@alvy.test', 'Admin');
        $range = ['type' => 'sellers', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()];
        $this->actingAs($admin)->get(route('admin.reports.index', $range + ['export' => 'csv']))->assertOk();
        $xlsx = $this->actingAs($admin)->get(route('admin.reports.index', $range + ['export' => 'xlsx']))->assertOk();
        $zip = new \ZipArchive();
        $zip->open($xlsx->getFile()->getPathname());
        $this->assertStringContainsString('Maria Clara Santos', $zip->getFromName('xl/worksheets/sheet1.xml'));
    }
}
