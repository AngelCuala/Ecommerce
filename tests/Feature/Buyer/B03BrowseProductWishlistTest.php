<?php

namespace Tests\Feature\Buyer;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\StoreFollow;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Buyer workbook B3 (Browse & Search), B4 (Product Detail) and B5 (Wishlist & Follow). */
class B03BrowseProductWishlistTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
    }

    private function titles($response): array
    {
        return collect($response->viewData('products')->items())->pluck('title')->all();
    }

    private function search(array $q)
    {
        return $this->actingAs($this->buyer1)->get(route('shop.index', $q))->assertOk();
    }

    // ── B3 Browse, Search & Filter ────────────────────────────

    /** BUY-SRCH-001, 002 */
    public function test_category_page_and_store_page_with_store_categories(): void
    {
        $this->actingAs($this->buyer1)->get(route('categories.show', 'home-and-garden'))->assertOk()->assertSee('Rattan Fruit Basket');

        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertOk()
            ->assertSee('Maria Clara Handicrafts')->assertSee('Bags &amp; Luggage', false)->assertSee('Home &amp; Garden', false)
            ->assertSee('Lamps')->assertSee('Baskets');
        $lamps = $this->actingAs($this->buyer1)->get(route('stores.show', [$this->seller1->id, 'category' => 'Home & Garden', 'sub' => 'Lamps']));
        $this->assertSame(['Capiz Shell Lamp'], collect($lamps->viewData('products')->items())->pluck('title')->all());
    }

    /** BUY-SRCH-003, 004 */
    public function test_search_matches_typos_and_odd_input(): void
    {
        $this->assertSame('Rattan Fruit Basket', $this->titles($this->search(['search' => 'Rattan Fruit Basket']))[0]);
        $this->assertContains('Rattan Fruit Basket', $this->titles($this->search(['search' => 'rattan'])));

        // Typo → closest spelling.
        $typo = $this->search(['search' => 'Ratan baskit']);
        $this->assertNotNull($typo->viewData('suggestion'));
        $this->assertContains('Rattan Fruit Basket', $this->titles($typo));

        $this->search(['search' => 'zzqxw'])->assertSee('No results');
        $this->search(['search' => "% ' \" <script>"])->assertSee('&lt;script&gt;', false)->assertSee('No results');   // shown as text, no error
        $this->assertGreaterThan(0, $this->search(['search' => ''])->viewData('products')->total());   // empty search shows all
    }

    /** BUY-SRCH-005 */
    public function test_price_range_is_inclusive_and_swaps_min_and_max(): void
    {
        $this->assertContains('Rattan Fruit Basket', $this->titles($this->search(['min_price' => 300, 'max_price' => 450])));
        $this->assertNotContains('Rattan Fruit Basket', $this->titles($this->search(['min_price' => 451, 'max_price' => 1000])));

        $swapped = $this->search(['min_price' => 1000, 'max_price' => 100]);
        $this->assertNotEmpty($swapped->viewData('notes'));
        $this->assertContains('Abaca Woven Tote Bag', $this->titles($swapped));   // ₱199 is within ₱100 – ₱1,000
    }

    /** BUY-SRCH-006 */
    public function test_rating_category_and_location_filters_combine(): void
    {
        $this->review($this->p02, 5);
        $this->review($this->p05, 5);   // rated, but sold from Cebu and in Gadgets

        $hits = $this->titles($this->search(['rating' => 4, 'category' => 'Home & Garden', 'location' => 'Metro Manila']));
        $this->assertSame(['Rattan Fruit Basket'], $hits);
        $this->assertGreaterThan(1, count($this->titles($this->search([]))));   // cleared: everything again
    }

    /** BUY-SRCH-007 */
    public function test_sorting_and_pages_without_repeats(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->product($this->seller1, $this->homeLiving, "Filler {$i}", 100 + $i, 5, 0.1);
        }

        $low = $this->titles($this->search(['sort' => 'price_low']));
        $prices = collect($low)->map(fn ($t) => Product::where('title', $t)->first()->effective_price)->all();
        $sorted = $prices; sort($sorted);
        $this->assertSame($sorted, $prices);
        $high = collect($this->titles($this->search(['sort' => 'price_high'])))->first();
        $this->assertSame('Wireless Earbuds X2', $high);

        $this->sell($this->p05, 7);
        $this->assertSame('USB-C Fast Charger 20W', $this->titles($this->search(['sort' => 'best_selling']))[0]);

        $page1 = $this->titles($this->search(['sort' => 'newest']));
        $page2 = $this->titles($this->search(['sort' => 'newest', 'page' => 2]));
        $this->assertEmpty(array_intersect($page1, $page2));
        $this->assertSame(Product::published()->where('stock', '>', 0)->count(), count($page1) + count($page2));
    }

    /** BUY-SRCH-008 */
    public function test_inactive_deleted_and_draft_products_never_appear(): void
    {
        $archived = $this->product($this->seller1, $this->homeLiving, 'Hidden Archived Mat', 300, 5, 0.5, ['is_archived' => true]);
        $draft    = $this->product($this->seller1, $this->homeLiving, 'Hidden Draft Mat', 300, 5, 0.5, ['status' => 'draft']);
        $deleted  = $this->product($this->seller1, $this->homeLiving, 'Hidden Deleted Mat', 300, 5, 0.5);
        $deleted->delete();

        $this->search(['search' => 'Hidden'])->assertSee('No results');
        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertDontSee('Hidden');
        foreach ([$archived, $draft, $deleted] as $p) {
            $this->actingAs($this->buyer1)->get(route('products.show', $p->slug))->assertNotFound()->assertSee('This product is unavailable');
        }
    }

    // ── B4 Product Detail Page ────────────────────────────────

    /** BUY-PDP-001, 002, 003 */
    public function test_variations_must_be_chosen_and_sold_out_ones_are_blocked(): void
    {
        $page = $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk()
            ->assertSee('Color')->assertSee('Size')->assertSee('Natural')->assertSee('Black');
        $this->assertSame([['Natural', 'Black'], ['S', 'M', 'L']], $this->p01->variationGroups());

        $add = fn (array $data) => $this->actingAs($this->buyer1)->from(route('products.show', $this->p01->slug))->post(route('cart.store', $this->p01->id), $data);
        $add(['quantity' => 1])->assertSessionHas('error', 'Please select a variation.');
        $add(['quantity' => 1, 'variation_id' => $this->variation($this->p01, 'Black / M')->id])->assertSessionHas('error', fn ($m) => str_contains($m, 'out of stock'));
        $add(['quantity' => 1, 'variation_id' => $this->variation($this->p01, 'Natural / L')->id])->assertSessionHas('success');

        // 002: the variant's own price.
        $this->assertEquals(299, CartItem::first()->unit_price);
    }

    /** BUY-PDP-004 */
    public function test_quantity_limits(): void
    {
        $add = fn ($qty) => $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), ['quantity' => $qty]);
        foreach ([0, -1, 'abc', 21] as $bad) {
            $add($bad)->assertRedirect('/p');
            $this->assertSame(0, CartItem::count(), "quantity {$bad} must be rejected");
            session()->flush();
        }
        $add(20)->assertSessionHas('success');
        $this->assertSame(20, CartItem::first()->quantity);
    }

    /** BUY-PDP-005 */
    public function test_per_buyer_purchase_limit_counts_earlier_orders(): void
    {
        $add = fn ($qty) => $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p07->id), ['quantity' => $qty]);
        $add(3)->assertSessionHas('error', fn ($m) => str_contains($m, 'limited to 2 per buyer'));
        $add(2)->assertSessionHas('success');

        CartItem::query()->delete();
        $this->sell($this->p07, 2, $this->buyer1);
        $add(1)->assertSessionHas('error', fn ($m) => str_contains($m, 'limited to 2 per buyer'));
        $this->assertSame(0, $this->p07->remainingAllowanceFor($this->buyer1));
    }

    /** BUY-PDP-006 */
    public function test_sale_price_countdown_and_vouchers_are_shown(): void
    {
        $this->p02->update(['sale_price' => 360, 'sale_ends_at' => now()->addDays(2)]);

        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->fresh()->slug))->assertOk()
            ->assertSee('₱360.00')->assertSee('₱450.00')->assertSee('Sale ends in')
            ->assertSee('₱50 off')->assertSee('Claim');

        // Once the sale ends, the normal price is back.
        $this->p02->update(['sale_ends_at' => now()->subMinute()]);
        $this->assertEquals(450, $this->p02->fresh()->effective_price);
    }

    /** BUY-PDP-007 */
    public function test_buy_now_with_a_variant(): void
    {
        $this->cart($this->buyer1, $this->p02, 2);
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p01->id), [
            'quantity' => 1, 'action' => 'buy_now', 'variation_id' => $this->variation($this->p01, 'Natural / M')->id,
        ])->assertRedirect(route('checkout.index', ['buy_now' => 1]));

        $page = $this->actingAs($this->buyer1)->get(route('checkout.index', ['buy_now' => 1]))->assertOk();
        $this->assertSame(['Abaca Woven Tote Bag'], $page->viewData('lines')->pluck('product.title')->all());
        $page->assertSee('Natural / M')->assertDontSee('Rattan Fruit Basket');
    }

    // ── B5 Wishlist & Follow Store ────────────────────────────

    /** BUY-WISH-001, 002 */
    public function test_wishlist_add_remove_and_double_click(): void
    {
        $this->actingAs($this->buyer1)->post(route('wishlist.toggle', $this->p02->id), ['state' => 'on']);
        $this->actingAs($this->buyer1)->post(route('wishlist.toggle', $this->p02->id), ['state' => 'on']);   // double click
        $this->assertSame(1, WishlistItem::count());
        $this->actingAs($this->buyer1)->get(route('wishlist.index'))->assertSee('Rattan Fruit Basket');
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('In wishlist');

        $this->actingAs($this->buyer1)->post(route('wishlist.toggle', $this->p02->id), ['state' => 'off']);
        $this->assertSame(0, WishlistItem::count());
        $this->actingAs($this->buyer1)->get(route('wishlist.index'))->assertDontSee('Rattan Fruit Basket');
    }

    /** BUY-WISH-003 */
    public function test_wishlist_shows_sold_out_and_unavailable_items(): void
    {
        WishlistItem::create(['user_id' => $this->buyer1->id, 'product_id' => $this->p02->id]);
        WishlistItem::create(['user_id' => $this->buyer1->id, 'product_id' => $this->p05->id]);
        $this->p02->update(['stock' => 0]);
        $this->p05->delete();

        $this->actingAs($this->buyer1)->get(route('wishlist.index'))->assertOk()->assertSee('Sold out')->assertSee('Unavailable');
    }

    /** BUY-WISH-004, 005 */
    public function test_follow_store_and_guests_must_sign_in(): void
    {
        $this->actingAs($this->buyer1)->post(route('stores.follow', $this->seller1->id));
        $this->assertSame(1, $this->seller1->followers()->count());
        $this->actingAs($this->buyer1)->get(route('stores.following'))->assertSee('Maria Clara Handicrafts');
        $this->actingAs($this->buyer1)->post(route('stores.follow', $this->seller1->id));
        $this->assertSame(0, StoreFollow::count());
        session()->forget('success');
        $this->actingAs($this->buyer1)->get(route('stores.following'))->assertSee("You don't follow any store yet", false);

        auth()->logout();
        $this->post(route('wishlist.toggle', $this->p02->id))->assertRedirect(route('login'));
    }

    // ── helpers ──

    private function review(Product $p, int $stars): void
    {
        $order = Order::create(['user_id' => $this->buyer2->id, 'status' => 'Completed', 'completed_at' => now(), 'full_name' => 'A',
            'phone' => '0917', 'email' => 'a@x.test', 'address_line' => 'X', 'city' => 'X', 'zip_code' => '1', 'shipping_address' => 'X',
            'total_price' => 1, 'payment_method' => 'COD', 'payment_status' => 'Paid']);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'quantity' => 1, 'price' => $p->price]);
        Review::create(['product_id' => $p->id, 'user_id' => $this->buyer2->id, 'order_id' => $order->id, 'order_item_id' => $item->id, 'rating' => $stars]);
    }

    private function sell(Product $p, int $qty, $buyer = null): void
    {
        $order = Order::create(['user_id' => ($buyer ?? $this->buyer2)->id, 'status' => 'Pending', 'full_name' => 'A',
            'phone' => '0917', 'email' => 'a@x.test', 'address_line' => 'X', 'city' => 'X', 'zip_code' => '1', 'shipping_address' => 'X',
            'total_price' => 1, 'payment_method' => 'COD', 'payment_status' => 'Pending']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $p->id, 'quantity' => $qty, 'price' => $p->price]);
    }
}
