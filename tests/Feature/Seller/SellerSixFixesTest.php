<?php

namespace Tests\Feature\Seller;

use App\Models\Message;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/**
 * Seller fixes: categories limited to the business category, search kept after editing,
 * "Store Category" label, Notifications page, bundle dates in Philippine time, waybill after shipping.
 */
class SellerSixFixesTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        config(['app.business_timezone' => 'Asia/Manila']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // 1. Product categories / subcategories

    public function test_add_product_offers_only_the_business_category_and_its_subcategories(): void
    {
        $this->seller1->sellerApplication->update(['line_of_business' => 'Home & Garden']);

        $page = $this->actingAs($this->seller1)->get(route('seller.books.create'))->assertOk();
        $page->assertSee('data-name="Home &amp; Garden"', false)->assertDontSee('data-name="Bags &amp; Luggage"', false)
            ->assertDontSee('data-name="Electronics &amp; Gadgets"', false)
            ->assertSee('categoryId: "' . $this->homeLiving->id . '"', false);   // preselected

        // Another category, or a subcategory of another category, is refused.
        $bags = \App\Models\Category::where('name', 'Bags & Luggage')->first();
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->productForm(['category_id' => $bags->id]))
            ->assertSessionHasErrors(['category_id' => 'Choose your store\'s registered business category.']);
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->productForm(['subcategory' => 'Backpacks']))
            ->assertSessionHasErrors(['subcategory' => 'Choose a subcategory of the selected category.']);

        // Its own subcategory works.
        $this->actingAs($this->seller1)->post(route('seller.books.store'), $this->productForm(['subcategory' => 'Furniture & Decor']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['title' => 'Buri Placemat Set', 'category_id' => $this->homeLiving->id, 'subcategory' => 'Furniture & Decor']);
    }

    public function test_editing_keeps_a_product_already_in_another_category(): void
    {
        $this->seller1->sellerApplication->update(['line_of_business' => 'Home & Garden']);   // p01 is in Bags & Luggage

        $this->actingAs($this->seller1)->get(route('seller.books.edit', $this->p01))->assertOk()
            ->assertSee('data-name="Bags &amp; Luggage"', false)->assertSee('data-name="Home &amp; Garden"', false);
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $this->editForm($this->p01))->assertSessionHasNoErrors();
    }

    // 2. Search kept after editing

    public function test_saving_an_edit_returns_to_the_same_search(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.books.index', ['search' => 'basket', 'sort' => 'oldest']))->assertOk()
            ->assertSee('Rattan Fruit Basket')->assertDontSee('Capiz Shell Lamp');

        $this->actingAs($this->seller1)->get(route('seller.books.edit', $this->p02))
            ->assertSee(route('seller.books.index', ['search' => 'basket', 'sort' => 'oldest']));   // "← Inventory"
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p02), $this->editForm($this->p02, ['stock' => 21]))
            ->assertRedirect(route('seller.books.index', ['search' => 'basket', 'sort' => 'oldest']));
    }

    // 3. Store Category label

    public function test_store_category_form_says_store_category_not_top_level(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->assertOk()
            ->assertSee('— Store Category —')->assertDontSee('Top level');
        $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['name' => 'Pasalubong', 'parent_id' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('store_categories', ['seller_id' => $this->seller1->id, 'name' => 'Pasalubong', 'parent_id' => null]);
    }

    public function test_under_offers_alvy_categories_and_creates_the_parent_once(): void
    {
        $bags = \App\Models\Category::firstOrCreate(['name' => 'Bags & Luggage']);
        $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))->assertOk()
            ->assertSee('ALVY categories')->assertSee('value="alvy:' . $bags->id . '"', false);

        foreach (['Backpacks', 'Totes'] as $name) {
            $this->actingAs($this->seller1)->post(route('seller.store-categories.store'), ['name' => $name, 'parent_id' => 'alvy:' . $bags->id])
                ->assertSessionHasNoErrors();
        }

        $parents = \App\Models\StoreCategory::where('seller_id', $this->seller1->id)->where('name', $bags->name)->get();
        $this->assertCount(1, $parents);
        $this->assertNull($parents[0]->parent_id);
        $this->assertSame(['Backpacks', 'Totes'], $parents[0]->children()->orderBy('name')->pluck('name')->all());

        // Once the store has it, it is listed under "Your categories" only.
        $this->actingAs($this->seller1)->get(route('seller.store-categories.index'))
            ->assertSee('value="' . $parents[0]->id . '"', false)->assertDontSee('value="alvy:' . $bags->id . '"', false);
    }

    // 4. Notifications

    public function test_seller_notifications_page_lists_opens_and_marks_read(): void
    {
        $n = UserNotification::create(['user_id' => $this->seller1->id, 'title' => 'Product approved', 'body' => 'Abaca tote is live.',
            'type' => 'success', 'link' => route('seller.books.edit', $this->p01, false)]);
        UserNotification::create(['user_id' => $this->seller2->id, 'title' => 'Not mine', 'body' => 'x', 'type' => 'info']);

        $this->actingAs($this->seller1)->get(route('seller.dashboard'))->assertSee(route('seller.notifications'), false);
        $this->actingAs($this->seller1)->get(route('seller.notifications'))->assertOk()
            ->assertSee('Product approved')->assertSee('1 unread.')->assertDontSee('Not mine');

        $this->actingAs($this->seller1)->get(route('seller.notifications.open', $n->id))->assertRedirect(route('seller.books.edit', $this->p01, false));
        $this->assertNotNull($n->fresh()->read_at);
        $other = UserNotification::where('user_id', $this->seller2->id)->first();
        $this->actingAs($this->seller1)->get(route('seller.notifications.open', $other->id))->assertNotFound();

        UserNotification::create(['user_id' => $this->seller1->id, 'title' => 'Another', 'type' => 'info']);
        $this->actingAs($this->seller1)->post(route('seller.notifications.read-all'))->assertSessionHas('success');
        $this->assertSame(0, $this->seller1->notifications()->whereNull('read_at')->count());
    }

    public function test_order_received_reaches_notifications_not_messages(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 1)])->first();
        $this->moveTo($order, Order::DELIVERED);

        $this->actingAs($this->buyer1)->post(route('orders.confirm-delivery', $order->id))->assertSessionHas('success');

        $this->assertSame(0, Message::where('receiver_id', $this->seller1->id)->count());
        $this->actingAs($this->seller1)->get(route('seller.notifications'))->assertSee('Order ' . $order->fresh()->number() . ' completed');
    }

    // 5. Bundle promo

    public function test_bundle_dates_are_philippine_time_and_the_bundle_applies_right_away(): void
    {
        // 3:25 PM in Manila; the seller sets the bundle to start at 3:25 PM today.
        Carbon::setTestNow(Carbon::parse('2026-10-26 15:25', 'Asia/Manila'));
        $this->actingAs($this->seller1)->post(route('seller.promotions.store'), [
            'name' => 'Pamasko', 'type' => Promotion::BUNDLE, 'product_ids' => [$this->p02->id, $this->p03->id], 'min_qty' => 2,
            'discount_percent' => 15, 'starts_at' => '2026-10-26T15:25', 'ends_at' => '2026-10-28T15:25',
        ])->assertSessionHasNoErrors();

        $promo = Promotion::where('name', 'Pamasko')->firstOrFail();
        $this->assertSame('2026-10-26 07:25:00', $promo->starts_at->utc()->toDateTimeString());
        $this->assertTrue(Promotion::running()->whereKey($promo->id)->exists());
        $this->actingAs($this->seller1)->get(route('seller.promotions.index'))->assertSee('Oct 26, 3:25 PM')->assertSee('Ongoing');

        // Two Rattan baskets (₱450 each) → 15% off at checkout.
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first();
        $this->assertEquals(135.00, (float) $order->promo_discount);
    }

    public function test_checkout_names_the_bundle_that_was_applied(): void
    {
        Promotion::create(['seller_id' => $this->seller1->id, 'name' => 'Basket pair', 'type' => Promotion::BUNDLE,
            'product_ids' => [$this->p02->id], 'min_qty' => 2, 'discount_percent' => 15, 'free_qty' => 1, 'is_active' => true]);

        $line = $this->cart($this->buyer1, $this->p02, 2);
        $this->openCheckout($this->buyer1, [$line])->assertOk()
            ->assertSee('data-store-promo-row="' . $this->seller1->id . '"', false)
            ->assertSee('Basket pair (Buy 2, get 15% off)')->assertSee('−₱135.00');

        // One basket: no bundle, no row.
        $line->update(['quantity' => 1]);
        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertDontSee('data-store-promo-row', false);
    }

    public function test_bundle_end_in_the_past_is_refused_and_the_typed_time_is_kept(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-26 15:25', 'Asia/Manila'));
        $this->actingAs($this->seller1)->from(route('seller.promotions.index'))->post(route('seller.promotions.store'), [
            'name' => 'Late', 'type' => Promotion::BUNDLE, 'product_ids' => [$this->p02->id], 'min_qty' => 2,
            'discount_percent' => 10, 'ends_at' => '2026-10-26T15:00',
        ])->assertSessionHasErrors('ends_at')->assertSessionHasInput('ends_at', '2026-10-26T15:00');
    }

    // 6. Waybill

    public function test_waybill_stays_viewable_after_the_order_ships(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 1)])->first();
        $this->actingAs($this->seller1)->get(route('seller.orders.waybill', $order->id))->assertRedirect(route('seller.orders.show', $order->id));
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertDontSee('Print waybill');

        $order->update(['status' => Order::PROCESSING, 'accepted_at' => now(), 'packed_at' => now()]);
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Print waybill');

        foreach ([Order::SHIPPED, Order::DELIVERED, Order::COMPLETED] as $status) {
            $order->update(['status' => $status]);
            $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Print waybill');
            $this->actingAs($this->seller1)->get(route('seller.orders.waybill', $order->id))->assertOk()->assertSee($order->fresh()->number());
        }

        $order->update(['status' => Order::CANCELLED]);
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertDontSee('Print waybill');
        $this->actingAs($this->seller1)->get(route('seller.orders.waybill', $order->id))->assertRedirect(route('seller.orders.show', $order->id));

        // Another seller can't open it.
        $this->actingAs($this->seller2)->get(route('seller.orders.waybill', $order->id))->assertForbidden();
    }
}
