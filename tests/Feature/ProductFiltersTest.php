<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/**
 * Product filters. Shop: keyword, category, store, price, availability, sorting, pagination.
 * Seller inventory: own products only, status tabs, category / price / sort, combinable, paginated.
 */
class ProductFiltersTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    private function shopIds(array $q): array
    {
        return $this->get(route('shop.index', $q))->assertOk()->viewData('products')->getCollection()->pluck('id')->all();
    }

    public function test_shop_filters_by_store_and_combines_with_price_and_category(): void
    {
        $ids = $this->shopIds(['store' => $this->seller2->id]);
        $this->assertContains($this->p05->id, $ids);
        $this->assertNotContains($this->p02->id, $ids);

        $ids = $this->shopIds(['store' => $this->seller1->id, 'category' => 'Home & Garden', 'min_price' => 400, 'max_price' => 500]);
        $this->assertSame([$this->p02->id], $ids);                       // Rattan Fruit Basket ₱450

        $this->assertSame([], $this->shopIds(['store' => $this->seller2->id, 'category' => 'Home & Garden']));
        $junk = $this->shopIds(['store' => 'abc']);                       // junk store id: filter ignored, page still works
        $this->assertContains($this->p02->id, $junk);
        $this->assertContains($this->p05->id, $junk);
    }

    public function test_sold_out_items_show_only_when_asked(): void
    {
        $this->assertNotContains($this->p04->id, $this->shopIds(['search' => 'Banig']));                         // stock 0
        $this->assertContains($this->p04->id, $this->shopIds(['search' => 'Banig', 'availability' => 'all']));
    }

    public function test_shop_sorts_by_price_newest_and_best_selling(): void
    {
        $prices = fn (string $sort) => $this->get(route('shop.index', ['store' => $this->seller1->id, 'sort' => $sort]))
            ->viewData('products')->getCollection()->map(fn ($p) => $p->price)->all();
        $low = $prices('price_low');
        $this->assertSame($low, collect($low)->sort()->values()->all());
        $high = $prices('price_high');
        $this->assertSame($high, collect($high)->sortDesc()->values()->all());

        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 3)]);
        $this->assertSame($this->p02->id, $this->shopIds(['store' => $this->seller1->id, 'sort' => 'best_selling'])[0]);
    }

    public function test_shop_cards_never_expose_seller_private_details(): void
    {
        $this->get(route('shop.index', ['store' => $this->seller1->id]))->assertOk()
            ->assertSee('Maria Clara Handicrafts')                                        // public shop name in the store list
            ->assertDontSee($this->seller1->email)
            ->assertDontSee('09170000000');                                              // store phone
    }

    public function test_seller_inventory_shows_only_own_products_with_combinable_filters(): void
    {
        $page = fn (array $q) => $this->actingAs($this->seller1)->get(route('seller.books.index', $q))->assertOk()->viewData('books');

        $all = $page([])->getCollection()->pluck('seller_id')->unique()->all();
        $this->assertSame([$this->seller1->id], $all);

        $ids = $page(['category_id' => $this->homeLiving->id, 'min_price' => 300, 'max_price' => 500])->getCollection()->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$this->p02->id, $this->p04->id])->sort()->values()->all(), $ids);   // ₱450 basket, ₱350 placemat

        $this->assertSame([$this->p04->id], $page(['status' => 'out_of_stock'])->getCollection()->pluck('id')->all());
        $this->assertContains($this->p03->id, $page(['status' => 'low_stock'])->getCollection()->pluck('id')->all());   // 1 left

        $stocks = $page(['sort' => 'stock_low'])->getCollection()->pluck('stock')->all();
        $this->assertSame($stocks, collect($stocks)->sort()->values()->all());

        // Another seller's category id just finds nothing of theirs.
        $this->assertSame(0, $page(['category_id' => $this->gadgets->id])->total());
    }

    public function test_seller_inventory_rejects_bad_filters_and_paginates(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.books.index', ['min_price' => 500, 'max_price' => 100]))->assertSessionHasErrors('max_price');
        $this->actingAs($this->seller1)->get(route('seller.books.index', ['sort' => 'drop table']))->assertSessionHasErrors('sort');

        foreach (range(1, 30) as $i) {
            $this->product($this->seller1, $this->homeLiving, "Extra item {$i}", 100 + $i, 5 + $i, 0.3);
        }
        $first = $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertOk();
        $this->assertSame(25, $first->viewData('books')->count());
        $second = $this->actingAs($this->seller1)->get(route('seller.books.index', ['page' => 2]))->viewData('books');
        $this->assertSame([], array_intersect($first->viewData('books')->pluck('id')->all(), $second->pluck('id')->all()));
        $first->assertDontSee('>Reset</a>', false);
        $this->actingAs($this->seller1)->get(route('seller.books.index', ['sort' => 'price_low']))->assertSee('>Reset</a>', false);
    }
}
