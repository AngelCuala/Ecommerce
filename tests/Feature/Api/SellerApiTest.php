<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\StoreCategory;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Phase 3 of the app conversion: the seller panel through /api/v1, with the web's rules. */
class SellerApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedRiderTestData();
        $this->token = $this->seller1->createToken('test')->plainTextToken;
    }

    /** Each app request starts fresh: in a test the guards and default headers would carry over. */
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

    private function api(string $method, string $uri, array $data = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/seller' . $uri, $data);
    }

    /** A multipart form, as the app sends the product form. */
    private function form(string $uri, array $data)
    {
        return $this->withToken($this->token)->post('/api/v1/seller' . $uri, $data, ['Accept' => 'application/json']);
    }

    public function test_dashboard_and_reports(): void
    {
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $d = $this->api('GET', '/dashboard')->assertOk();
        $this->assertSame(1, $d->json('total_orders'));
        $this->assertSame(1, $d->json('to_ship'));
        $this->assertCount(6, $d->json('chart'));

        // Low stock (10 or fewer): the warning counts, the list and the alert in the bell's count.
        [$before, $low] = [$d->json('unread_notifications'), $d->json('low_stock_count')];
        $this->p02->update(['stock' => 8]);
        $d = $this->api('GET', '/dashboard')->assertOk();
        $this->assertSame(10, $d->json('low_stock_threshold'));
        $this->assertSame($low + 1, $d->json('low_stock_count'));
        $this->assertContains('Rattan Fruit Basket', collect($d->json('low_stock'))->pluck('title')->all());
        $this->assertTrue(collect($d->json('low_stock'))->firstWhere('title', 'Rattan Fruit Basket')['low_stock']);
        $this->assertSame($before + 1, $d->json('unread_notifications'));
        $this->assertTrue(collect($this->withToken($this->token)->getJson('/api/v1/notifications')->assertOk()->json('data'))->contains(fn ($n) => str_contains($n['body'], '"Rattan Fruit Basket" has only 8 left')));

        $r = $this->api('GET', '/reports?from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString())->assertOk();
        $this->assertSame('Rattan Fruit Basket', $r->json('by_product.0.title'));
        $this->api('GET', '/reports?from=2026-10-10&to=2026-10-01')->assertStatus(422)
            ->assertJsonValidationErrors(['to' => 'The end date must be on or after the start date.']);

        // The export is the website's file (CSV / XLSX), with the rows on screen and a Total row.
        $range = 'from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->toDateString();
        $csv = $this->withToken($this->token)->get("/api/v1/seller/reports/export?format=csv&{$range}", ['Accept' => 'application/json']);
        $csv->assertOk();
        $this->assertStringStartsWith('text/csv', $csv->headers->get('Content-Type'));
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Product,"Units sold","Gross sales","Net earnings"', $body);
        $this->assertStringContainsString('"Rattan Fruit Basket",1,450', $body);
        $this->assertStringContainsString('Total,1,450', $body);
        $xlsx = $this->withToken($this->token)->get("/api/v1/seller/reports/export?format=xlsx&{$range}", ['Accept' => 'application/json']);
        $xlsx->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $pdf = $this->withToken($this->token)->get("/api/v1/seller/reports/export?format=pdf&{$range}", ['Accept' => 'application/json']);
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('sales-report_', $pdf->headers->get('Content-Disposition'));
        $this->api('GET', '/reports/export?format=doc')->assertStatus(422)->assertJsonValidationErrors(['format' => 'Choose PDF, CSV or Excel (XLSX).']);
    }

    public function test_product_create_edit_and_list_actions(): void
    {
        $this->assertNotEmpty($this->api('GET', '/products/form')->assertOk()->json('categories'));

        // The web's rules and messages.
        $this->form('/products', $this->productForm(['title' => 'Bag', 'images' => []]))->assertStatus(422)
            ->assertJsonValidationErrors(['title' => 'The product name must be 5 to 120 characters.', 'images' => 'Please add at least one photo.']);

        $this->form('/products', $this->productForm(['video' => UploadedFile::fake()->create('demo.avi', 100, 'video/x-msvideo')]))
            ->assertStatus(422)->assertJsonValidationErrors('video');
        $saved = $this->form('/products', $this->productForm(['store_category_ids' => [$this->cat5->id],
            'video' => UploadedFile::fake()->create('demo.mp4', 500, 'video/mp4')]))->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'submitted for admin approval'));
        $id = $saved->json('product.id');
        $p = Product::findOrFail($id);
        $this->assertSame(['Buri Placemat Set', 'pending', 2], [$p->title, $p->approval_status, $p->images()->count()]);
        $this->assertStringEndsWith('.mp4', $saved->json('product.video_url'));

        // Edit with options ("Color / Size" rows), as the web's form sends them.
        $edit = $this->api('GET', "/products/{$id}")->assertOk();
        $this->assertSame('Buri Placemat Set', $edit->json('product.title'));
        $this->form("/products/{$id}", $this->editForm($p, [
            'variations' => [
                ['name' => 'Red / S', 'price' => '299', 'stock' => '5', 'sku' => 'BPS-RED-S'],
                ['name' => 'Red / M', 'price' => '329', 'stock' => '4', 'sku' => 'BPS-RED-M'],
            ],
        ]))->assertOk()->assertJsonPath('product.stock', 9)->assertJsonCount(2, 'product.variations');

        $this->api('PATCH', "/products/{$id}/stock", ['stock' => -1])->assertStatus(422)
            ->assertJsonValidationErrors(['stock' => 'Stock must be a whole number from 0 to 99,999.']);
        $this->api('PATCH', "/products/{$id}/archive")->assertOk()->assertJsonPath('product.is_archived', true);
        $this->assertSame(1, $this->api('GET', '/products?status=archived')->json('counts.archived'));
        $this->api('PATCH', "/products/{$id}/unarchive")->assertOk();

        $copy = $this->api('POST', "/products/{$id}/duplicate")->assertOk();
        $this->assertSame('Copy of Buri Placemat Set', $copy->json('product.title'));
        $this->assertSame('draft', $copy->json('product.status'));

        // A product with an unfinished order can't be deleted.
        $this->api('DELETE', '/products/' . $this->p02->id)->assertOk();
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p03)]);
        $this->api('DELETE', '/products/' . $this->p03->id)->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'cannot be deleted'));

        // Another seller's product: forbidden.
        $this->api('GET', '/products/' . $this->p05->id)->assertForbidden();
    }

    public function test_order_flow_accept_pack_book_pickup_and_cancel(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->assertSame(1, collect($this->api('GET', '/orders')->json('tabs'))->firstWhere('key', 'new')['count']);

        $detail = $this->api('GET', "/orders/{$order->id}")->assertOk()->json('order');
        $this->assertTrue($detail['actions']['accept']);
        $this->assertSame(450.0, (float) $detail['figures']['gross']);

        $this->api('POST', "/orders/{$order->id}/accept")->assertOk()->assertJsonPath('order.seller_step', 'accepted')->assertJsonPath('order.actions.pack', true);
        $packed = $this->api('POST', "/orders/{$order->id}/pack")->assertOk()->json('order');
        $this->assertTrue($packed['actions']['book_pickup']);
        $this->assertTrue($packed['actions']['waybill']);
        $this->assertContains($this->logistics1->id, collect($packed['sorting_centers'])->pluck('id')->all());

        $this->api('POST', "/orders/{$order->id}/handover", [])->assertStatus(422)
            ->assertJsonValidationErrors(['sorting_center_id' => 'Choose the logistics company whose rider will pick up the parcel.']);
        $this->online($this->rider1);
        $this->api('POST', "/orders/{$order->id}/handover", ['sorting_center_id' => $this->logistics1->id,
            'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])->assertOk()
            ->assertJsonPath('order.seller_step', 'ready')->assertJsonPath('order.parcel.status', 'pickup_approved');
        $waybill = $this->api('GET', "/orders/{$order->id}/waybill")->assertOk()->json('waybill');
        $this->assertSame('ALVY', substr($waybill['tracking'], 0, 4));
        $this->assertSame(['COD', 'Maria Clara Handicrafts'], [$waybill['payment_method'], $waybill['seller']['name']]);
        $this->assertSame(500.0, (float) $waybill['cod']);
        $this->assertNotEmpty($waybill['seller']['phone']);

        $other = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->api('POST', "/orders/{$other->id}/cancel", ['reason' => ''])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->api('POST', "/orders/{$other->id}/cancel", ['reason' => 'Out of stock'])->assertOk()->assertJsonPath('order.status', Order::CANCELLED);

        // Another seller's order: forbidden.
        $this->api('GET', "/orders/{$order->id}", [], $this->seller2->createToken('t')->plainTextToken)->assertForbidden();
    }

    public function test_vouchers_flash_sales_promotions_and_store_categories(): void
    {
        $this->api('POST', '/vouchers', ['code' => 'mch 50', 'name' => 'x', 'discount_type' => 'fixed', 'discount_value' => '50', 'per_user_limit' => '1'])
            ->assertStatus(422)->assertJsonValidationErrors(['code' => 'Voucher codes are 4 to 12 letters and numbers, with no spaces or symbols (e.g. SALE1111).']);
        $this->api('POST', '/vouchers', ['code' => 'MCH75', 'name' => 'Maria Clara ₱75 off', 'discount_type' => 'fixed', 'discount_value' => '75',
            'min_spend' => '500', 'per_user_limit' => '1', 'is_active' => '1', 'is_claimable' => '1'])->assertOk()
            ->assertJsonPath('message', 'Store voucher MCH75 created.');
        $v = Voucher::where('code', 'MCH75')->sole();
        $this->assertContains('MCH75', collect($this->api('GET', '/vouchers')->json('vouchers'))->pluck('code')->all());
        $this->assertFalse($this->api('GET', "/vouchers/form/{$v->id}")->json('locked'));
        $this->api('PATCH', "/vouchers/{$v->id}/toggle")->assertOk()->assertJsonPath('message', 'Voucher MCH75 turned off.');

        $this->api('POST', '/flash-sales', ['product_id' => $this->p02->id, 'flash_price' => 500, 'quantity' => 3,
            'starts_at' => now()->addHour()->format('Y-m-d H:i'), 'ends_at' => now()->addHours(3)->format('Y-m-d H:i')])
            ->assertStatus(422)->assertJsonValidationErrors('flash_price');
        $this->api('POST', '/flash-sales', ['product_id' => $this->p02->id, 'flash_price' => 299, 'quantity' => 3,
            'starts_at' => now()->addHour()->format('Y-m-d H:i'), 'ends_at' => now()->addHours(3)->format('Y-m-d H:i')])
            ->assertOk()->assertJsonPath('message', 'Flash sale scheduled for "Rattan Fruit Basket".');
        $this->assertSame('Upcoming', $this->api('GET', '/flash-sales')->json('sales.0.state'));

        $this->api('POST', '/promotions', ['name' => 'Bundle', 'type' => 'bundle', 'product_ids' => [$this->p02->id], 'min_qty' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['min_qty' => 'A bundle needs at least 2 items.']);
        $this->api('POST', '/promotions', ['name' => 'Basket bundle', 'type' => 'bundle', 'product_ids' => [$this->p02->id, $this->p04->id],
            'min_qty' => 2, 'discount_percent' => 10])->assertOk();
        $this->assertSame('Basket bundle', $this->api('GET', '/promotions')->json('promotions.0.name'));

        // Store categories: add, then delete one that has products (asks to confirm first, like the web).
        $toys = \App\Models\Category::firstOrCreate(['name' => 'Toys & Games']);
        $list = $this->api('GET', '/store-categories')->assertOk();
        $this->assertContains('Toys & Games', collect($list->json('available'))->pluck('name')->all());
        $this->api('POST', '/store-categories', ['category_id' => $toys->id])->assertOk()
            ->assertJsonPath('message', 'Category "Toys & Games" added to your store. Pick it when you add a product.');
        $added = collect($this->api('GET', '/store-categories')->json('categories'))->firstWhere('category_id', $toys->id);
        $this->assertTrue($added['from_alvy']);
        $this->assertNotContains($toys->id, collect($this->api('GET', '/store-categories')->json('available'))->pluck('id')->all());
        $this->assertContains($toys->id, $this->api('GET', '/products/form')->assertOk()->json('my_category_ids'));
        $this->api('POST', '/store-categories', ['category_id' => $toys->id])->assertStatus(422)
            ->assertJsonValidationErrors(['category_id' => 'That category is already in your store.']);
        $this->api('DELETE', '/store-categories/' . $this->cat4->id)->assertOk()->assertJsonPath('warning', true);
        $this->assertNotNull(StoreCategory::find($this->cat4->id));
        $this->api('DELETE', '/store-categories/' . $this->cat4->id . '?confirm=1')->assertOk()->assertJsonPath('warning', false);
        $this->assertNull(StoreCategory::find($this->cat4->id));
    }

    public function test_reviews_earnings_store_settings_and_account(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::COMPLETED);
        $item = $order->items()->first();
        $review = Review::create(['product_id' => $this->p02->id, 'user_id' => $this->buyer1->id, 'order_id' => $order->id,
            'order_item_id' => $item->id, 'rating' => 4, 'comment' => 'Maganda!']);

        $this->assertSame(4, $this->api('GET', '/reviews')->assertOk()->json('data.0.rating'));
        $this->api('POST', "/reviews/{$review->id}/reply", ['reply' => ''])->assertStatus(422)->assertJsonValidationErrors(['reply' => 'Please write a reply.']);
        $this->api('POST', "/reviews/{$review->id}/reply", ['reply' => 'Salamat po!'])->assertOk()->assertJsonPath('message', 'Reply posted. Buyers see it under the review.');
        $this->api('POST', "/reviews/{$review->id}/reply", ['reply' => 'Maraming salamat po!'])->assertOk()->assertJsonPath('message', 'Reply updated.');

        $e = $this->api('GET', '/earnings')->assertOk();
        $this->assertGreaterThan(0, $e->json('balances.available'));
        $this->api('POST', '/earnings/payouts', ['amount' => 50, 'request_token' => $e->json('request_token')])->assertStatus(422)
            ->assertJsonValidationErrors(['amount' => 'The minimum payout is ₱100.00.']);
        $this->api('POST', '/earnings/payouts', ['amount' => 100000, 'request_token' => (string) \Illuminate\Support\Str::uuid()])->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $store = $this->api('GET', '/store-settings')->assertOk()->json('store');
        $this->assertSame('Maria Clara Handicrafts', $store['shop_name']);
        $this->api('POST', '/store-settings', array_merge($store, ['phone' => '123']))->assertStatus(422)
            ->assertJsonValidationErrors(['phone' => 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).']);
        // NCR: the address picker has no province to send.
        $ncr = array_merge($store, ['region' => 'NCR', 'province' => '', 'province_code' => '', 'municipality' => 'Quezon City',
            'municipality_code' => '1381300000', 'barangay' => 'Bagumbayan', 'zip_code' => '1110', 'is_on_vacation' => false]);
        unset($ncr['logo_url'], $ncr['banner_url'], $ncr['address']);
        $this->api('POST', '/store-settings', $ncr)->assertOk()->assertJsonPath('store.province', 'Metro Manila');

        $this->api('PUT', '/account/password', ['current_password' => 'wrong', 'password' => 'Bago@2026x', 'password_confirmation' => 'Bago@2026x'])
            ->assertStatus(422)->assertJsonValidationErrors(['current_password' => 'The current password you entered is incorrect.']);

        // Notifications work for sellers too.
        $before = $this->withToken($this->token)->getJson('/api/v1/notifications')->assertOk()->json('unread');
        \App\Models\UserNotification::create(['user_id' => $this->seller1->id, 'title' => 'New order', 'body' => 'x', 'type' => 'info']);
        $this->withToken($this->token)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread', $before + 1);
    }

    public function test_pending_seller_only_prepares_drafts_and_sees_the_status(): void
    {
        $token = $this->seller3->createToken('t')->plainTextToken;
        $this->api('GET', '/status', [], $token)->assertOk()->assertJsonPath('status', 'pending');
        $this->api('GET', '/orders', [], $token)->assertForbidden()->assertJsonPath('home', 'seller_status');
        $this->api('GET', '/products', [], $token)->assertOk();

        $publish = $this->withToken($token)->post('/api/v1/seller/products', $this->productForm(), ['Accept' => 'application/json']);
        $publish->assertStatus(422)->assertJsonValidationErrors(['publish' => 'Your store must be verified before you can publish products. You can save this product as a draft for now.']);
    }

    public function test_buyers_cant_open_the_seller_panel(): void
    {
        $this->api('GET', '/dashboard', [], $this->buyer1->createToken('t')->plainTextToken)->assertForbidden()
            ->assertJsonPath('message', 'This part of the app is for sellers.');
    }
}
