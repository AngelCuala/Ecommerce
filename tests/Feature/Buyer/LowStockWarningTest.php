<?php

namespace Tests\Feature\Buyer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Buyers see "Only N left" when a product is at or below its low-stock level. */
class LowStockWarningTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
    }

    public function test_product_page_warns_when_stock_is_low_only(): void
    {
        // Capiz Shell Lamp: 1 in stock (default alert level 5).
        $this->get(route('products.show', $this->p03->slug))->assertOk()
            ->assertSee('Only <span id="pd-low-count">1</span> left', false)
            ->assertDontSee('data-low-stock style="display:none', false);

        // Rattan Fruit Basket: 20 in stock, so the warning is hidden...
        $html = $this->get(route('products.show', $this->p02->slug))->getContent();
        $this->assertMatchesRegularExpression('/id="pd-low"[^>]*display:none;/', $html);

        // ...unless the seller set a higher alert level for it.
        $this->p02->update(['low_stock_threshold' => 25]);
        $html = $this->get(route('products.show', $this->p02->slug))->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="pd-low"[^>]*display:none;/', $html);
    }

    public function test_sold_out_products_show_no_low_stock_warning(): void
    {
        $this->get(route('products.show', $this->p04->slug))->assertOk()->assertDontSee('id="pd-low"', false);
    }

    public function test_product_cards_show_only_n_left(): void
    {
        $html = $this->get(route('shop.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Only 1 left', $html);   // the lamp
        $this->assertStringNotContainsString('Only 20 left', $html);
    }
}
